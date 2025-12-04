<?php

namespace App\Http\Controllers;

use App\Models\ChatAttachment;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Expert;
use App\Models\MultiCompareAttachment;
use App\Models\MultiCompareConversation;
use App\Models\MultiCompareMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    // Convert messages format for Claude API
    private function convertMessagesToClaudeFormat($messages)
    {
        $systemMessage = null;
        $claudeMessages = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                // Claude uses separate system parameter
                $systemMessage = $msg['content'];
                continue;
            }

            // Handle image content for Claude
            if (isset($msg['content']) && is_array($msg['content'])) {
                $claudeContent = [];
                foreach ($msg['content'] as $content) {
                    if ($content['type'] === 'text') {
                        $claudeContent[] = [
                            'type' => 'text',
                            'text' => $content['text']
                        ];
                    } elseif ($content['type'] === 'image_url') {
                        // Claude requires base64 encoded images
                        $imageUrl = $content['image_url']['url'];
                        
                        // Fetch and convert to base64
                        try {
                            $imageData = file_get_contents($imageUrl);
                            $base64 = base64_encode($imageData);
                            $mimeType = $this->getImageMimeType($imageUrl);
                            
                            $claudeContent[] = [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $mimeType,
                                    'data' => $base64
                                ]
                            ];
                        } catch (\Exception $e) {
                            Log::error('Error converting image for Claude: ' . $e->getMessage());
                        }
                    }
                }
                
                $claudeMessages[] = [
                    'role' => $msg['role'],
                    'content' => $claudeContent
                ];
            } else {
                // Simple text message
                $claudeMessages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content']
                ];
            }
        }

        return ['system' => $systemMessage, 'messages' => $claudeMessages];
    }

    private function getImageMimeType($url)
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ];
        return $mimeTypes[$extension] ?? 'image/jpeg';
    }

    public function chat(Request $request)
    {
        set_time_limit(300);
        
        Log::debug('Full incoming request', [
            'all' => $request->all(),
            'hasFile' => $request->hasFile('pdf'),
            'web_search' => $request->boolean('web_search'),
            'create_image' => $request->boolean('create_image'),
            'auto_optimize_model' => $request->boolean('auto_optimize_model'),  // ✅ NEW
            'allow_cross_provider' => $request->boolean('allow_cross_provider'), // ✅ NEW
        ]);

        $editingMessageId = $request->input('editing_message_id');
        $isEditing = !empty($editingMessageId);

        $request->validate([
            'message' => 'required|string',
            'conversation_id' => 'nullable|exists:chat_conversations,id',
            'pdf' => 'nullable|file|mimes:pdf,doc,docx,png,jpg,jpeg,webp,gif|max:10240',
            'create_image' => 'sometimes|boolean',
            'auto_optimize_model' => 'sometimes|boolean',      // ✅ NEW
            'allow_cross_provider' => 'sometimes|boolean',     // ✅ NEW
        ]);

        if ($isEditing && $request->conversation_id) {
            ChatMessage::where('conversation_id', $request->conversation_id)
                ->where('role', 'assistant')
                ->where('id', $editingMessageId)
                ->delete();
        }

        $user = auth()->user();
        $openaiModel = $user->selected_model;
        $useWebSearch = $request->boolean('web_search');
        $createImage = $request->boolean('create_image');
        $expert = null;
        $systemMessage = null;
        
        // Check if current model is Claude
        $isCurrentModelClaude = $this->isClaudeModel($openaiModel);

        // ✅ Analyze query complexity early
        $complexityScore = \App\Services\AI\QueryComplexityAnalyzer::analyze($request->message);
        $complexityFeatures = \App\Services\AI\QueryComplexityAnalyzer::extractFeatures($request->message);
        
        Log::info('Query Complexity Analysis', [
            'query' => substr($request->message, 0, 100) . '...',
            'complexity_score' => $complexityScore,
            'features' => $complexityFeatures,
            'user_selected_model' => $openaiModel,
            'user_plan' => $user->plan?->name,
            'user_accessible_models' => $user->aiModels()
        ]);

        // Handle Expert Selection (UNCHANGED)
        if ($request->filled('expert_id')) {
            $expert = Expert::find($request->expert_id);
            if ($expert) {
                $systemMessage = $expert->expertise;
                
                $documents = $expert->documents;
                if ($documents->isNotEmpty()) {
                    $documentContext = "\n\nExpert Document Context:";
                    foreach ($documents as $doc) {
                        if (!empty($doc->content)) {
                            $documentContext .= "\n\n[From {$doc->file_name}]:\n" . 
                                            substr($doc->content, 0, 2000);
                        }
                    }
                    $systemMessage .= $documentContext;
                }
                
                if (!$request->conversation_id) {
                    $conversation = ChatConversation::create([
                        'user_id' => $user->id,
                        'expert_id' => $expert->id,
                        'title' => $expert->name . ' Chat'
                    ]);
                    $request->merge(['conversation_id' => $conversation->id]);
                }
            }
        }

        // Handle Image Generation (UNCHANGED)
        if ($createImage) {
            if ($isCurrentModelClaude) {
                return response()->json([
                    'error' => 'Image generation is not supported with Claude models. Please switch to an OpenAI model.'
                ], 400);
            }
            return $this->handleImageGeneration($request, $user);
        }

        // ✅✅✅ NEW: Get suggested model based on complexity and cross-provider setting ✅✅✅
        $autoOptimizeEnabled = $request->boolean('auto_optimize_model', true); // default true
        $allowCrossProvider = $request->boolean('allow_cross_provider', false); // default false

        Log::debug('Model optimization settings', [
            'auto_optimize_enabled' => $autoOptimizeEnabled,
            'allow_cross_provider' => $allowCrossProvider,
            'current_model' => $openaiModel,
            'current_provider' => $this->getModelProvider($openaiModel)
        ]);

        // Get suggested model based on cross-provider setting
        $suggestedModel = $allowCrossProvider 
            ? $this->getSuggestedModelFromAnyProvider($complexityScore, $openaiModel, $user)
            : $this->getSuggestedModelFromComplexity($complexityScore, $openaiModel, $user);

        Log::info('Model suggestion', [
            'suggested_model' => $suggestedModel,
            'suggested_provider' => $this->getModelProvider($suggestedModel),
            'current_model' => $openaiModel,
            'current_provider' => $this->getModelProvider($openaiModel),
            'cross_provider_enabled' => $allowCrossProvider,
            'suggestion_reason' => $suggestedModel !== $openaiModel ? 'Complexity-based suggestion' : 'Current model optimal'
        ]);

        // Model selection with web search handling
        if ($useWebSearch) {
            if ($isCurrentModelClaude) {
                // Claude: Use the selected Claude model with search tools
                $modelToUse = $openaiModel;
                
                if ($systemMessage) {
                    $systemMessage .= "\n\nYou have access to web search capabilities. When needed, you can search for current information, recent events, and real-time data. Always cite your sources when providing information from the web.";
                } else {
                    $systemMessage = "You are a helpful AI assistant with access to web search. When answering questions, you can search for current information, recent events, and real-time data. Always cite your sources when providing information from the web.";
                }
                
                Log::debug('Web search enabled for Claude model', ['model' => $modelToUse]);
                
            } elseif ($this->isGeminiModel($openaiModel)) {
                // Gemini: Use the selected Gemini model with search grounding
                $modelToUse = $openaiModel;
                
                if ($systemMessage) {
                    $systemMessage .= "\n\nYou have access to Google Search grounding. When needed, you can search for current information, recent events, and real-time data. Always cite your sources when providing information from the web.";
                } else {
                    $systemMessage = "You are a helpful AI assistant with access to Google Search grounding. When answering questions, you can search for current information, recent events, and real-time data. Always cite your sources when providing information from the web.";
                }
                
                Log::debug('Web search (grounding) enabled for Gemini model', ['model' => $modelToUse]);
                
            } elseif ($this->isGrokModel($openaiModel)) {
                // Grok: Use the selected Grok model with Live Search
                $modelToUse = $openaiModel;
                
                if ($systemMessage) {
                    $systemMessage .= "\n\nYou have access to real-time Live Search capabilities. When needed, you can search for current information, recent events, and real-time data from the web and X (Twitter). Always cite your sources when providing information from the web.";
                } else {
                    $systemMessage = "You are a helpful AI assistant with access to real-time Live Search. When answering questions, you can search for current information, recent events, and real-time data from the web and X (Twitter). Always cite your sources when providing information from the web.";
                }
                
                Log::debug('Live Search enabled for Grok model', ['model' => $modelToUse]);
                
            } else {
                // OpenAI: Switch to the search-enabled model
                $modelToUse = 'gpt-4o-search-preview';
                Log::debug('Web search enabled, switching to OpenAI search model', [
                    'original_model' => $openaiModel,
                    'search_model' => $modelToUse
                ]);
            }
        } else {
            // ✅✅✅ UPDATED: Apply smart model selection with cross-provider support ✅✅✅
            $modelToUse = $autoOptimizeEnabled 
                ? $this->applySmartModelSelection(
                    $openaiModel,      // User's selected model
                    $suggestedModel,   // AI-suggested model based on complexity
                    $complexityScore,  // Complexity score (0-100)
                    $user,             // User object for plan/access control
                    $allowCrossProvider // ✅ NEW: Allow cross-provider switching
                )
                : $openaiModel; // Just use the selected model if auto-optimize is disabled

            Log::info('Model selection decision', [
                'auto_optimize_enabled' => $autoOptimizeEnabled,
                'cross_provider_enabled' => $allowCrossProvider,
                'user_selected' => $openaiModel,
                'user_selected_provider' => $this->getModelProvider($openaiModel),
                'ai_suggested' => $suggestedModel,
                'ai_suggested_provider' => $this->getModelProvider($suggestedModel),
                'final_decision' => $modelToUse,
                'final_provider' => $this->getModelProvider($modelToUse),
                'complexity_score' => $complexityScore,
                'model_changed' => $openaiModel !== $modelToUse,
                'provider_switched' => $this->getModelProvider($openaiModel) !== $this->getModelProvider($modelToUse),
                'reason' => $autoOptimizeEnabled 
                    ? ($allowCrossProvider ? 'Cross-provider optimization applied' : 'Same-provider optimization applied')
                    : 'Auto-optimization disabled - using user selection'
            ]);

            // ✅ Notify user if model or provider was changed
            if ($openaiModel !== $modelToUse) {
                $providerChanged = $this->getModelProvider($openaiModel) !== $this->getModelProvider($modelToUse);
                
                Log::notice('Model changed by smart selection', [
                    'user_id' => $user->id,
                    'from' => $openaiModel,
                    'from_provider' => $this->getModelProvider($openaiModel),
                    'to' => $modelToUse,
                    'to_provider' => $this->getModelProvider($modelToUse),
                    'provider_switched' => $providerChanged,
                    'reason' => $complexityScore < 25 
                        ? 'Query too simple for premium model' 
                        : ($allowCrossProvider ? 'Cross-provider optimization' : 'Same-provider optimization')
                ]);
            }
        }

        // Detect if user needs math assistance
        $needsMath = preg_match('/\b(math|equation|formula|calculate|integral|integrate|integration|derivative|differentiate|differentiation|sum|algebra|geometry|trigonometry|calculus|solve|sqrt|fraction|logarithm|log|ln|sin|cos|tan|exp|factorial|permutation|combination|matrix|vector|polar|cartesian|limit|limits|simplify|expand|factor|polynomial|quadratic|cubic)\b/i', $request->message) || preg_match('/[\^\+\-\*\/\=\(\)\[\]\\\\\{\}%<>!|,:\'\.]/', $request->message);

        // Detect if user needs charts
        $needsVisualization = preg_match('/\b(plot|graph|chart|visualize|show.*graph|draw|diagram|display|illustrate|represent|render|sketch|map|figure|outline|exhibit|demonstrate|view|table)\b/i', $request->message);

        // Add math instructions if needed
        if ($needsMath) {
            $mathInstructions = '

        **IMPORTANT: For mathematical expressions, use LaTeX notation:**
        - Inline math (within text): Wrap in single dollar signs like $x^2 + y^2 = z^2$
        - Display math (on its own line): Wrap in double dollar signs like $$\int_0^\infty e^{-x^2} dx = \frac{\sqrt{\pi}}{2}$$

        Examples:
        - Fractions: $\frac{a}{b}$
        - Exponents: $x^2$ or $e^{-x}$
        - Square roots: $\sqrt{x}$ or $\sqrt[3]{x}$
        - Integrals: $\int_a^b f(x)dx$
        - Summations: $\sum_{i=1}^{n} i$
        - Greek letters: $\alpha$, $\beta$, $\theta$, $\pi$

        Always use this notation for ANY mathematical expression in your response.';

            if ($systemMessage) {
                $systemMessage .= $mathInstructions;
            } else {
                $systemMessage = "You are a helpful AI assistant." . $mathInstructions;
            }
        }

        // Add chart instructions if needed
        if ($needsVisualization) {
            $chartInstructions = '

        **IMPORTANT: For charts/graphs, use this JSON format:**

        You can create MULTIPLE charts in a single response. Wrap EACH chart in its own chart block (three backticks followed by the word chart).

        Example of creating TWO charts:

        ' . '```chart' . '
        {
        "type": "line",
        "title": "First Chart",
        "xLabel": "X Axis",
        "yLabel": "Y Axis",
        "data": {
            "labels": [1, 2, 3],
            "datasets": [{
            "label": "Dataset 1",
            "data": [10, 20, 30],
            "borderColor": "rgb(75, 192, 192)",
            "backgroundColor": "rgba(75, 192, 192, 0.2)"
            }]
        }
        }
        ' . '```' . '

        ' . '```chart' . '
        {
        "type": "bar",
        "title": "Second Chart",
        "xLabel": "Categories",
        "yLabel": "Values",
        "data": {
            "labels": ["A", "B", "C"],
            "datasets": [{
            "label": "Dataset 2",
            "data": [50, 75, 100],
            "backgroundColor": "rgba(255, 99, 132, 0.2)"
            }]
        }
        }
        ' . '```' . '

        Supported chart types: line, bar, pie, doughnut, scatter, radar.
        IMPORTANT: Create as many separate chart blocks as needed - they will ALL render automatically.';

            if ($systemMessage) {
                $systemMessage .= $chartInstructions;
            } else {
                $systemMessage = "You are a helpful AI assistant." . $chartInstructions;
            }
        }

        // Auto-adjust model based on token balance
        $siteSettings = app('siteSettings');
        if ($user->tokens_left <= 0) {
            $modelToUse = $siteSettings->default_model;
            
            if ($user->selected_model !== $siteSettings->default_model) {
                $user->selected_model = $siteSettings->default_model;
                $user->save();
            }
        }

        Log::debug('Model Selection', [
            'openaiModel' => $openaiModel,
            'modelToUse' => $modelToUse,
            'provider' => $this->getModelProvider($modelToUse),
            'isClaude' => $this->isClaudeModel($modelToUse),
            'isGemini' => $this->isGeminiModel($modelToUse),
            'isGrok' => $this->isGrokModel($modelToUse),
            'isOpenAI' => $this->isOpenAIModel($modelToUse)
        ]);

        // Prepare messages
        $messages = [];
        
        if ($systemMessage) {
            $messages[] = ['role' => 'system', 'content' => $systemMessage];
        }

        if ($request->conversation_id) {
            $dbMessages = ChatMessage::where('conversation_id', $request->conversation_id)
                ->orderBy('created_at')
                ->get();

            foreach ($dbMessages as $msg) {
                $messages[] = [
                    'role' => $msg->role,
                    'content' => $msg->content,
                ];
            }
        }

        // Handle file uploads (UNCHANGED - keeping your existing code)
        $fileContentMessage = null;
        $uploadedFilePath = null;

        if ($request->hasFile('pdf') && $request->file('pdf')->isValid()) {
            $file = $request->file('pdf');
            $extension = strtolower($file->getClientOriginalExtension());
            $tempPath = $file->storeAs('temp', uniqid() . '.' . $extension);
            $fullPath = storage_path("app/{$tempPath}");

            if (in_array($extension, ['pdf', 'doc', 'docx'])) {
                $text = '';
                try {
                    if ($extension === 'pdf') {
                        $parser = new PdfParser();
                        $pdf = $parser->parseFile($fullPath);
                        $text = $pdf->getText();
                    } else {
                        $phpWord = WordIOFactory::load($fullPath);
                        $sections = $phpWord->getSections();
                        foreach ($sections as $section) {
                            $elements = $section->getElements();
                            foreach ($elements as $element) {
                                if (method_exists($element, 'getText')) {
                                    $text .= $element->getText() . "\n";
                                } elseif (method_exists($element, 'getElements')) {
                                    foreach ($element->getElements() as $child) {
                                        if (method_exists($child, 'getText')) {
                                            $text .= $child->getText() . "\n";
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $text = substr($text, 0, 8000);

                    // Upload to Azure
                    try {
                        $blobClient = BlobRestProxy::createBlobService(config('filesystems.disks.azure.connection_string'));
                        $containerName = config('filesystems.disks.azure.container');
                        $originalFileName = $file->getClientOriginalName();
                        $azureFileName = 'chattermate-attachments/' . $originalFileName;
                        $fileContent = file_get_contents($fullPath);
                        $blobClient->createBlockBlob($containerName, $azureFileName, $fileContent, new CreateBlockBlobOptions());

                        $uploadedFilePath = [
                            'file_path' => $azureFileName,
                            'file_name' => $file->getClientOriginalName(),
                        ];
                    } catch (\Exception $azureEx) {
                        Log::error('Azure upload error: ' . $azureEx->getMessage());
                    }
                } catch (\Exception $e) {
                    Log::error('File parsing error: ' . $e->getMessage());
                    $text = '[Error parsing uploaded document.]';
                }

                Storage::delete($tempPath);

                $fileContentMessage = [
                    'role' => 'system',
                    'content' => "This is the content of the uploaded document. Refer to it for future questions:\n\n{$text}",
                ];

            } elseif (in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
                try {
                    $blobClient = BlobRestProxy::createBlobService(config('filesystems.disks.azure.connection_string'));
                    $containerName = config('filesystems.disks.azure.container');
                    $originalFileName = $file->getClientOriginalName();
                    $sanitizedFileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '-', $originalFileName);
                    $sanitizedFileName = preg_replace('/-+/', '-', $sanitizedFileName);
                    
                    $imageName = 'chattermate-attachments/' . $sanitizedFileName;
                    $imageContent = file_get_contents($file->getRealPath());
                    $blobClient->createBlockBlob($containerName, $imageName, $imageContent, new CreateBlockBlobOptions());

                    $baseUrl = rtrim(config('filesystems.disks.azure.url'), '/');
                    $publicUrl = "{$baseUrl}/{$containerName}/{$imageName}";

                    $fileContentMessage = [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $request->message ?: "What is in this image?"],
                            ['type' => 'image_url', 'image_url' => ['url' => $publicUrl]],
                        ],
                    ];

                    $uploadedFilePath = [
                        'file_path' => $imageName,
                        'file_name' => $originalFileName,
                    ];

                    Storage::delete($tempPath);
                } catch (\Exception $e) {
                    Log::error('Image upload error: ' . $e->getMessage());
                    Storage::delete($tempPath);
                }
            } else {
                Storage::delete($tempPath);
            }
        }

        $useVision = isset($fileContentMessage['content'][1]['type']) && 
                    $fileContentMessage['content'][1]['type'] === 'image_url';

        if ($fileContentMessage) {
            $messages[] = $fileContentMessage;
            if ($fileContentMessage['role'] === 'system' && $request->message) {
                $messages[] = ['role' => 'user', 'content' => $request->message];
            }
        }

        if (!$fileContentMessage) {
            $messages[] = ['role' => 'user', 'content' => $request->message];
        }

        // Force vision model for images
        if ($useVision && $this->isOpenAIModel($modelToUse)) {
            $modelToUse = 'gpt-4.1-mini';
        }

        // Route to appropriate API
        if ($this->isClaudeModel($modelToUse)) {
            return $this->streamClaudeResponse($request, $messages, $modelToUse, $fileContentMessage, $uploadedFilePath, $expert);
        } elseif ($this->isGeminiModel($modelToUse)) {
            return $this->streamGeminiResponse($request, $messages, $modelToUse, $fileContentMessage, $uploadedFilePath, $expert);
        } elseif ($this->isGrokModel($modelToUse)) {
            return $this->streamGrokResponse($request, $messages, $modelToUse, $fileContentMessage, $uploadedFilePath, $expert);
        } else {
            return $this->streamOpenAIResponse($request, $messages, $modelToUse, $fileContentMessage, $uploadedFilePath, $expert);
        }
    }

    // ✅ NEW: Helper method to get suggested model based on complexity
    private function getSuggestedModelFromComplexity(int $score, string $currentModel, $user): string
    {
        // Determine the provider of the current model
        $currentProvider = $this->getModelProvider($currentModel);
        
        Log::debug('Provider-aware model suggestion starting', [
            'current_model' => $currentModel,
            'current_provider' => $currentProvider,
            'complexity_score' => $score
        ]);
        
        // Get all active AI models from settings with their tiers
        $aiSettings = DB::table('a_i_settings')
            ->where('status', 1)
            ->orderBy('tier')
            ->orderBy('cost_per_m_tokens', 'asc')
            ->get();
        
        // Filter by provider manually since column doesn't exist
        $aiSettings = $aiSettings->filter(function($setting) use ($currentProvider) {
            return $this->getModelProvider($setting->openaimodel) === $currentProvider;
        });
        
        // Group models by tier
        $modelsByTier = $aiSettings->groupBy('tier');
        
        // Determine suggested tier based on complexity score
        $suggestedTier = $this->getTierFromComplexity($score);
        
        Log::debug('Model suggestion process', [
            'complexity_score' => $score,
            'suggested_tier' => $suggestedTier,
            'current_provider' => $currentProvider,
            'user_plan' => $user->plan?->name,
            'user_accessible_models' => $user->aiModels()
        ]);
        
        // Get user's accessible models (filtered by provider)
        $userModels = $this->getUserModelsByProvider($user, $currentProvider);
        
        // If user has no accessible models from this provider, return current model
        if (empty($userModels)) {
            Log::warning('User has no accessible models from provider', [
                'user_id' => $user->id,
                'provider' => $currentProvider
            ]);
            return $currentModel;
        }
        
        // ✅ NEW: Find CHEAPEST model in suggested tier that user has access to
        if (isset($modelsByTier[$suggestedTier])) {
            $cheapestModel = null;
            $lowestCost = PHP_FLOAT_MAX;
            
            foreach ($modelsByTier[$suggestedTier] as $aiSetting) {
                $model = $aiSetting->openaimodel;
                $cost = (float) ($aiSetting->cost_per_m_tokens ?? PHP_FLOAT_MAX);
                
                // Check if user has access to this model AND it's cheaper
                if ($user->hasModelAccess($model) && $cost < $lowestCost) {
                    $cheapestModel = $model;
                    $lowestCost = $cost;
                }
            }
            
            if ($cheapestModel) {
                Log::info('Found cheapest model in suggested tier (same provider)', [
                    'model' => $cheapestModel,
                    'provider' => $currentProvider,
                    'tier' => $suggestedTier,
                    'cost_per_m_tokens' => $lowestCost,
                    'complexity' => $score
                ]);
                return $cheapestModel;
            }
        }
        
        // Try lower tiers if user doesn't have access to suggested tier
        $tierHierarchy = ['basic', 'standard', 'advanced', 'premium'];
        $suggestedTierIndex = array_search($suggestedTier, $tierHierarchy);
        
        // Search downwards from suggested tier
        for ($i = $suggestedTierIndex - 1; $i >= 0; $i--) {
            $lowerTier = $tierHierarchy[$i];
            
            if (isset($modelsByTier[$lowerTier])) {
                $cheapestModel = null;
                $lowestCost = PHP_FLOAT_MAX;
                
                foreach ($modelsByTier[$lowerTier] as $aiSetting) {
                    $model = $aiSetting->openaimodel;
                    $cost = (float) ($aiSetting->cost_per_m_tokens ?? PHP_FLOAT_MAX);
                    
                    if ($user->hasModelAccess($model) && $cost < $lowestCost) {
                        $cheapestModel = $model;
                        $lowestCost = $cost;
                    }
                }
                
                if ($cheapestModel) {
                    Log::info('Falling back to cheapest model in lower tier (same provider)', [
                        'model' => $cheapestModel,
                        'provider' => $currentProvider,
                        'tier' => $lowerTier,
                        'cost_per_m_tokens' => $lowestCost,
                        'original_tier' => $suggestedTier
                    ]);
                    return $cheapestModel;
                }
            }
        }
        
        // Fallback to current model if accessible, otherwise first available model from provider
        if ($user->hasModelAccess($currentModel)) {
            Log::info('Using current model as fallback (provider match)', [
                'model' => $currentModel,
                'provider' => $currentProvider
            ]);
            return $currentModel;
        }
        
        // Last resort: return cheapest accessible model from same provider
        $fallbackModel = $this->getCheapestModelFromList($userModels, $currentProvider);
        Log::warning('Using cheapest accessible model from provider as last resort', [
            'model' => $fallbackModel,
            'provider' => $currentProvider
        ]);
        return $fallbackModel;
    }

    private function getModelProvider(string $modelName): string
    {
        // Try to get from database first
        $modelSettings = \App\Models\AISettings::active()
            ->where('openaimodel', $modelName)
            ->first();
        
        if ($modelSettings && $modelSettings->provider) {
            return $modelSettings->provider;
        }
        
        // Fallback: If not in database, try to guess (for backwards compatibility)
        $modelLower = strtolower($modelName);
        
        if (str_contains($modelLower, 'claude')) {
            return 'claude';
        } elseif (str_contains($modelLower, 'gemini')) {
            return 'gemini';
        } elseif (str_contains($modelLower, 'grok')) {
            return 'grok';
        } else {
            return 'openai'; // Default
        }
    }

    /**
     * Check if model is from specific provider
     */
    private function isClaudeModel($model): bool
    {
        return $this->getModelProvider($model) === 'claude';
    }

    private function isGeminiModel($model): bool
    {
        return $this->getModelProvider($model) === 'gemini';
    }

    private function isGrokModel($model): bool
    {
        return $this->getModelProvider($model) === 'grok';
    }

    private function isOpenAIModel($model): bool
    {
        return $this->getModelProvider($model) === 'openai';
    }

    /**
     * Helper to check if model supports image generation
     * All providers support it except Claude
     */
    private function supportsImageGeneration($model): bool
    {
        $provider = $this->getModelProvider($model);
        return $provider !== 'claude';
    }

    private function getUserModelsByProvider($user, string $provider): array
    {
        $allUserModels = $user->aiModels();
        
        // Filter models by provider using model detection methods
        $providerModels = array_filter($allUserModels, function($model) use ($provider) {
            return $this->getModelProvider($model) === $provider;
        });
        
        // Re-index array
        $providerModels = array_values($providerModels);
        
        Log::debug('Filtered user models by provider', [
            'provider' => $provider,
            'total_models' => count($allUserModels),
            'provider_models' => count($providerModels),
            'models' => $providerModels
        ]);
        
        return $providerModels;
    }

    private function getTierFromComplexity(int $score): string
    {
        if ($score < 30) {
            return 'basic';
        } elseif ($score < 50) {
            return 'standard';
        } elseif ($score < 70) {
            return 'advanced';
        } else {
            return 'premium';
        }
    }

    // ✅ NEW: Helper method to get suggested model from ANY provider (cross-provider selection)
    private function getSuggestedModelFromAnyProvider(int $score, string $currentModel, $user): string
    {
        Log::debug('Cross-provider model suggestion starting', [
            'current_model' => $currentModel,
            'complexity_score' => $score,
            'user_plan' => $user->plan?->name
        ]);
        
        // Get all active AI models from settings with their tiers and costs
        $aiSettings = DB::table('a_i_settings')
            ->where('status', 1)
            ->orderBy('tier')
            ->orderBy('cost_per_m_tokens', 'asc') // ✅ Order by cost (cheapest first)
            ->get();
        
        // Group models by tier (across ALL providers)
        $modelsByTier = $aiSettings->groupBy('tier');
        
        // Determine suggested tier based on complexity score
        $suggestedTier = $this->getTierFromComplexity($score);
        
        Log::debug('Cross-provider model selection process', [
            'complexity_score' => $score,
            'suggested_tier' => $suggestedTier,
            'user_plan' => $user->plan?->name,
            'user_accessible_models' => $user->aiModels()
        ]);
        
        // Get user's accessible models (from ALL providers)
        $userModels = $user->aiModels();
        
        // If user has no accessible models, return current model
        if (empty($userModels)) {
            Log::warning('User has no accessible models', [
                'user_id' => $user->id
            ]);
            return $currentModel;
        }
        
        // ✅ NEW: Find CHEAPEST model in suggested tier that user has access to (ANY provider)
        if (isset($modelsByTier[$suggestedTier])) {
            $cheapestModel = null;
            $lowestCost = PHP_FLOAT_MAX;
            
            foreach ($modelsByTier[$suggestedTier] as $aiSetting) {
                $model = $aiSetting->openaimodel;
                $cost = (float) ($aiSetting->cost_per_m_tokens ?? PHP_FLOAT_MAX);
                
                // Check if user has access to this model AND it's cheaper
                if ($user->hasModelAccess($model) && $cost < $lowestCost) {
                    $cheapestModel = $model;
                    $lowestCost = $cost;
                }
            }
            
            if ($cheapestModel) {
                Log::info('Found cheapest model in suggested tier (cross-provider)', [
                    'model' => $cheapestModel,
                    'provider' => $this->getModelProvider($cheapestModel),
                    'tier' => $suggestedTier,
                    'cost_per_m_tokens' => $lowestCost,
                    'complexity' => $score
                ]);
                return $cheapestModel;
            }
        }
        
        // ✅ UPDATED: Try lower tiers, selecting CHEAPEST in each tier
        $tierHierarchy = ['basic', 'standard', 'advanced', 'premium'];
        $suggestedTierIndex = array_search($suggestedTier, $tierHierarchy);
        
        // Search downwards from suggested tier
        for ($i = $suggestedTierIndex - 1; $i >= 0; $i--) {
            $lowerTier = $tierHierarchy[$i];
            
            if (isset($modelsByTier[$lowerTier])) {
                $cheapestModel = null;
                $lowestCost = PHP_FLOAT_MAX;
                
                foreach ($modelsByTier[$lowerTier] as $aiSetting) {
                    $model = $aiSetting->openaimodel;
                    $cost = (float) ($aiSetting->cost_per_m_tokens ?? PHP_FLOAT_MAX);
                    
                    if ($user->hasModelAccess($model) && $cost < $lowestCost) {
                        $cheapestModel = $model;
                        $lowestCost = $cost;
                    }
                }
                
                if ($cheapestModel) {
                    Log::info('Falling back to cheapest model in lower tier (cross-provider)', [
                        'model' => $cheapestModel,
                        'provider' => $this->getModelProvider($cheapestModel),
                        'tier' => $lowerTier,
                        'cost_per_m_tokens' => $lowestCost,
                        'original_tier' => $suggestedTier
                    ]);
                    return $cheapestModel;
                }
            }
        }
        
        // Fallback to current model if accessible, otherwise cheapest available model
        if ($user->hasModelAccess($currentModel)) {
            Log::info('Using current model as fallback (cross-provider)', [
                'model' => $currentModel,
                'provider' => $this->getModelProvider($currentModel)
            ]);
            return $currentModel;
        }
        
        // Last resort: return cheapest accessible model from any provider
        $fallbackModel = $this->getCheapestModelFromList($userModels);
        Log::warning('Using cheapest accessible model as last resort (cross-provider)', [
            'model' => $fallbackModel,
            'provider' => $this->getModelProvider($fallbackModel)
        ]);
        return $fallbackModel;
    }

    // ✅ UPDATED: Smart model selection with cross-provider support
    private function applySmartModelSelection(
        string $userSelectedModel, 
        string $suggestedModel, 
        int $complexityScore,
        $user,
        bool $allowCrossProvider = false  // ✅ NEW parameter
    ): string {
        // ⛔ CRITICAL: Check if user has access to their selected model
        if (!$user->hasModelAccess($userSelectedModel)) {
            Log::warning('User selected model they don\'t have access to', [
                'user_id' => $user->id,
                'plan' => $user->plan?->name,
                'selected_model' => $userSelectedModel,
                'available_models' => $user->aiModels()
            ]);
            
            // Return suggested model (which is already validated for user access)
            return $suggestedModel;
        }
        
        // Get model tier information from ai_settings
        $selectedModelTier = $this->getModelTier($userSelectedModel);
        $suggestedModelTier = $this->getModelTier($suggestedModel);
        $selectedProvider = $this->getModelProvider($userSelectedModel);
        $suggestedProvider = $this->getModelProvider($suggestedModel);
        
        Log::debug('Model selection comparison', [
            'selected' => [
                'model' => $userSelectedModel,
                'tier' => $selectedModelTier,
                'provider' => $selectedProvider,
                'tier_level' => $this->getTierLevel($selectedModelTier)
            ],
            'suggested' => [
                'model' => $suggestedModel,
                'tier' => $suggestedModelTier,
                'provider' => $suggestedProvider,
                'tier_level' => $this->getTierLevel($suggestedModelTier)
            ],
            'complexity' => $complexityScore,
            'allow_cross_provider' => $allowCrossProvider
        ]);
        
        // ✅ Strategy 1: Prevent over-selection (user picks premium for simple task)
        // This saves costs and improves efficiency
        if ($complexityScore < 25 && $selectedModelTier === 'premium') {
            Log::info('Downgrading from premium model for very simple query', [
                'from' => $userSelectedModel,
                'from_tier' => $selectedModelTier,
                'from_provider' => $selectedProvider,
                'to' => $suggestedModel,
                'to_tier' => $suggestedModelTier,
                'to_provider' => $suggestedProvider,
                'complexity' => $complexityScore,
                'cross_provider' => $allowCrossProvider,
                'reason' => 'Over-qualification - simple query doesn\'t need premium model'
            ]);
            return $suggestedModel;
        }
        
        // ✅ Strategy 2: Auto-optimize for free/basic tier users or when enabled
        // This helps manage costs for users with limited plans
        if ($this->shouldAutoOptimize($user)) {
            // Only downgrade, never upgrade (respect plan limits)
            $tierHierarchy = ['basic' => 0, 'standard' => 1, 'advanced' => 2, 'premium' => 3];
            $selectedTierLevel = $tierHierarchy[$selectedModelTier] ?? 0;
            $suggestedTierLevel = $tierHierarchy[$suggestedModelTier] ?? 0;
            
            if ($suggestedTierLevel < $selectedTierLevel) {
                Log::info('Auto-optimizing model for cost savings', [
                    'from' => $userSelectedModel,
                    'from_provider' => $selectedProvider,
                    'to' => $suggestedModel,
                    'to_provider' => $suggestedProvider,
                    'complexity' => $complexityScore,
                    'plan' => $user->plan?->name ?? 'none',
                    'cross_provider' => $allowCrossProvider,
                    'reason' => 'Auto-optimization enabled'
                ]);
                return $suggestedModel;
            }
        }
        
        // ✅ Strategy 3: Warn if complexity suggests higher tier but user doesn't have access
        // This helps identify when users might need to upgrade their plan
        $selectedTierLevel = $this->getTierLevel($selectedModelTier);
        $suggestedTierLevel = $this->getTierLevel($suggestedModelTier);
        
        if ($complexityScore >= 70 && $suggestedTierLevel > $selectedTierLevel) {
            Log::warning('Complex query but user lacks access to optimal tier', [
                'selected_model' => $userSelectedModel,
                'selected_tier' => $selectedModelTier,
                'selected_provider' => $selectedProvider,
                'suggested_model' => $suggestedModel,
                'suggested_tier' => $suggestedModelTier,
                'suggested_provider' => $suggestedProvider,
                'complexity' => $complexityScore,
                'plan' => $user->plan?->name,
                'available_models' => $user->aiModels(),
                'cross_provider' => $allowCrossProvider,
                'recommendation' => 'Consider upgrading plan for better results'
            ]);
        }
        
        // ✅ Strategy 4: Apply auto-selection (method only called when enabled)
        if ($suggestedModel && $user->hasModelAccess($suggestedModel)) {
            Log::info('Auto-optimized model selection applied', [
                'user_id' => $user->id,
                'from' => $userSelectedModel,
                'from_tier' => $selectedModelTier,
                'from_provider' => $selectedProvider,
                'to' => $suggestedModel,
                'to_tier' => $suggestedModelTier,
                'to_provider' => $suggestedProvider,
                'complexity_score' => $complexityScore,
                'plan' => $user->plan?->name ?? 'none',
                'cross_provider_enabled' => $allowCrossProvider,
                'provider_switched' => $selectedProvider !== $suggestedProvider,
                'reason' => 'Auto-optimization enabled via checkbox'
            ]);
            return $suggestedModel;
        }

        // ✅ Default: Respect user's selected model
        Log::info('Using user-selected model', [
            'user_id' => $user->id,
            'model' => $userSelectedModel,
            'tier' => $selectedModelTier,
            'provider' => $selectedProvider,
            'reason' => 'Fallback - using user selection'
        ]);
        return $userSelectedModel;
    }

    private function getModelTier(string $modelName): string
    {
        $aiSetting = DB::table('a_i_settings')
            ->where('openaimodel', $modelName)
            ->where('status', 1)
            ->first();
        
        return $aiSetting?->tier ?? 'basic';
    }
   
    private function getTierLevel(string $tier): int
    {
        $tierLevels = [
            'basic' => 0,
            'standard' => 1,
            'advanced' => 2,
            'premium' => 3
        ];
        
        return $tierLevels[$tier] ?? 0;
    }

    private function shouldAutoOptimize($user): bool
    {
        // Auto-optimize for:
        // 1. Users without active subscription
        if (!$user->hasActiveSubscription()) {
            Log::debug('Auto-optimize: No active subscription');
            return true;
        }
        
        // 2. Free or basic tier users
        $planName = strtolower($user->plan?->name ?? 'free');
        if (str_contains($planName, 'free') || str_contains($planName, 'basic')) {
            Log::debug('Auto-optimize: Free or basic plan', ['plan' => $planName]);
            return true;
        }
        
        // 3. Users who explicitly enabled auto-optimize
        $autoOptimizeEnabled = $user->auto_optimize_model ?? false;
        if ($autoOptimizeEnabled) {
            Log::debug('Auto-optimize: User preference enabled');
            return true;
        }
        
        // 4. Users with low token balance (if applicable)
        if (isset($user->tokens_left)) {
            $lowTokens = $user->tokens_left < 1000;
            if ($lowTokens) {
                Log::debug('Auto-optimize: Low token balance', ['tokens' => $user->tokens_left]);
                return true;
            }
        }
        
        return false;
    }
   
    // ✅ NEW: Helper method to find cheapest model from a list of model names
    private function getCheapestModelFromList(array $modelNames, ?string $provider = null): string
    {
        if (empty($modelNames)) {
            return '';
        }
        
        $query = DB::table('a_i_settings')
            ->whereIn('openaimodel', $modelNames)
            ->where('status', 1);
        
        // Optionally filter by provider
        if ($provider) {
            // Filter by provider after fetching
            $models = $query->get()->filter(function($setting) use ($provider) {
                return $this->getModelProvider($setting->openaimodel) === $provider;
            });
        } else {
            $models = $query->get();
        }
        
        if ($models->isEmpty()) {
            return $modelNames[0]; // Fallback to first model
        }
        
        // Find the model with lowest cost_per_m_tokens
        $cheapest = $models->sortBy('cost_per_m_tokens')->first();
        
        Log::debug('Selected cheapest model from list', [
            'models_considered' => $modelNames,
            'provider' => $provider ?? 'any',
            'selected' => $cheapest->openaimodel,
            'cost_per_m_tokens' => $cheapest->cost_per_m_tokens
        ]);
        
        return $cheapest->openaimodel;
    }
    /**
     * Get cheaper alternative model within user's accessible models
     * Used for downgrading when needed
     */
    private function getCheaperAlternative(string $model, $user): string
    {
        $currentTier = $this->getModelTier($model);
        $currentTierLevel = $this->getTierLevel($currentTier);
        $currentProvider = $this->getModelProvider($model);
        
        // Get user's accessible models from same provider only
        $userModels = $this->getUserModelsByProvider($user, $currentProvider);
        
        // Get AI settings for user's accessible models (same provider)
        $accessibleModels = DB::table('a_i_settings')
            ->whereIn('openaimodel', $userModels)
            ->where('status', 1)
            ->orderBy('tier')
            ->orderBy('cost_per_m_tokens', 'asc') // ✅ Order by cost
            ->get();
        
        // Filter by provider
        $accessibleModels = $accessibleModels->filter(function($setting) use ($currentProvider) {
            return $this->getModelProvider($setting->openaimodel) === $currentProvider;
        });
        
        // ✅ Find the CHEAPEST model with lower tier level than current
        $cheapestAlternative = null;
        $lowestCost = PHP_FLOAT_MAX;
        
        foreach ($accessibleModels as $aiSetting) {
            $modelTierLevel = $this->getTierLevel($aiSetting->tier);
            $cost = (float) ($aiSetting->cost_per_m_tokens ?? PHP_FLOAT_MAX);
            
            if ($modelTierLevel < $currentTierLevel && $cost < $lowestCost) {
                $cheapestAlternative = $aiSetting;
                $lowestCost = $cost;
            }
        }
        
        if ($cheapestAlternative) {
            Log::info('Found cheapest alternative (same provider)', [
                'from' => $model,
                'from_tier' => $currentTier,
                'to' => $cheapestAlternative->openaimodel,
                'to_tier' => $cheapestAlternative->tier,
                'cost_per_m_tokens' => $lowestCost,
                'provider' => $currentProvider
            ]);
            return $cheapestAlternative->openaimodel;
        }
        
        // Fallback to cheapest accessible model from provider or current model
        $fallback = $this->getCheapestModelFromList($userModels, $currentProvider) ?: $model;
        Log::debug('No cheaper alternative found (same provider), using cheapest fallback', [
            'model' => $fallback,
            'provider' => $currentProvider
        ]);
        return $fallback;
    }













    // Update the streamGrokResponse method
    private function streamGrokResponse($request, $messages, $modelToUse, $fileContentMessage, $uploadedFilePath, $expert)
    {
        Log::debug('Streaming Grok response', ['model' => $modelToUse]);
        
        $apiKey = config('services.xai.api_key');
        $useWebSearch = $request->boolean('web_search');
        
        // Convert messages to OpenAI format (Grok is OpenAI-compatible)
        $payload = [
            'model' => $modelToUse,
            'messages' => $messages,
            'stream' => true,
            'temperature' => 0.7,
            'max_tokens' => 4096,
        ];
        
        // ✅ CORRECTED: Use the actual Grok API search parameters format
        if ($useWebSearch) {
                // Don't add search_parameters at all - let Grok decide based on content
                
                // Just enhance the message to indicate need for current data
                $currentDate = date('Y-m-d');
                $lastMessage = end($messages);
                if ($lastMessage && $lastMessage['role'] === 'user') {
                    $originalContent = $lastMessage['content'];
                    
                    // Make it clear we need current information
                    $messages[count($messages) - 1]['content'] = 
                        "Current date: {$currentDate}. " . 
                        "Please search for and use only the most recent, up-to-date information when answering: " . 
                        $originalContent;
                }
                $payload['messages'] = $messages;
            }
        
        Log::debug('Grok API Payload', ['payload' => $payload, 'use_search' => $useWebSearch]);
        
        return response()->stream(function () use ($payload, $apiKey, $request, $fileContentMessage, $uploadedFilePath, $messages, $expert, $modelToUse, $useWebSearch) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            $fullContent = '';
            $chunkCount = 0;
            $buffer = '';
            $searchUsed = false;
            $sourcesUsed = 0;
            $searchQueries = [];
            $citations = [];

            try {
                $ch = curl_init('https://api.x.ai/v1/chat/completions');
                
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $apiKey,
                    ],
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$fullContent, &$chunkCount, &$buffer, &$searchUsed, &$sourcesUsed, &$searchQueries, &$citations, $useWebSearch) {
                        $buffer .= $data;
                        $lines = explode("\n", $buffer);
                        
                        // Keep the last incomplete line in the buffer
                        $buffer = array_pop($lines);
                        
                        foreach ($lines as $line) {
                            $line = trim($line);
                            
                            if (empty($line)) {
                                continue;
                            }
                            
                            if (strpos($line, 'data: ') === 0) {
                                $line = substr($line, 6);
                            }
                            
                            if ($line === '[DONE]') {
                                continue;
                            }
                            
                            try {
                                $decoded = json_decode($line, true);
                                
                                if (json_last_error() !== JSON_ERROR_NONE) {
                                    Log::warning('Grok JSON parse error: ' . json_last_error_msg() . ' | Line: ' . $line);
                                    continue;
                                }
                                
                                // ✅ IMPROVED: Better search detection and notification
                                // Send search start notification if we detect search activity
                                if ($useWebSearch && !$searchUsed && isset($decoded['choices'][0]['delta']['tool_calls'])) {
                                    $searchUsed = true;
                                    Log::info('Grok Live Search initiated - tool calls detected');
                                    
                                    // Send search notification to frontend
                                    echo "data: " . json_encode([
                                        'search_info' => [
                                            'message' => 'Searching current data...',
                                            'status' => 'searching'
                                        ]
                                    ]) . "\n\n";
                                    ob_flush();
                                    flush();
                                }
                                
                                // Handle regular content streaming
                                if (isset($decoded['choices'][0]['delta']['content'])) {
                                    $content = $decoded['choices'][0]['delta']['content'];
                                    $fullContent .= $content;
                                    $chunkCount++;
                                    
                                    // ✅ IMPROVED: More comprehensive search detection
                                    if ($useWebSearch && !$searchUsed) {
                                        $searchIndicators = [
                                            'searching',
                                            'according to',
                                            'based on current',
                                            'latest information',
                                            'recent data',
                                            'real-time',
                                            'up-to-date',
                                            'current information',
                                            'live data',
                                            'today\'s',
                                            date('Y') // Current year
                                        ];
                                        
                                        foreach ($searchIndicators as $indicator) {
                                            if (stripos($content, $indicator) !== false) {
                                                $searchUsed = true;
                                                Log::info("Grok Live Search detected via content indicator: {$indicator}");
                                                break;
                                            }
                                        }
                                    }
                                    
                                    echo "data: " . json_encode(['content' => $content]) . "\n\n";
                                    ob_flush();
                                    flush();
                                }
                                
                                // ✅ IMPROVED: Handle search metadata and usage
                                if (isset($decoded['usage'])) {
                                    // Check for live search queries
                                    if (isset($decoded['usage']['live_search_queries']) && $decoded['usage']['live_search_queries'] > 0) {
                                        $searchUsed = true;
                                        $sourcesUsed = $decoded['usage']['live_search_queries'];
                                        Log::info('Grok used ' . $sourcesUsed . ' live search queries');
                                    }
                                    
                                    // Check for search sources
                                    if (isset($decoded['usage']['search_sources_used']) && $decoded['usage']['search_sources_used'] > 0) {
                                        $searchUsed = true;
                                        $sourcesUsed = max($sourcesUsed, $decoded['usage']['search_sources_used']);
                                        Log::info('Grok used ' . $sourcesUsed . ' search sources');
                                    }
                                }
                                
                                // ✅ NEW: Handle search results and citations
                                if (isset($decoded['search_results']) && is_array($decoded['search_results'])) {
                                    $searchUsed = true;
                                    foreach ($decoded['search_results'] as $result) {
                                        if (isset($result['url'])) {
                                            $citations[] = $result['url'];
                                        }
                                    }
                                    Log::info('Grok search results received', ['count' => count($decoded['search_results'])]);
                                }
                                
                                // ✅ NEW: Handle citations
                                if (isset($decoded['citations']) && is_array($decoded['citations'])) {
                                    $citations = array_merge($citations, $decoded['citations']);
                                    Log::info('Grok citations received', ['count' => count($decoded['citations'])]);
                                }
                                
                                // ✅ NEW: Handle search queries
                                if (isset($decoded['search_queries']) && is_array($decoded['search_queries'])) {
                                    $searchQueries = array_merge($searchQueries, $decoded['search_queries']);
                                    $searchUsed = true;
                                    Log::info('Grok search queries', ['queries' => $decoded['search_queries']]);
                                }
                                
                                // Check for finish reason
                                if (isset($decoded['choices'][0]['finish_reason'])) {
                                    $finishReason = $decoded['choices'][0]['finish_reason'];
                                    if ($finishReason !== 'stop') {
                                        Log::warning('Grok unusual finish reason: ' . $finishReason);
                                    }
                                }
                                
                            } catch (\Exception $e) {
                                Log::error('Grok stream parse error: ' . $e->getMessage() . ' | Line: ' . $line);
                            }
                        }
                        
                        return strlen($data);
                    }
                ]);

                $result = curl_exec($ch);
                
                if (curl_errno($ch)) {
                    $error = curl_error($ch);
                    Log::error('Grok API curl error: ' . $error);
                    echo "data: " . json_encode(['content' => '', 'error' => 'API connection error: ' . $error]) . "\n\n";
                    ob_flush();
                    flush();
                }
                
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode !== 200) {
                    Log::error('Grok API HTTP error: ' . $httpCode);
                    echo "data: " . json_encode(['content' => '', 'error' => 'API returned error code: ' . $httpCode]) . "\n\n";
                    ob_flush();
                    flush();
                }
                
                curl_close($ch);

            } catch (\Exception $e) {
                Log::error('Grok streaming error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                echo "data: " . json_encode(['content' => '', 'error' => $e->getMessage()]) . "\n\n";
                ob_flush();
                flush();
            }

            // ✅ IMPROVED: Add citations to response if available
            if (!empty($citations) && $useWebSearch && $searchUsed) {
                $citationText = "\n\n**Sources:**\n";
                $uniqueCitations = array_unique($citations);
                foreach ($uniqueCitations as $index => $citation) {
                    $citationText .= ($index + 1) . ". [{$citation}]({$citation})\n";
                }
                $fullContent .= $citationText;
                
                // Send citations as final content
                echo "data: " . json_encode(['content' => $citationText]) . "\n\n";
                ob_flush();
                flush();
            }

            // Save to database
            if (Auth::check() && $request->conversation_id) {
                $this->saveConversation($request, $fullContent, $uploadedFilePath, $fileContentMessage, $expert);
            }

            Log::info('Grok stream completed', [
                'total_chunks' => $chunkCount,
                'content_length' => strlen($fullContent),
                'search_used' => $searchUsed,
                'sources_used' => $sourcesUsed,
                'citations_found' => count($citations),
                'search_queries' => $searchQueries
            ]);
            
            echo "data: [DONE]\n\n";
            ob_flush();
            flush();

            // ✅ IMPROVED: Better cost calculation for search
            try {
                $totalTokenCount = 0;
                foreach ($messages as $msg) {
                    $encodedMessage = json_encode($msg);
                    $totalTokenCount += approximateTokenCount($encodedMessage);
                }
                $totalTokenCount += approximateTokenCount($fullContent);

                // Calculate search cost based on actual usage
                $searchCost = 0;
                if ($useWebSearch && $searchUsed && $sourcesUsed > 0) {
                    // Grok charges approximately $25 per 1,000 sources
                    $searchCost = max(1, ceil($sourcesUsed * 0.025)); // Minimum 1 credit
                    Log::info("Grok search cost: {$sourcesUsed} sources = {$searchCost} credits");
                }

                if (Auth::check()) {
                    deductUserTokensAndCredits($totalTokenCount, $searchCost, $modelToUse);
                    Log::info("Deducted {$totalTokenCount} tokens and {$searchCost} search credits for Grok");
                }
            } catch (\Exception $e) {
                Log::error('Error deducting tokens: ' . $e->getMessage());
            }

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    // Add this method to stream Gemini responses
    // In your ChatController.php, update the streamGeminiResponse method

    private function streamGeminiResponse($request, $messages, $modelToUse, $fileContentMessage, $uploadedFilePath, $expert)
    {
        Log::debug('Streaming Gemini response', ['model' => $modelToUse]);
        
        // Convert messages to Gemini format
        $geminiMessages = $this->convertMessagesToGeminiFormat($messages);
        
        $apiKey = config('services.gemini.api_key');
        $useWebSearch = $request->boolean('web_search');
        
        // Gemini API endpoint for streaming
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelToUse}:streamGenerateContent?alt=sse&key={$apiKey}";
        
        $payload = [
            'contents' => $geminiMessages['contents'],
            'generationConfig' => [
                'temperature' => 1.0, // Google recommends 1.0 for grounding
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 8192,
            ],
        ];
        
        // Add system instruction if present
        if ($geminiMessages['systemInstruction']) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $geminiMessages['systemInstruction']]
                ]
            ];
        }
        
        // ✅ UPDATED: Use new google_search tool format for newer models
        if ($useWebSearch) {
            // For Gemini 2.0+ models, use the new format
            if (str_contains($modelToUse, '2.') || str_contains($modelToUse, 'flash-002')) {
                $payload['tools'] = [
                    [
                        'google_search' => (object)[] // Empty object for default settings
                    ]
                ];
            } else {
                // For older Gemini 1.5 models, use legacy format
                $payload['tools'] = [
                    [
                        'googleSearchRetrieval' => [
                            'dynamicRetrievalConfig' => [
                                'mode' => 'MODE_DYNAMIC',
                                'dynamicThreshold' => 0.7
                            ]
                        ]
                    ]
                ];
            }
            
            Log::debug('Gemini web search enabled', [
                'model' => $modelToUse,
                'tool_format' => str_contains($modelToUse, '2.') ? 'google_search' : 'googleSearchRetrieval'
            ]);
        }
        
        Log::debug('Gemini API Payload', ['payload' => $payload]);
        
        return response()->stream(function () use ($payload, $endpoint, $request, $fileContentMessage, $uploadedFilePath, $messages, $expert, $modelToUse, $useWebSearch) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            $fullContent = '';
            $chunkCount = 0;
            $buffer = '';
            $groundingMetadata = null;

            try {
                $ch = curl_init($endpoint);
                
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                    ],
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$fullContent, &$chunkCount, &$buffer, &$groundingMetadata, $useWebSearch) {
                        $buffer .= $data;
                        $lines = explode("\n", $buffer);
                        
                        // Keep the last incomplete line in the buffer
                        $buffer = array_pop($lines);
                        
                        foreach ($lines as $line) {
                            $line = trim($line);
                            
                            // Skip empty lines
                            if (empty($line)) {
                                continue;
                            }
                            
                            // Remove "data: " prefix if present (SSE format)
                            if (strpos($line, 'data: ') === 0) {
                                $line = substr($line, 6);
                            }
                            
                            // Skip [DONE] marker
                            if ($line === '[DONE]') {
                                continue;
                            }
                            
                            try {
                                $decoded = json_decode($line, true);
                                
                                if (json_last_error() !== JSON_ERROR_NONE) {
                                    Log::warning('Gemini JSON parse error: ' . json_last_error_msg() . ' | Line: ' . $line);
                                    continue;
                                }
                                
                                // Extract text from Gemini response
                                if (isset($decoded['candidates'][0]['content']['parts'])) {
                                    foreach ($decoded['candidates'][0]['content']['parts'] as $part) {
                                        if (isset($part['text'])) {
                                            $content = $part['text'];
                                            $fullContent .= $content;
                                            $chunkCount++;
                                            
                                            echo "data: " . json_encode(['content' => $content]) . "\n\n";
                                            ob_flush();
                                            flush();
                                        }
                                    }
                                }
                                
                                // ✅ NEW: Handle grounding metadata (web search results)
                                if (isset($decoded['candidates'][0]['groundingMetadata']) && $useWebSearch) {
                                    $groundingMetadata = $decoded['candidates'][0]['groundingMetadata'];
                                    
                                    // Log search queries for debugging
                                    if (isset($groundingMetadata['webSearchQueries'])) {
                                        Log::info('Gemini used web search', [
                                            'queries' => $groundingMetadata['webSearchQueries']
                                        ]);
                                        
                                        // Optional: Show search indicator to user
                                        echo "data: " . json_encode([
                                            'content' => '', 
                                            'search_info' => [
                                                'queries' => $groundingMetadata['webSearchQueries'],
                                                'message' => '🔍 Searched: ' . implode(', ', $groundingMetadata['webSearchQueries'])
                                            ]
                                        ]) . "\n\n";
                                        ob_flush();
                                        flush();
                                    }
                                    
                                    // Store grounding chunks for citations
                                    if (isset($groundingMetadata['groundingChunks'])) {
                                        Log::debug('Gemini grounding sources', [
                                            'sources' => array_map(function($chunk) {
                                                return $chunk['web']['uri'] ?? 'Unknown';
                                            }, $groundingMetadata['groundingChunks'])
                                        ]);
                                    }
                                }
                                
                                // Check for finish reason
                                if (isset($decoded['candidates'][0]['finishReason'])) {
                                    $finishReason = $decoded['candidates'][0]['finishReason'];
                                    if ($finishReason !== 'STOP') {
                                        Log::warning('Gemini unusual finish reason: ' . $finishReason);
                                    }
                                }
                                
                            } catch (\Exception $e) {
                                Log::error('Gemini stream parse error: ' . $e->getMessage() . ' | Line: ' . $line);
                            }
                        }
                        
                        return strlen($data);
                    }
                ]);

                $result = curl_exec($ch);
                
                if (curl_errno($ch)) {
                    $error = curl_error($ch);
                    Log::error('Gemini API curl error: ' . $error);
                    echo "data: " . json_encode(['content' => '', 'error' => 'API connection error: ' . $error]) . "\n\n";
                    ob_flush();
                    flush();
                }
                
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode !== 200) {
                    Log::error('Gemini API HTTP error: ' . $httpCode);
                    echo "data: " . json_encode(['content' => '', 'error' => 'API returned error code: ' . $httpCode]) . "\n\n";
                    ob_flush();
                    flush();
                }
                
                curl_close($ch);

            } catch (\Exception $e) {
                Log::error('Gemini streaming error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                echo "data: " . json_encode(['content' => '', 'error' => $e->getMessage()]) . "\n\n";
                ob_flush();
                flush();
            }

            // ✅ NEW: Add citations to content if grounding was used
            if ($groundingMetadata && isset($groundingMetadata['groundingChunks']) && $useWebSearch) {
                $citations = "\n\n**Sources:**\n";
                foreach ($groundingMetadata['groundingChunks'] as $index => $chunk) {
                    if (isset($chunk['web'])) {
                        $title = $chunk['web']['title'] ?? 'Source';
                        $uri = $chunk['web']['uri'] ?? '#';
                        $citations .= "- [{$title}]({$uri})\n";
                    }
                }
                $fullContent .= $citations;
                
                // Send citations as final content
                echo "data: " . json_encode(['content' => $citations]) . "\n\n";
                ob_flush();
                flush();
            }

            // Save to database
            if (Auth::check() && $request->conversation_id) {
                $this->saveConversation($request, $fullContent, $uploadedFilePath, $fileContentMessage, $expert);
            }

            Log::info('Gemini stream completed', [
                'total_chunks' => $chunkCount,
                'content_length' => strlen($fullContent),
                'used_search' => !is_null($groundingMetadata)
            ]);
            
            echo "data: [DONE]\n\n";
            ob_flush();
            flush();

            // Deduct tokens - Add extra cost for search if used
            try {
                $totalTokenCount = 0;
                foreach ($messages as $msg) {
                    $encodedMessage = json_encode($msg);
                    $totalTokenCount += approximateTokenCount($encodedMessage);
                }
                $totalTokenCount += approximateTokenCount($fullContent);

                // Add additional tokens/credits for search usage (since Google charges $35/1000 queries)
                $searchCost = 0;
                if ($useWebSearch && $groundingMetadata) {
                    $searchCost = 50; // Adjust based on your pricing model
                }

                if (Auth::check()) {
                    deductUserTokensAndCredits($totalTokenCount, $searchCost, $modelToUse);
                    Log::info("Deducted {$totalTokenCount} tokens and {$searchCost} search credits for Gemini");
                }
            } catch (\Exception $e) {
                Log::error('Error deducting tokens: ' . $e->getMessage());
            }

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    // Convert messages format for Gemini API
    private function convertMessagesToGeminiFormat($messages)
    {
        $systemInstruction = null;
        $geminiContents = [];
        
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                // Gemini uses systemInstruction separately
                $systemInstruction = $msg['content'];
                continue;
            }
            
            $role = $msg['role'] === 'assistant' ? 'model' : 'user';
            
            // Handle multimodal content (images)
            if (isset($msg['content']) && is_array($msg['content'])) {
                $parts = [];
                
                foreach ($msg['content'] as $content) {
                    if ($content['type'] === 'text') {
                        $parts[] = [
                            'text' => $content['text']
                        ];
                    } elseif ($content['type'] === 'image_url') {
                        // Gemini requires base64 encoded images
                        $imageUrl = $content['image_url']['url'];
                        
                        try {
                            $imageData = file_get_contents($imageUrl);
                            $base64 = base64_encode($imageData);
                            $mimeType = $this->getImageMimeType($imageUrl);
                            
                            $parts[] = [
                                'inlineData' => [
                                    'mimeType' => $mimeType,
                                    'data' => $base64
                                ]
                            ];
                        } catch (\Exception $e) {
                            Log::error('Error converting image for Gemini: ' . $e->getMessage());
                        }
                    }
                }
                
                $geminiContents[] = [
                    'role' => $role,
                    'parts' => $parts
                ];
            } else {
                // Simple text message
                $geminiContents[] = [
                    'role' => $role,
                    'parts' => [
                        ['text' => $msg['content']]
                    ]
                ];
            }
        }
        
        return [
            'systemInstruction' => $systemInstruction,
            'contents' => $geminiContents
        ];
    }

    private function streamOpenAIResponse($request, $messages, $modelToUse, $fileContentMessage, $uploadedFilePath, $expert)
    {
        Log::debug('Streaming OpenAI response', ['model' => $modelToUse]);
        
        $response = OpenAI::chat()->createStreamed([
            'model' => $modelToUse,
            'messages' => $messages,
            'stream' => true,
        ]);

        return $this->createStreamedResponse($response, $request, $fileContentMessage, $uploadedFilePath, $messages, $expert, $modelToUse, 'openai');
    }

    private function streamClaudeResponse($request, $messages, $modelToUse, $fileContentMessage, $uploadedFilePath, $expert)
    {
        Log::debug('Streaming Claude response', ['model' => $modelToUse]);
        
        $claudeData = $this->convertMessagesToClaudeFormat($messages);
        
        $payload = [
            'model' => $modelToUse,
            'messages' => $claudeData['messages'],
            'max_tokens' => 4096,
            'stream' => true,
        ];

        if ($claudeData['system']) {
            $payload['system'] = $claudeData['system'];
        }

        // ✅ ADD WEB SEARCH TOOL IF ENABLED
        $useWebSearch = $request->boolean('web_search');
        if ($useWebSearch) {
            $payload['tools'] = [
                [
                    'type' => 'web_search_20250305',
                    'name' => 'web_search',
                    'max_uses' => 5  // Claude can search up to 5 times
                ]
            ];
        }

        Log::debug('Claude API Payload', ['payload' => $payload]);

        $apiKey = config('services.anthropic.api_key');
        
        return response()->stream(function () use ($payload, $apiKey, $request, $fileContentMessage, $uploadedFilePath, $messages, $expert, $modelToUse) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            $fullContent = '';
            $chunkCount = 0;

            try {
                $ch = curl_init('https://api.anthropic.com/v1/messages');
                
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'x-api-key: ' . $apiKey,
                        'anthropic-version: 2023-06-01',
                    ],
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$fullContent, &$chunkCount) {
                        $lines = explode("\n", $data);
                        
                        foreach ($lines as $line) {
                            if (strpos($line, 'data: ') === 0) {
                                $json = substr($line, 6);
                                
                                if (trim($json) === '[DONE]') {
                                    continue;
                                }
                                
                                try {
                                    $decoded = json_decode($json, true);
                                    
                                    if (isset($decoded['type'])) {
                                        // Handle text content streaming
                                        if ($decoded['type'] === 'content_block_delta') {
                                            $content = $decoded['delta']['text'] ?? '';
                                            $fullContent .= $content;
                                            $chunkCount++;
                                            
                                            echo "data: " . json_encode(['content' => $content]) . "\n\n";
                                            ob_flush();
                                            flush();
                                        }
                                        
                                        // ✅ Show when Claude is searching (optional)
                                        if ($decoded['type'] === 'content_block_start' && 
                                            isset($decoded['content_block']['type']) && 
                                            $decoded['content_block']['type'] === 'tool_use') {
                                            
                                            Log::info('Claude is using web search');
                                            
                                            // Optionally show a search indicator to user
                                            echo "data: " . json_encode(['content' => ' 🔍 ']) . "\n\n";
                                            ob_flush();
                                            flush();
                                        }
                                    }
                                } catch (\Exception $e) {
                                    Log::error('Claude stream parse error: ' . $e->getMessage());
                                }
                            }
                        }
                        
                        return strlen($data);
                    }
                ]);

                curl_exec($ch);
                
                if (curl_errno($ch)) {
                    Log::error('Claude API error: ' . curl_error($ch));
                    echo "data: " . json_encode(['content' => '', 'error' => 'API error']) . "\n\n";
                }
                
                curl_close($ch);

            } catch (\Exception $e) {
                Log::error('Claude streaming error: ' . $e->getMessage());
                echo "data: " . json_encode(['content' => '', 'error' => $e->getMessage()]) . "\n\n";
            }

            // Save to database
            if (Auth::check() && $request->conversation_id) {
                $this->saveConversation($request, $fullContent, $uploadedFilePath, $fileContentMessage, $expert);
            }

            Log::info('Claude stream completed', ['total_chunks' => $chunkCount]);
            echo "data: [DONE]\n\n";
            ob_flush();
            flush();

            // Deduct tokens
            try {
                $totalTokenCount = 0;
                foreach ($messages as $msg) {
                    $encodedMessage = json_encode($msg);
                    $totalTokenCount += approximateTokenCount($encodedMessage);
                }
                $totalTokenCount += approximateTokenCount($fullContent);

                if (Auth::check()) {
                    deductUserTokensAndCredits($totalTokenCount, 0, $modelToUse);
                    Log::info("Deducted {$totalTokenCount} tokens for Claude");
                }
            } catch (\Exception $e) {
                Log::error('Error deducting tokens: ' . $e->getMessage());
            }

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function createStreamedResponse($response, $request, $fileContentMessage, $uploadedFilePath, $messages, $expert, $modelToUse, $apiType)
    {
        return new StreamedResponse(function () use ($response, $request, $fileContentMessage, $uploadedFilePath, $messages, $expert, $modelToUse) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            $chunkCount = 0;
            $fullContent = '';

            foreach ($response as $chunk) {
                $content = $chunk->choices[0]->delta->content ?? '';
                $fullContent .= $content;
                $chunkCount++;

                if (connection_aborted()) {
                    Log::warning('Client disconnected early');
                    break;
                }

                echo "data: " . json_encode(['content' => $content]) . "\n\n";
                ob_flush();
                flush();

                usleep(50000);
            }

            // Save to database
            if (Auth::check() && $request->conversation_id) {
                $this->saveConversation($request, $fullContent, $uploadedFilePath, $fileContentMessage, $expert);
            }

            Log::info('Stream completed', ['total_chunks' => $chunkCount]);
            echo "data: [DONE]\n\n";
            ob_flush();
            flush();
            
            // Deduct tokens
            try {
                $totalTokenCount = 0;
                foreach ($messages as $msg) {
                    $encodedMessage = json_encode($msg);
                    $totalTokenCount += approximateTokenCount($encodedMessage);
                }
                $totalTokenCount += approximateTokenCount($fullContent);

                if (Auth::check()) {
                    deductUserTokensAndCredits($totalTokenCount, 0, $modelToUse);
                    Log::info("Deducted {$totalTokenCount} tokens");
                }
            } catch (\Exception $e) {
                Log::error('Error deducting tokens: ' . $e->getMessage());
            }

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function saveConversation($request, $fullContent, $uploadedFilePath, $fileContentMessage, $expert)
    {
        $conversation = ChatConversation::where('id', $request->conversation_id)
            ->where('user_id', Auth::id())
            ->first();

        if ($conversation) {
            if ($expert && $conversation->expert_id !== $expert->id) {
                $conversation->expert_id = $expert->id;
                $conversation->save();
            }
            
            $userMessage = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $request->message,
            ]);

            if ($uploadedFilePath) {
                ChatAttachment::create([
                    'message_id' => $userMessage->id,
                    'file_path' => $uploadedFilePath['file_path'],
                    'file_name' => $uploadedFilePath['file_name'],
                ]);
            }

            if ($fileContentMessage) {
                ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'system',
                    'content' => is_array($fileContentMessage['content']) 
                        ? json_encode($fileContentMessage['content']) 
                        : $fileContentMessage['content'],
                ]);
            }

            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => trim($fullContent),
            ]);

            if ($conversation->title === 'New Chat') {
                $title = substr($request->message, 0, 50);
                if (strlen($request->message) > 50) {
                    $title .= '...';
                }
                $conversation->update(['title' => $title]);
            }
        }
    }

    public function saveChat(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'messages' => 'nullable|array'
        ]);

        $conversation = ChatConversation::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
        ]);

        if ($request->messages) {
            foreach ($request->messages as $message) {
                ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => $message['role'],
                    'content' => $message['content'],
                    'id' => $message['id'] ?? null,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id
        ]);
    }

    private function handleImageGeneration(Request $request, User $user)
    {
        $creditCheck = checkUserHasCredits();
        if (!$creditCheck['status']) {
            return response()->json([
                'error' => $creditCheck['message']
            ], 400);
        }

        $prompt = $request->message;
        $currentModel = $user->selected_model;

        Log::info('Image generation requested', ['prompt' => $prompt, 'current_model' => $currentModel]);
        
        try {
            // Determine which image generation service to use based on current model
            if ($this->isGrokModel($currentModel)) {
                // If current model is Grok, use Grok image generation
                return $this->handleGrokImageGeneration($request, $user, $prompt);
            } elseif ($this->isClaudeModel($currentModel)) {
                // Claude doesn't support image generation
                return response()->json([
                    'error' => 'Image generation is not supported with Claude models. Please switch to an OpenAI or Grok model.'
                ], 400);
            } elseif ($this->isGeminiModel($currentModel)) {
                // Gemini doesn't support image generation
                return response()->json([
                    'error' => 'Image generation is not supported with Gemini models. Please switch to an OpenAI or Grok model.'
                ], 400);
            } else {
                // For OpenAI models or any other model, use DALL-E
                $response = OpenAI::images()->create([
                    'model' => 'dall-e-3',
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => '1024x1024',
                    'quality' => 'hd',
                    'style' => 'vivid',
                ]);

                deductUserTokensAndCredits(0, 8);
                $imageUrl = $response->data[0]->url;

                // Upload to Azure (existing code)
                $imageContent = file_get_contents($imageUrl);
                $blobClient = BlobRestProxy::createBlobService(config('filesystems.disks.azure.connection_string'));
                $containerName = config('filesystems.disks.azure.container');
                $imageName = 'chattermate-images/' . uniqid() . '.png';
                $blobClient->createBlockBlob($containerName, $imageName, $imageContent);

                $baseUrl = rtrim(config('filesystems.disks.azure.url'), '/');
                $publicUrl = "{$baseUrl}/{$containerName}/{$imageName}";

                // Save to database
                if (Auth::check() && $request->conversation_id) {
                    $conversation = ChatConversation::where('id', $request->conversation_id)
                        ->where('user_id', Auth::id())
                        ->first();

                    if ($conversation) {
                        $userMessage = ChatMessage::create([
                            'conversation_id' => $conversation->id,
                            'role' => 'user',
                            'content' => $prompt,
                        ]);

                        $assistantMessage = ChatMessage::create([
                            'conversation_id' => $conversation->id,
                            'role' => 'assistant',
                            'content' => $publicUrl,
                        ]);

                        if ($conversation->title === 'New Chat') {
                            $title = substr($prompt, 0, 50);
                            if (strlen($prompt) > 50) {
                                $title .= '...';
                            }
                            $conversation->update(['title' => $title]);
                        }
                    }
                }

                return new StreamedResponse(function () use ($publicUrl, $prompt, $request) {
                    echo "data: " . json_encode([
                        'image' => $publicUrl,
                        'prompt' => $prompt
                    ]) . "\n\n";
                    ob_flush();
                    flush();
                    
                    echo "data: [DONE]\n\n";
                    ob_flush();
                    flush();
                }, 200, [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                    'Connection' => 'keep-alive',
                    'X-Accel-Buffering' => 'no',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Image generation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Image generation failed: ' . $e->getMessage()], 500);
        }
    }

    private function handleGrokImageGeneration(Request $request, User $user, $prompt)
    {
        $apiKey = config('services.xai.api_key');
        
        try {
            $ch = curl_init('https://api.x.ai/v1/images/generations');
            
            $payload = [
                'model' => 'grok-2-image-1212',
                'prompt' => $prompt,
                'image_format' => 'url'
            ];
            
            Log::debug('Grok Image Generation Payload', ['payload' => $payload]);
            
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS => json_encode($payload)
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                Log::error('Grok image generation failed', [
                    'http_code' => $httpCode, 
                    'response' => $response,
                    'payload' => $payload
                ]);
                throw new \Exception('Grok API returned error: ' . $httpCode . ' - ' . $response);
            }

            $data = json_decode($response, true);
            
            if (!isset($data['data'][0]['url'])) {
                Log::error('Unexpected Grok API response format', ['response' => $data]);
                throw new \Exception('Unexpected API response format');
            }
            
            $imageUrl = $data['data'][0]['url'];

            deductUserTokensAndCredits(0, 8); // Adjust credits as needed

            // Upload to Azure
            $imageContent = file_get_contents($imageUrl);
            $blobClient = BlobRestProxy::createBlobService(config('filesystems.disks.azure.connection_string'));
            $containerName = config('filesystems.disks.azure.container');
            $imageName = 'chattermate-images/' . uniqid() . '_grok.png';
            $blobClient->createBlockBlob($containerName, $imageName, $imageContent);

            $baseUrl = rtrim(config('filesystems.disks.azure.url'), '/');
            $publicUrl = "{$baseUrl}/{$containerName}/{$imageName}";

            // Save to database
            if (Auth::check() && $request->conversation_id) {
                $conversation = ChatConversation::where('id', $request->conversation_id)
                    ->where('user_id', Auth::id())
                    ->first();

                if ($conversation) {
                    $userMessage = ChatMessage::create([
                        'conversation_id' => $conversation->id,
                        'role' => 'user',
                        'content' => $prompt,
                    ]);

                    $assistantMessage = ChatMessage::create([
                        'conversation_id' => $conversation->id,
                        'role' => 'assistant',
                        'content' => $publicUrl,
                    ]);

                    if ($conversation->title === 'New Chat') {
                        $title = substr($prompt, 0, 50);
                        if (strlen($prompt) > 50) {
                            $title .= '...';
                        }
                        $conversation->update(['title' => $title]);
                    }
                }
            }

            return new StreamedResponse(function () use ($publicUrl, $prompt) {
                echo "data: " . json_encode([
                    'image' => $publicUrl,
                    'prompt' => $prompt
                ]) . "\n\n";
                ob_flush();
                flush();
                
                echo "data: [DONE]\n\n";
                ob_flush();
                flush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);

        } catch (\Exception $e) {
            Log::error('Grok image generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getChats(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $conversations = ChatConversation::where('user_id', Auth::id())->whereNull('expert_id')
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'title', 'created_at', 'updated_at']);

        return response()->json($conversations);
    }
   
    public function getExpertChats(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $query = ChatConversation::where('user_id', Auth::id())
                    ->whereNotNull('expert_id')
                    ->whereHas('expert', function ($query) {
                        $query->where('domain', 'expert-chat');
                    });

        // Add expert filter if provided
        if ($request->has('expert_id') && $request->expert_id) {
            $query->where('expert_id', $request->expert_id);
        }

        $conversations = $query->orderBy('updated_at', 'desc')
                    ->get(['id', 'title', 'created_at', 'updated_at', 'expert_id']);

        return response()->json($conversations);
    }

    public function getAiTutorExpertChats(Request $request)
    {
          if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $query = ChatConversation::where('user_id', Auth::id())
                    ->whereNotNull('expert_id')
                    ->whereHas('expert', function ($query) {
                        $query->where('domain', 'ai-tutor');
                    });

        // Add expert filter if provided
        if ($request->has('expert_id') && $request->expert_id) {
            $query->where('expert_id', $request->expert_id);
        }

        $conversations = $query->orderBy('updated_at', 'desc')
                    ->get(['id', 'title', 'created_at', 'updated_at', 'expert_id']);

        return response()->json($conversations);
    }


    public function getConversation($id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Eager load messages and their attachment, and expert
        $conversation = ChatConversation::with(['messages.attachment', 'expert'])
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        return response()->json([
            'title' => $conversation->title,
            'expert_name' => $conversation->expert ? $conversation->expert->expert_name : null,
            'messages' => $conversation->messages->map(function ($message) {
                return [
                    'role' => $message->role,
                    'content' => $message->content,
                    'attachment' => $message->attachment ? [
                        'url' => rtrim(config('filesystems.disks.azure.url'), '/') . '/' . config('filesystems.disks.azure.container') . '/' . $message->attachment->file_path,
                        'name' => $message->attachment->file_name,
                    ] : null
                ];
            })
        ]);
    }


    // DELETE CONVERSATION
    public function deleteConversation($id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $conversation = ChatConversation::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        $conversation->delete();

        return response()->json(['success' => true]);
    }

    // PDF CHAT
    // PDF POC
    public function index()
    {
        return view('backend.chattermate.poc_pdf_file');
    }

    public function chatPDF(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'pdf' => 'required|file|mimes:pdf|max:10240', // 10MB max
        ]);

        $tempPath = $request->file('pdf')->storeAs('temp', uniqid() . '.pdf');
        $fullPath = storage_path("app/{$tempPath}");

        $parser = new PdfParser();
        $pdf = $parser->parseFile($fullPath);
        $text = substr($pdf->getText(), 0, 8000); // 8K token limit (adjust if needed)

        // Delete after parsing
        Storage::delete($tempPath);

        $prompt = "The following is content from a PDF:\n\n{$text}\n\nUser Question: " . $request->message;

        $messages = [
            ['role' => 'system', 'content' => 'You are an assistant that answers questions based on the provided document.'],
            ['role' => 'user', 'content' => $prompt],
        ];

        $response = OpenAI::chat()->createStreamed([
            'model' => 'gpt-4',
            'messages' => $messages,
            'stream' => true,
        ]);

        return new StreamedResponse(function () use ($response) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            foreach ($response as $chunk) {
                $content = $chunk->choices[0]->delta->content ?? '';
                echo "data: " . json_encode(['content' => $content]) . "\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function translate(Request $request)
    {
        $request->validate([
            'text' => 'required|string',
            'target_lang' => 'required|string',
        ]);

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini', // or 'gpt-4.1-mini'
                'messages' => [
                    ['role' => 'system', 'content' => "You are a translator. Translate the text into {$request->target_lang}."],
                    ['role' => 'user', 'content' => $request->text],
                ],
            ]);

            $translation = $response->choices[0]->message->content;

            return response()->json([
                'success' => true,
                'translation' => $translation,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Edit CHat Title
    public function updateConversationTitle(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255'
        ]);
        
        $conversation = ChatConversation::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();
        
        if (!$conversation) {
            return response()->json(['success' => false, 'error' => 'Conversation not found'], 404);
        }
        
        $conversation->title = $request->title;
        $conversation->save();
        
        return response()->json(['success' => true, 'title' => $conversation->title]);
    }













    




    public function multiModelChat(Request $request)
    {
        set_time_limit(300);
    
        Log::info('MultiModelChat request received', [
            'message' => $request->input('message'),
            'models' => $request->input('models'),
            'conversation_id' => $request->input('conversation_id'),
            'web_search' => $request->boolean('web_search'),
            'create_image' => $request->boolean('create_image'),
            'optimization_mode' => $request->input('optimization_mode', 'fixed'), // ✅ NEW
        ]);
        
        $request->validate([
            'message' => 'required|string',
            'models' => 'required|string',
            'conversation_id' => 'nullable|exists:multi_compare_conversations,id',
            'pdf' => 'nullable|file|mimes:pdf,doc,docx,png,jpg,jpeg,webp,gif|max:10240',
            'web_search' => 'sometimes|boolean',
            'create_image' => 'sometimes|boolean',
            'optimization_mode' => 'sometimes|string|in:fixed,smart_same,smart_all', // ✅ NEW
        ]);

        $message = $request->input('message');
        $modelsJson = $request->input('models');
        $selectedModels = json_decode($modelsJson, true);
        $conversationId = $request->input('conversation_id');
        $useWebSearch = $request->boolean('web_search');
        $createImage = $request->boolean('create_image');
        $optimizationMode = $request->input('optimization_mode', 'fixed'); // ✅ NEW
        $user = auth()->user();

        Log::info('Multi-model chat request', [
            'message' => $message,
            'models' => $selectedModels,
            'conversation_id' => $conversationId,
            'web_search' => $useWebSearch,
            'create_image' => $createImage,
            'optimization_mode' => $optimizationMode, // ✅ NEW
        ]);

        if (!is_array($selectedModels) || empty($selectedModels)) {
            return response()->json(['error' => 'Invalid models selection'], 400);
        }

        // ✅ HANDLE IMAGE GENERATION FIRST (BEFORE OPTIMIZATION)
        // Image generation needs original model names to determine capabilities
        if ($createImage) {
            return $this->handleMultiModelImageGeneration($request, $user, $selectedModels, $message);
        }

        // ✅ NEW: Apply smart model optimization if enabled (AFTER image check)
        $originalModels = $selectedModels;
        $optimizedModels = [];
        
        if ($optimizationMode !== 'fixed') {
            // Analyze query complexity
            $complexityScore = \App\Services\AI\QueryComplexityAnalyzer::analyze($message);
            
            Log::info('Applying model optimization', [
                'mode' => $optimizationMode,
                'complexity_score' => $complexityScore,
                'original_models' => $selectedModels
            ]);
            
            foreach ($selectedModels as $model) {
                if ($optimizationMode === 'smart_same') {
                    // Optimize within same provider
                    $suggestedModel = $this->getSuggestedModelFromComplexity($complexityScore, $model, $user);
                } else {
                    // Optimize across all providers
                    $suggestedModel = $this->getSuggestedModelFromAnyProvider($complexityScore, $model, $user);
                }
                
                $optimizedModels[$model] = $suggestedModel;
                
                if ($model !== $suggestedModel) {
                    Log::info('Model optimized', [
                        'from' => $model,
                        'to' => $suggestedModel,
                        'mode' => $optimizationMode,
                        'complexity' => $complexityScore
                    ]);
                }
            }
            
            // Replace models with optimized versions
            $selectedModels = array_values(array_unique($optimizedModels));
            
            Log::info('Optimization complete', [
                'original_count' => count($originalModels),
                'optimized_count' => count($selectedModels),
                'optimized_models' => $selectedModels
            ]);
        }

        // ✅ ENHANCED FILE UPLOAD HANDLING
        $fileContentMessage = null;
        $uploadedFilePath = null;
        $azureFileUrl = null;

        if ($request->hasFile('pdf') && $request->file('pdf')->isValid()) {
            $file = $request->file('pdf');
            $extension = strtolower($file->getClientOriginalExtension());
            $tempPath = $file->storeAs('temp', uniqid() . '.' . $extension);
            $fullPath = storage_path("app/{$tempPath}");

            try {
                // Upload to Azure first
                $blobClient = BlobRestProxy::createBlobService(config('filesystems.disks.azure.connection_string'));
                $containerName = config('filesystems.disks.azure.container');
                $originalFileName = $file->getClientOriginalName();
                $azureFileName = 'chattermate-multi-compare/' . uniqid() . '_' . $originalFileName;
                $fileContent = file_get_contents($fullPath);
                $blobClient->createBlockBlob($containerName, $azureFileName, $fileContent, new CreateBlockBlobOptions());

                $baseUrl = rtrim(config('filesystems.disks.azure.url'), '/');
                $azureFileUrl = "{$baseUrl}/{$containerName}/{$azureFileName}";

                $uploadedFilePath = [
                    'file_path' => $azureFileName,
                    'file_name' => $originalFileName,
                ];

                // Process file based on type
                if (in_array($extension, ['pdf', 'doc', 'docx'])) {
                    $text = '';
                    
                    if ($extension === 'pdf') {
                        $text = $this->extractTextFromPDF($fullPath);
                    } else {
                        $text = $this->extractTextFromDOCX($fullPath);
                    }

                    $fileContentMessage = [
                        'role' => 'system',
                        'content' => "This is the content of the uploaded document. Refer to it for future questions:\n\n{$text}",
                    ];

                } elseif (in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
                    // For images, create a multimodal message
                    $fileContentMessage = [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $message ?: "What is in this image?"],
                            ['type' => 'image_url', 'image_url' => ['url' => $azureFileUrl]],
                        ],
                    ];
                }

            } catch (\Exception $e) {
                Log::error('File processing error: ' . $e->getMessage());
            } finally {
                Storage::delete($tempPath);
            }
        }

        // Get or create conversation
        $conversation = null;
        if ($conversationId) {
            $conversation = MultiCompareConversation::where('id', $conversationId)
                ->where('user_id', $user->id)
                ->first();
        }

        // Around line 67-77, update this section:
        if (!$conversation) {
            $conversation = MultiCompareConversation::create([
                'user_id' => $user->id,
                'title' => $this->generateConversationTitle($message),
                'selected_models' => $selectedModels,
                'optimization_mode' => $request->input('optimization_mode', 'fixed')
            ]);
        } else {
            $conversation->update([
                'selected_models' => $selectedModels,
                'optimization_mode' => $request->input('optimization_mode', 'fixed')
            ]);
        }

        // Get conversation history
        $conversationHistory = [];
        $messages = $conversation->messages()->orderBy('created_at')->get();
        
        foreach ($messages as $msg) {
            if ($msg->role === 'user') {
                $conversationHistory[] = ['role' => 'user', 'content' => $msg->content];
            } elseif ($msg->role === 'assistant' && $msg->all_responses) {
                foreach ($msg->all_responses as $model => $response) {
                    $conversationHistory[] = ['role' => 'assistant', 'content' => $response];
                    break;
                }
            }
        }

        // Prepare base messages
        $baseMessages = [];
        
        $systemMessage = "You are a helpful AI assistant. Respond clearly and concisely.";
        
        // Add math and visualization instructions
        $needsMath = preg_match('/\b(math|equation|formula|calculate|integral|integrate|integration|derivative|differentiate|differentiation|sum|algebra|geometry|trigonometry|calculus|solve|sqrt|fraction|logarithm|log|ln|sin|cos|tan|exp|factorial|permutation|combination|matrix|vector|polar|cartesian|limit|limits|simplify|expand|factor|polynomial|quadratic|cubic)\b/i', $message);
        $needsVisualization = preg_match('/\b(plot|graph|chart|visualize|show.*graph|draw|diagram|display|illustrate|represent|render|sketch|map|figure|outline|exhibit|demonstrate|view|table)\b/i', $message);

        if ($needsMath) {
            $systemMessage .= "\n\n**IMPORTANT: For mathematical expressions, use LaTeX notation:**
            - Inline math: \$x^2 + y^2 = z^2\$
            - Display math: \$\$\\int_0^\\infty e^{-x^2} dx = \\frac{\\sqrt{\\pi}}{2}\$\$";
        }

        if ($needsVisualization) {
            $systemMessage .= "\n\n**IMPORTANT: For charts/graphs, use Chart.js JSON format wrapped in ```chart blocks.**
            You can create MULTIPLE charts - each in its own chart block.
            
            Example:
        ```chart
                    {
                        \"type\": \"line\",
                        \"data\": {
                            \"labels\": [\"Jan\", \"Feb\", \"Mar\"],
                            \"datasets\": [{
                                \"label\": \"Sales\",
                                \"data\": [10, 20, 30],
                                \"borderColor\": \"rgb(75, 192, 192)\"
                            }]
                        },
                        \"options\": {
                            \"responsive\": true
                        }
                    }
        ```
            
            Supported types: line, bar, pie, doughnut, scatter, radar.";
        }

        if ($useWebSearch) {
            $systemMessage .= "\n\nYou have access to web search capabilities. Use them when current information is needed.";
        }

        $baseMessages[] = ['role' => 'system', 'content' => $systemMessage];

        foreach ($conversationHistory as $historyMessage) {
            $baseMessages[] = $historyMessage;
        }

        if ($fileContentMessage) {
            $baseMessages[] = $fileContentMessage;
            if ($fileContentMessage['role'] === 'system' && $message) {
                $baseMessages[] = ['role' => 'user', 'content' => $message];
            }
        }

        if (!$fileContentMessage) {
            $baseMessages[] = ['role' => 'user', 'content' => $message];
        }

        Log::info('Base messages prepared', ['message_count' => count($baseMessages)]);

            // Return streaming response
        return response()->stream(function () use ($selectedModels, $baseMessages, $useWebSearch, $message, $conversation, $uploadedFilePath, $fileContentMessage, $optimizedModels, $originalModels) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            $responses = [];
            $allResponses = [];

            echo "data: " . json_encode([
                'type' => 'init',
                'models' => $selectedModels,
                'conversation_id' => $conversation->id,
                'message' => 'Starting comparison...',
                'optimized_models' => $optimizedModels ?? null, // ✅ NEW: Send optimization info
                'original_models' => $originalModels ?? null, // ✅ NEW
            ]) . "\n\n";
            ob_flush();
            flush();

            // ✅ PROCESS ALL MODELS SIMULTANEOUSLY
            $this->processModelsSimultaneously($selectedModels, $baseMessages, $useWebSearch, $responses, $allResponses);

            try {
                $this->saveMultiCompareConversation($conversation, $message, $allResponses, $uploadedFilePath, $fileContentMessage);
            } catch (\Exception $e) {
                Log::error('Error saving conversation: ' . $e->getMessage());
            }

            echo "data: " . json_encode([
                'type' => 'all_complete',
                'responses' => $responses,
                'conversation_id' => $conversation->id
            ]) . "\n\n";
            
            echo "data: [DONE]\n\n";
            ob_flush();
            flush();

            try {
                $totalTokenCount = 0;
                foreach ($baseMessages as $msg) {
                    $encodedMessage = json_encode($msg);
                    $totalTokenCount += approximateTokenCount($encodedMessage);
                }
                foreach ($responses as $response) {
                    $totalTokenCount += approximateTokenCount($response);
                }
                $totalTokenCount *= count($selectedModels);

                if (Auth::check()) {
                    deductUserTokensAndCredits($totalTokenCount, 0, 'multi-model');
                    Log::info("Deducted {$totalTokenCount} tokens for multi-model comparison");
                }
            } catch (\Exception $e) {
                Log::error('Error deducting tokens: ' . $e->getMessage());
            }

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    // ✅ NEW METHOD: Process all models simultaneously using curl_multi
    // ✅ UPDATED METHOD: Process all models simultaneously using curl_multi
    private function processModelsSimultaneously($selectedModels, $baseMessages, $useWebSearch, &$responses, &$allResponses)
    {
        // Clear static buffers before starting
        self::$simultaneousBuffers = [];
        self::$simultaneousResponses = [];
        
        // Initialize responses for all models
        foreach ($selectedModels as $model) {
            $responses[$model] = '';
            self::$simultaneousResponses[$model] = '';
            
            echo "data: " . json_encode([
                'type' => 'model_start',
                'model' => $model,
                'message' => 'Processing...'
            ]) . "\n\n";
            ob_flush();
            flush();
        }

        // Create curl handles for all models
        $curlHandles = [];
        $modelMapping = []; // Maps curl handle to model info
        
        $mh = curl_multi_init();

        foreach ($selectedModels as $model) {
            $ch = $this->createCurlHandleForModel($model, $baseMessages, $useWebSearch);
            
            if ($ch) {
                curl_multi_add_handle($mh, $ch);
                $curlHandles[(int)$ch] = $ch;
                $modelMapping[(int)$ch] = $model;
            }
        }

        // Execute all handles simultaneously
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            
            // Process any available data
            while ($info = curl_multi_info_read($mh)) {
                $handle = $info['handle'];
                $handleId = (int)$handle;
                
                if (isset($modelMapping[$handleId])) {
                    $model = $modelMapping[$handleId];
                    
                    if ($info['result'] === CURLE_OK) {
                        // ✅ FIX: Copy final response from static array to the passed arrays
                        $responses[$model] = self::$simultaneousResponses[$model] ?? '';
                        $allResponses[$model] = $responses[$model];
                        
                        Log::info("Model {$model} completed successfully", [
                            'response_length' => strlen($responses[$model])
                        ]);
                        
                        echo "data: " . json_encode([
                            'type' => 'complete',
                            'model' => $model,
                            'final_response' => $responses[$model]
                        ]) . "\n\n";
                        ob_flush();
                        flush();
                    } else {
                        $error = curl_error($handle);
                        Log::error("Error processing model {$model}: {$error}");
                        
                        $errorMessage = 'Error: ' . $error;
                        $responses[$model] = $errorMessage;
                        $allResponses[$model] = $errorMessage;
                        
                        echo "data: " . json_encode([
                            'type' => 'error',
                            'model' => $model,
                            'error' => $errorMessage
                        ]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                }
                
                curl_multi_remove_handle($mh, $handle);
                curl_close($handle);
            }
            
            // Small delay to prevent CPU spinning
            if ($running > 0) {
                curl_multi_select($mh, 0.1);
            }
        } while ($running > 0);

        curl_multi_close($mh);
        
        // ✅ FIX: Final sync - ensure all responses are captured
        foreach ($selectedModels as $model) {
            if (!isset($allResponses[$model]) && isset(self::$simultaneousResponses[$model])) {
                $responses[$model] = self::$simultaneousResponses[$model];
                $allResponses[$model] = self::$simultaneousResponses[$model];
            }
        }
        
        Log::info('All models processed', [
            'models_count' => count($allResponses),
            'models' => array_keys($allResponses),
            'response_lengths' => array_map('strlen', $allResponses)
        ]);
    }

    // ✅ NEW METHOD: Create curl handle for a specific model
    private function createCurlHandleForModel($model, $messages, $useWebSearch)
    {
        try {
            if ($this->isClaudeModel($model)) {
                return $this->createClaudeCurlHandle($model, $messages, $useWebSearch);
            } elseif ($this->isGeminiModel($model)) {
                return $this->createGeminiCurlHandle($model, $messages, $useWebSearch);
            } elseif ($this->isGrokModel($model)) {
                return $this->createGrokCurlHandle($model, $messages, $useWebSearch);
            } else {
                return $this->createOpenAICurlHandle($model, $messages, $useWebSearch);
            }
        } catch (\Exception $e) {
            Log::error("Error creating curl handle for {$model}: " . $e->getMessage());
            return null;
        }
    }

    // ✅ NEW METHOD: Create OpenAI curl handle
    private function createOpenAICurlHandle($model, $messages, $useWebSearch)
    {
        $apiKey = config('services.openai.api_key');
        $modelToUse = $useWebSearch ? 'gpt-4o-search-preview' : $model;
        
        $payload = [
            'model' => $modelToUse,
            'messages' => $messages,
            'stream' => true,
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($model, &$responses) {
                return $this->handleStreamChunkSimultaneous($model, $data, 'openai');
            }
        ]);

        return $ch;
    }

    // ✅ NEW METHOD: Create Claude curl handle
    private function createClaudeCurlHandle($model, $messages, $useWebSearch)
    {
        $claudeData = $this->convertMessagesToClaudeFormat($messages);
        
        $payload = [
            'model' => $model,
            'messages' => $claudeData['messages'],
            'max_tokens' => 4096,
            'stream' => true,
        ];

        if ($claudeData['system']) {
            $payload['system'] = $claudeData['system'];
        }

        if ($useWebSearch) {
            $payload['tools'] = [
                [
                    'type' => 'web_search_20250305',
                    'name' => 'web_search',
                    'max_uses' => 5
                ]
            ];
        }

        $apiKey = config('services.anthropic.api_key');
        
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($model, &$responses) {
                return $this->handleStreamChunkSimultaneous($model, $data, 'claude');
            }
        ]);

        return $ch;
    }

    // ✅ NEW METHOD: Create Gemini curl handle
    private function createGeminiCurlHandle($model, $messages, $useWebSearch)
    {
        $geminiMessages = $this->convertMessagesToGeminiFormat($messages);
        $apiKey = config('services.gemini.api_key');
        
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?alt=sse&key={$apiKey}";
        
        $payload = [
            'contents' => $geminiMessages['contents'],
            'generationConfig' => [
                'temperature' => 1.0,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 8192,
            ],
        ];
        
        if ($geminiMessages['systemInstruction']) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $geminiMessages['systemInstruction']]
                ]
            ];
        }
        
        if ($useWebSearch) {
            if (str_contains($model, '2.') || str_contains($model, 'flash-002')) {
                $payload['tools'] = [['google_search' => (object)[]]];
            } else {
                $payload['tools'] = [[
                    'googleSearchRetrieval' => [
                        'dynamicRetrievalConfig' => [
                            'mode' => 'MODE_DYNAMIC',
                            'dynamicThreshold' => 0.7
                        ]
                    ]
                ]];
            }
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($model, &$responses) {
                return $this->handleStreamChunkSimultaneous($model, $data, 'gemini');
            }
        ]);

        return $ch;
    }

    // ✅ NEW METHOD: Create Grok curl handle
    private function createGrokCurlHandle($model, $messages, $useWebSearch)
    {
        $apiKey = config('services.xai.api_key');
        
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
            'temperature' => 0.7,
            'max_tokens' => 4096,
        ];
        
        if ($useWebSearch) {
            $currentDate = date('Y-m-d');
            $lastMessage = end($messages);
            if ($lastMessage && $lastMessage['role'] === 'user') {
                $originalContent = $lastMessage['content'];
                $messages[count($messages) - 1]['content'] = 
                    "Current date: {$currentDate}. " . 
                    "Please search for and use only the most recent, up-to-date information when answering: " . 
                    $originalContent;
            }
            $payload['messages'] = $messages;
        }

        $ch = curl_init('https://api.x.ai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($model, &$responses) {
                return $this->handleStreamChunkSimultaneous($model, $data, 'grok');
            }
        ]);

        return $ch;
    }

    // ✅ NEW METHOD: Handle stream chunks for simultaneous processing
    private static $simultaneousBuffers = [];
    private static $simultaneousResponses = [];

    private function handleStreamChunkSimultaneous($model, $data, $provider)
    {
        // Initialize buffers if not exists
        if (!isset(self::$simultaneousBuffers[$model])) {
            self::$simultaneousBuffers[$model] = '';
        }
        if (!isset(self::$simultaneousResponses[$model])) {
            self::$simultaneousResponses[$model] = '';
        }

        self::$simultaneousBuffers[$model] .= $data;
        $lines = explode("\n", self::$simultaneousBuffers[$model]);
        self::$simultaneousBuffers[$model] = array_pop($lines);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line === 'data: [DONE]') {
                continue;
            }

            if (strpos($line, 'data: ') === 0) {
                $json = substr($line, 6);
                
                try {
                    $decoded = json_decode($json, true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        continue;
                    }
                    
                    $content = '';
                    
                    if ($provider === 'claude') {
                        if (isset($decoded['type']) && $decoded['type'] === 'content_block_delta') {
                            $content = $decoded['delta']['text'] ?? '';
                        }
                    } elseif ($provider === 'gemini') {
                        if (isset($decoded['candidates'][0]['content']['parts'])) {
                            foreach ($decoded['candidates'][0]['content']['parts'] as $part) {
                                if (isset($part['text'])) {
                                    $content .= $part['text'];
                                }
                            }
                        }
                    } elseif ($provider === 'openai') {
                        if (isset($decoded['choices'][0]['delta']['content'])) {
                            $content = $decoded['choices'][0]['delta']['content'];
                        }
                    } elseif ($provider === 'grok') {
                        if (isset($decoded['choices'][0]['delta']['content'])) {
                            $content = $decoded['choices'][0]['delta']['content'];
                        }
                    }

                    if ($content !== '') {
                        self::$simultaneousResponses[$model] .= $content;

                        echo "data: " . json_encode([
                            'type' => 'chunk',
                            'model' => $model,
                            'content' => $content,
                            'full_response' => self::$simultaneousResponses[$model],
                            'provider' => $provider
                        ]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                    
                } catch (\Exception $e) {
                    Log::error("Error parsing JSON for {$model} ({$provider}): " . $e->getMessage());
                }
            }
        }

        return strlen($data);
    }

    private function handleMultiModelImageGeneration(Request $request, User $user, $selectedModels, $prompt)
    {
        $creditCheck = checkUserHasCredits();
        if (!$creditCheck['status']) {
            return response()->json([
                'error' => $creditCheck['message']
            ], 400);
        }

        Log::info('Multi-model image generation requested', [
            'prompt' => $prompt,
            'models' => $selectedModels,
            'model_count' => count($selectedModels),
            'has_file' => $request->hasFile('pdf'),
            'optimization_mode' => $request->input('optimization_mode', 'fixed') // ✅ ADD THIS
        ]);

        // ✅ NEW: Get optimization mode to handle Smart modes properly
        $optimizationMode = $request->input('optimization_mode', 'fixed');

        // ✅ Validate that at least one model supports image generation
        $supportedModels = array_filter($selectedModels, function($model) {
            return $this->supportsImageGeneration($model);
        });

        if (empty($supportedModels)) {
            Log::warning('No models support image generation', [
                'models' => $selectedModels
            ]);
        } else {
            Log::info('Image generation capable models', [
                'supported' => $supportedModels,
                'count' => count($supportedModels)
            ]);
        }

        $conversationId = $request->input('conversation_id');
        
        // ✅ NEW: Check for uploaded image
        $uploadedImagePath = null;
        $uploadedImageUrl = null;
        
        if ($request->hasFile('pdf') && $request->file('pdf')->isValid()) {
            $file = $request->file('pdf');
            $extension = strtolower($file->getClientOriginalExtension());
            
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $tempPath = $file->storeAs('temp', uniqid() . '.' . $extension);
                $uploadedImagePath = storage_path("app/{$tempPath}");
                
                try {
                    $blobClient = BlobRestProxy::createBlobService(config('filesystems.disks.azure.connection_string'));
                    $containerName = config('filesystems.disks.azure.container');
                    $azureFileName = 'chattermate-multi-compare-uploads/' . uniqid() . '_' . $file->getClientOriginalName();
                    $fileContent = file_get_contents($uploadedImagePath);
                    $blobClient->createBlockBlob($containerName, $azureFileName, $fileContent);
                    
                    $baseUrl = rtrim(config('filesystems.disks.azure.url'), '/');
                    $uploadedImageUrl = "{$baseUrl}/{$containerName}/{$azureFileName}";
                    
                    Log::info('Uploaded image for editing', [
                        'local_path' => $uploadedImagePath,
                        'azure_url' => $uploadedImageUrl
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to upload image to Azure: ' . $e->getMessage());
                }
            }
        }
        
        // Get or create conversation
        $conversation = null;
        if ($conversationId) {
            $conversation = MultiCompareConversation::where('id', $conversationId)
                ->where('user_id', $user->id)
                ->first();
        }

        if (!$conversation) {
            $conversation = MultiCompareConversation::create([
                'user_id' => $user->id,
                'title' => $this->generateConversationTitle($prompt),
                'selected_models' => $selectedModels,
                'optimization_mode' => $optimizationMode, // ✅ SAVE MODE
            ]);
        }
                
        // ✅ NEW: Get conversation history for multi-turn editing
        $conversationHistory = [];
        if ($conversation) {
            $messages = $conversation->messages()->orderBy('created_at')->get();
            foreach ($messages as $msg) {
                if ($msg->role === 'assistant' && $msg->all_responses) {
                    foreach ($selectedModels as $model) {
                        if (isset($msg->all_responses[$model])) {
                            $conversationHistory[] = [
                                'role' => 'assistant',
                                'content' => $msg->all_responses[$model],
                                'model' => $model
                            ];
                            break;
                        }
                    }
                }
            }
        }

        return response()->stream(function () use ($selectedModels, $prompt, $user, $conversation, $uploadedImagePath, $uploadedImageUrl, $conversationHistory, $optimizationMode) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            $allResponses = [];
            
            // ✅ NEW: Build model mapping for Smart modes
            $modelMapping = []; // Maps actual model -> frontend panel ID
            
            if ($optimizationMode === 'smart_same') {
                // For Smart (Same), map each model to its provider panel
                foreach ($selectedModels as $model) {
                    // Find provider from model name
                    $provider = $this->getProviderFromModel($model);
                    $panelId = "{$provider}_smart_panel";
                    $modelMapping[$model] = $panelId;
                    
                    Log::info('Smart (Same) model mapping', [
                        'actual_model' => $model,
                        'panel_id' => $panelId,
                        'provider' => $provider
                    ]);
                }
            } elseif ($optimizationMode === 'smart_all') {
                // For Smart (All), all models map to single panel
                foreach ($selectedModels as $model) {
                    $modelMapping[$model] = 'smart_all_auto';
                }
            } else {
                // For Fixed mode, model = panel
                foreach ($selectedModels as $model) {
                    $modelMapping[$model] = $model;
                }
            }
            
            $modeMessage = 'Starting image generation...';
            if ($uploadedImagePath) {
                $modeMessage = 'Starting image editing...';
            } elseif (!empty($conversationHistory)) {
                $modeMessage = 'Refining previous image...';
            }

            echo "data: " . json_encode([
                'type' => 'init',
                'models' => $selectedModels,
                'conversation_id' => $conversation->id,
                'message' => $modeMessage,
                'mode' => $uploadedImagePath ? 'editing' : (!empty($conversationHistory) ? 'refining' : 'generating'),
                'optimization_mode' => $optimizationMode, // ✅ SEND MODE
                'model_mapping' => $modelMapping // ✅ SEND MAPPING
            ]) . "\n\n";
            ob_flush();
            flush();

            foreach ($selectedModels as $model) {
                try {
                    // ✅ Get the frontend panel ID for this model
                    $panelId = $modelMapping[$model] ?? $model;
                    
                    Log::info("Processing image generation for model", [
                        'actual_model' => $model,
                        'panel_id' => $panelId,
                        'uploaded_image' => !empty($uploadedImagePath),
                        'history_count' => count($conversationHistory)
                    ]);

                    echo "data: " . json_encode([
                        'type' => 'model_start',
                        'model' => $panelId, // ✅ USE PANEL ID
                        'actual_model' => $model, // ✅ ALSO SEND ACTUAL MODEL
                        'message' => $uploadedImagePath ? 'Editing image...' : 'Generating image...'
                    ]) . "\n\n";
                    ob_flush();
                    flush();

                    // Check if model supports image generation
                    if (!$this->supportsImageGeneration($model)) {
                        $errorMessage = "Image generation is not supported for model: {$model}";
                        
                        Log::warning($errorMessage, ['model' => $model, 'panel_id' => $panelId]);
                        
                        echo "data: " . json_encode([
                            'type' => 'error',
                            'model' => $panelId, // ✅ USE PANEL ID
                            'actual_model' => $model,
                            'error' => $errorMessage
                        ]) . "\n\n";
                        ob_flush();
                        flush();
                        
                        $allResponses[$model] = $errorMessage;
                        continue;
                    }

                    // Generate or edit image based on model type
                    $imageSource = null;
                    $tempFile = null;

                    if ($this->isGrokModel($model)) {
                        if ($uploadedImagePath) {
                            $errorMessage = "Grok doesn't support image editing yet. Only generation.";
                            $allResponses[$model] = $errorMessage;
                            
                            Log::info('Grok image editing not supported', ['model' => $model]);
                            
                            echo "data: " . json_encode([
                                'type' => 'error',
                                'model' => $panelId, // ✅ USE PANEL ID
                                'actual_model' => $model,
                                'error' => $errorMessage
                            ]) . "\n\n";
                            ob_flush();
                            flush();
                            continue;
                        }
                        
                        Log::info('Generating Grok image', ['model' => $model, 'prompt' => substr($prompt, 0, 50)]);
                        $imageSource = $this->generateGrokImage($prompt);
                        $imageContent = file_get_contents($imageSource);
                        
                    } elseif ($this->isGeminiModel($model)) {
                        Log::info('Generating Gemini image', [
                            'model' => $model,
                            'has_input_image' => !empty($uploadedImagePath),
                            'has_history' => !empty($conversationHistory)
                        ]);
                        
                        $inputImage = $uploadedImagePath;
                        
                        $historyForModel = null;
                        if (!$inputImage && !empty($conversationHistory)) {
                            $historyForModel = $conversationHistory;
                        }
                        
                        $tempFile = $this->generateGeminiImage($prompt, $inputImage, $historyForModel);
                        $imageContent = file_get_contents($tempFile);
                        
                    } else {
                        // OpenAI - doesn't support editing
                        if ($uploadedImagePath) {
                            $errorMessage = "OpenAI/DALL-E doesn't support image editing. Only generation.";
                            $allResponses[$model] = $errorMessage;
                            
                            Log::info('OpenAI image editing not supported', ['model' => $model]);
                            
                            echo "data: " . json_encode([
                                'type' => 'error',
                                'model' => $panelId, // ✅ USE PANEL ID
                                'actual_model' => $model,
                                'error' => $errorMessage
                            ]) . "\n\n";
                            ob_flush();
                            flush();
                            continue;
                        }
                        
                        Log::info('Generating OpenAI image', ['model' => $model, 'prompt' => substr($prompt, 0, 50)]);
                        $imageSource = $this->generateOpenAIImage($prompt);
                        $imageContent = file_get_contents($imageSource);
                    }

                    if ($imageContent) {
                        Log::info('Image generated successfully', [
                            'model' => $model,
                            'panel_id' => $panelId,
                            'size' => strlen($imageContent)
                        ]);
                        
                        // Upload to Azure
                        $blobClient = BlobRestProxy::createBlobService(config('filesystems.disks.azure.connection_string'));
                        $containerName = config('filesystems.disks.azure.container');
                        
                        // Determine file extension
                        $extension = 'png';
                        if ($this->isGeminiModel($model)) {
                            $extension = 'png';
                        } elseif ($this->isGrokModel($model)) {
                            $extension = 'jpg';
                        }
                        
                        $imageName = 'chattermate-multi-compare-images/' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $model) . '.' . $extension;
                        $blobClient->createBlockBlob($containerName, $imageName, $imageContent);

                        $baseUrl = rtrim(config('filesystems.disks.azure.url'), '/');
                        $publicUrl = "{$baseUrl}/{$containerName}/{$imageName}";

                        $allResponses[$model] = $publicUrl;

                        Log::info('Image uploaded to Azure', [
                            'model' => $model,
                            'panel_id' => $panelId,
                            'url' => $publicUrl
                        ]);

                        echo "data: " . json_encode([
                            'type' => 'complete',
                            'model' => $panelId, // ✅ USE PANEL ID
                            'actual_model' => $model, // ✅ ALSO SEND ACTUAL MODEL
                            'image' => $publicUrl,
                            'prompt' => $prompt,
                            'was_editing' => !empty($uploadedImagePath)
                        ]) . "\n\n";
                        ob_flush();
                        flush();

                        // Deduct credits per image
                        deductUserTokensAndCredits(0, 8);
                    } else {
                        throw new \Exception('Failed to get image content');
                    }

                    // Clean up temp file
                    if ($tempFile && file_exists($tempFile)) {
                        unlink($tempFile);
                    }

                } catch (\Exception $e) {
                    $panelId = $modelMapping[$model] ?? $model;
                    
                    Log::error("Error generating image for model {$model}", [
                        'panel_id' => $panelId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    $errorMessage = 'Image generation failed: ' . $e->getMessage();
                    $allResponses[$model] = $errorMessage;
                    
                    echo "data: " . json_encode([
                        'type' => 'error',
                        'model' => $panelId, // ✅ USE PANEL ID
                        'actual_model' => $model,
                        'error' => $errorMessage
                    ]) . "\n\n";
                    ob_flush();
                    flush();
                    
                    // Clean up temp file on error
                    if (isset($tempFile) && $tempFile && file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                }
            }
            
            // Clean up uploaded image temp file
            if ($uploadedImagePath && file_exists($uploadedImagePath)) {
                unlink($uploadedImagePath);
            }

            // Save to database (with upload info if present)
            try {
                $uploadInfo = null;
                if ($uploadedImageUrl) {
                    $uploadInfo = [
                        'file_path' => $uploadedImageUrl,
                        'file_name' => 'uploaded_image.png',
                    ];
                }
                
                $this->saveMultiCompareConversation($conversation, $prompt, $allResponses, $uploadInfo, null);
            } catch (\Exception $e) {
                Log::error('Error saving image generation conversation: ' . $e->getMessage());
            }

            echo "data: " . json_encode([
                'type' => 'all_complete',
                'conversation_id' => $conversation->id
            ]) . "\n\n";
            
            echo "data: [DONE]\n\n";
            ob_flush();
            flush();

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    // ============================================================================
    // HELPER METHOD: Get provider from model name
    // ============================================================================

    /**
     * Determine the provider from a model name
     */
    private function getProviderFromModel($model)
    {
        $modelLower = strtolower($model);
        
        if (str_contains($modelLower, 'gemini')) {
            return 'gemini';
        } elseif (str_contains($modelLower, 'claude')) {
            return 'claude';
        } elseif (str_contains($modelLower, 'grok')) {
            return 'grok';
        } else {
            return 'openai';
        }
    }

    // Helper method to generate OpenAI image
    private function generateOpenAIImage($prompt)
    {
        $response = OpenAI::images()->create([
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
            'quality' => 'hd',
            'style' => 'vivid',
        ]);

        return $response->data[0]->url;
    }

    // Helper method to generate Grok image
    private function generateGrokImage($prompt)
    {
        $apiKey = config('services.xai.api_key');
        
        $ch = curl_init('https://api.x.ai/v1/images/generations');
        
        $payload = [
            'model' => 'grok-2-image-1212',
            'prompt' => $prompt,
            'image_format' => 'url'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Grok API returned error: ' . $httpCode);
        }

        $data = json_decode($response, true);
        
        if (!isset($data['data'][0]['url'])) {
            throw new \Exception('Unexpected API response format');
        }
        
        return $data['data'][0]['url'];
    }

    // Generate image using Gemini 2.5 Flash Image model
    private function generateGeminiImage($prompt, $imagePath = null, $conversationHistory = null)
    {
        $apiKey = config('services.gemini.api_key');
        
        // Use the gemini-2.5-flash-image model
        $model = 'gemini-2.5-flash-image';
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        
        // Build the content parts
        $parts = [];
        
        // ✅ NEW: Check for previous image in conversation (for multi-turn editing)
        if (!$imagePath && $conversationHistory) {
            $previousImage = $this->getPreviousImageFromConversation($conversationHistory);
            if ($previousImage) {
                $imagePath = $previousImage;
            }
        }
        
        // ✅ NEW: If image is provided, add it first
        if ($imagePath) {
            $imageBase64 = $this->convertImageToBase64($imagePath);
            
            if ($imageBase64) {
                // Determine MIME type
                $mimeType = 'image/jpeg';
                if (is_string($imagePath)) {
                    $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
                    $mimeType = match($extension) {
                        'png' => 'image/png',
                        'jpg', 'jpeg' => 'image/jpeg',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp',
                        default => 'image/jpeg',
                    };
                }
                
                $parts[] = [
                    'inlineData' => [
                        'mimeType' => $mimeType,
                        'data' => $imageBase64
                    ]
                ];
                
                Log::info('Gemini image editing mode', [
                    'has_input_image' => true,
                    'mime_type' => $mimeType
                ]);
            }
        }
        
        // Add text prompt
        $parts[] = ['text' => $prompt];
        
        $payload = [
            'contents' => [
                [
                    'parts' => $parts,
                    'role' => 'user'
                ]
            ],
            'generationConfig' => [
                'responseModalities' => ['IMAGE'], // Only need IMAGE for editing/generation
                'temperature' => 1.0,
                'topK' => 40,
                'topP' => 0.95,
            ]
        ];
        
        Log::info('Gemini API request', [
            'has_image' => !empty($imagePath),
            'parts_count' => count($parts),
            'prompt_length' => strlen($prompt)
        ]);
        
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 90, // Increased for image editing
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Gemini API request failed: ' . $error);
        }
        
        curl_close($ch);

        if ($httpCode !== 200) {
            Log::error('Gemini image generation failed', [
                'http_code' => $httpCode,
                'response' => substr($response, 0, 500)
            ]);
            throw new \Exception('Gemini API returned error: ' . $httpCode);
        }

        $data = json_decode($response, true);
        
        if (!isset($data['candidates'][0]['content']['parts'])) {
            throw new \Exception('Unexpected API response format from Gemini');
        }
        
        // Find the image part
        $imagePart = null;
        foreach ($data['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['inlineData']['data'])) {
                $imagePart = $part;
                break;
            }
        }
        
        if (!$imagePart) {
            throw new \Exception('No image data found in Gemini response');
        }
        
        // Decode base64 image
        $imageData = base64_decode($imagePart['inlineData']['data']);
        
        if ($imageData === false) {
            throw new \Exception('Failed to decode Gemini image data');
        }
        
        // Save to temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'gemini_image_');
        file_put_contents($tempFile, $imageData);
        
        Log::info('Gemini image generated successfully', [
            'temp_file' => $tempFile,
            'size' => strlen($imageData)
        ]);
        
        return $tempFile;
    }

    /**
     * Convert image to base64 for Gemini API
     * Handles local files, URLs, and temp files
     * 
     * @param string $imagePath Path to image (local file or URL)
     * @return string|null Base64 encoded image data (without data URL prefix)
     */
    private function convertImageToBase64($imagePath)
    {
        try {
            $imageData = null;
            
            // Check if it's a URL
            if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                // Download from URL
                $imageData = file_get_contents($imagePath);
                
                if ($imageData === false) {
                    Log::warning('Failed to download image from URL', ['url' => $imagePath]);
                    return null;
                }
            } 
            // Check if it's a local file
            elseif (file_exists($imagePath)) {
                $imageData = file_get_contents($imagePath);
                
                if ($imageData === false) {
                    Log::warning('Failed to read image file', ['path' => $imagePath]);
                    return null;
                }
            }
            else {
                Log::warning('Image path not found', ['path' => $imagePath]);
                return null;
            }
            
            // Encode to base64
            return base64_encode($imageData);
            
        } catch (\Exception $e) {
            Log::error('Error converting image to base64', [
                'error' => $e->getMessage(),
                'path' => $imagePath
            ]);
            return null;
        }
    }

    /**
     * Get the previous image URL from conversation history
     * Used for multi-turn image editing
     * 
     * @param array $conversationHistory Array of conversation messages
     * @return string|null URL of the most recent image
     */
    private function getPreviousImageFromConversation($conversationHistory)
    {
        if (empty($conversationHistory)) {
            return null;
        }
        
        // Search backwards through conversation for the most recent image
        for ($i = count($conversationHistory) - 1; $i >= 0; $i--) {
            $message = $conversationHistory[$i];
            
            // Check if it's an assistant message with image URL
            if (isset($message['role']) && $message['role'] === 'assistant') {
                if (isset($message['content']) && $this->isImageURL($message['content'])) {
                    Log::info('Found previous image in conversation', [
                        'image_url' => $message['content']
                    ]);
                    return $message['content'];
                }
            }
        }
        
        return null;
    }

    /**
     * Check if a string is an image URL
     * 
     * @param string $str String to check
     * @return bool True if string is an image URL
     */
    private function isImageURL($str)
    {
        if (!is_string($str) || empty($str)) {
            return false;
        }
        
        // Check if it's a URL
        if (!filter_var($str, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Check if URL ends with image extension
        $path = parse_url($str, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }

    // Add this method to extract text from PDF (missing method causing the error)
    private function extractTextFromPDF($filePath)
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            
            // Limit text to prevent token overflow
            return substr($text, 0, 8000);
        } catch (\Exception $e) {
            Log::error('PDF extraction error: ' . $e->getMessage());
            return '[Error extracting PDF content]';
        }
    }

    // Add this method to extract text from DOCX
    private function extractTextFromDOCX($filePath)
    {
        try {
            $phpWord = WordIOFactory::load($filePath);
            $text = '';
            
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . "\n";
                    } elseif (method_exists($element, 'getElements')) {
                        foreach ($element->getElements() as $child) {
                            if (method_exists($child, 'getText')) {
                                $text .= $child->getText() . "\n";
                            }
                        }
                    }
                }
            }
            
            return substr($text, 0, 8000);
        } catch (\Exception $e) {
            Log::error('DOCX extraction error: ' . $e->getMessage());
            return '[Error extracting DOCX content]';
        }
    }

    // Add this helper to convert image to base64 for API calls
    private function imageToBase64($imageUrl)
    {
        try {
            $imageData = file_get_contents($imageUrl);
            return base64_encode($imageData);
        } catch (\Exception $e) {
            Log::error('Image conversion error: ' . $e->getMessage());
            return null;
        }
    }

    private function processModelStream($model, $messages, $useWebSearch, &$responses)
    {
        if ($this->isClaudeModel($model)) {
            $this->processClaudeModel($model, $messages, $useWebSearch, $responses);
        } elseif ($this->isGeminiModel($model)) {
            $this->processGeminiModel($model, $messages, $useWebSearch, $responses);
        } elseif ($this->isGrokModel($model)) {
            $this->processGrokModel($model, $messages, $useWebSearch, $responses);
        } else {
            $this->processOpenAIModel($model, $messages, $useWebSearch, $responses);
        }
    }

    private function processOpenAIModel($model, $messages, $useWebSearch, &$responses)
    {
        $apiKey = config('services.openai.api_key');
        
        $modelToUse = $useWebSearch ? 'gpt-4o-search-preview' : $model;
        
        $payload = [
            'model' => $modelToUse,
            'messages' => $messages,
            'stream' => true,
            'temperature' => 0.7,
            'max_tokens' => 4096,
        ];

        Log::info("Starting OpenAI request", [
            'model' => $modelToUse,
            'message_count' => count($messages),
            'api_key_length' => strlen($apiKey)
        ]);

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($model, &$responses) {
                return $this->handleStreamChunk($model, $data, $responses, 'openai');
            }
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    // Keep the buffers for handling incomplete chunks
    private static $buffers = [];
    private static $chunkCounts = [];

    private function handleStreamChunk($model, $data, &$responses, $provider)
    {
        // Initialize buffer and chunk count if not exists
        if (!isset(self::$buffers[$model])) {
            self::$buffers[$model] = '';
            self::$chunkCounts[$model] = 0;
        }

        $buffers = &self::$buffers;
        $chunkCounts = &self::$chunkCounts;

        $buffers[$model] .= $data;
        $lines = explode("\n", $buffers[$model]);
        $buffers[$model] = array_pop($lines);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line === 'data: [DONE]') {
                continue;
            }

            if (strpos($line, 'data: ') === 0) {
                $json = substr($line, 6);
                
                try {
                    $decoded = json_decode($json, true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::warning("JSON parse error for {$provider}: " . json_last_error_msg());
                        continue;
                    }
                    
                    $content = '';
                    
                    if ($provider === 'claude') {
                        if (isset($decoded['type']) && $decoded['type'] === 'content_block_delta') {
                            $content = $decoded['delta']['text'] ?? '';
                        }
                    } elseif ($provider === 'gemini') {
                        if (isset($decoded['candidates'][0]['content']['parts'])) {
                            foreach ($decoded['candidates'][0]['content']['parts'] as $part) {
                                if (isset($part['text'])) {
                                    $content .= $part['text'];
                                }
                            }
                        }
                    } elseif ($provider === 'openai') {
                        if (isset($decoded['choices'][0]['delta']['content'])) {
                            $content = $decoded['choices'][0]['delta']['content'];
                            $chunkCounts[$model]++;
                            
                            // Log every 10th chunk for OpenAI
                            if ($chunkCounts[$model] % 10 === 0) {
                                Log::debug("OpenAI chunk #{$chunkCounts[$model]} for {$model}: " . strlen($content) . " chars");
                            }
                        }
                    } elseif ($provider === 'grok') {
                        if (isset($decoded['choices'][0]['delta']['content'])) {
                            $content = $decoded['choices'][0]['delta']['content'];
                        }
                    }

                    if ($content !== '') {
                        $responses[$model] .= $content;

                        echo "data: " . json_encode([
                            'type' => 'chunk',
                            'model' => $model,
                            'content' => $content,
                            'full_response' => $responses[$model],
                            'provider' => $provider,
                            'chunk_count' => $chunkCounts[$model] ?? 0
                        ]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                    
                } catch (\Exception $e) {
                    Log::error("Error parsing JSON for {$model} ({$provider}): " . $e->getMessage());
                    Log::error("Raw JSON (first 200 chars): " . substr($json, 0, 200));
                }
            }
        }

        return strlen($data);
    }

    private function processClaudeModel($model, $messages, $useWebSearch, &$responses)
    {
        $claudeData = $this->convertMessagesToClaudeFormat($messages);
        
        $payload = [
            'model' => $model,
            'messages' => $claudeData['messages'],
            'max_tokens' => 4096,
            'stream' => true,
        ];

        if ($claudeData['system']) {
            $payload['system'] = $claudeData['system'];
        }

        if ($useWebSearch) {
            $payload['tools'] = [
                [
                    'type' => 'web_search_20250305',
                    'name' => 'web_search',
                    'max_uses' => 5
                ]
            ];
        }

        $apiKey = config('services.anthropic.api_key');
        
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($model, &$responses) {
                return $this->handleStreamChunk($model, $data, $responses, 'claude');
            }
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    private function processGeminiModel($model, $messages, $useWebSearch, &$responses)
    {
        $geminiMessages = $this->convertMessagesToGeminiFormat($messages);
        $apiKey = config('services.gemini.api_key');
        
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?alt=sse&key={$apiKey}";
        
        $payload = [
            'contents' => $geminiMessages['contents'],
            'generationConfig' => [
                'temperature' => 1.0,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 8192,
            ],
        ];
        
        if ($geminiMessages['systemInstruction']) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $geminiMessages['systemInstruction']]
                ]
            ];
        }
        
        if ($useWebSearch) {
            if (str_contains($model, '2.') || str_contains($model, 'flash-002')) {
                $payload['tools'] = [['google_search' => (object)[]]];
            } else {
                $payload['tools'] = [[
                    'googleSearchRetrieval' => [
                        'dynamicRetrievalConfig' => [
                            'mode' => 'MODE_DYNAMIC',
                            'dynamicThreshold' => 0.7
                        ]
                    ]
                ]];
            }
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($model, &$responses) {
                return $this->handleStreamChunk($model, $data, $responses, 'gemini');
            }
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    private function processGrokModel($model, $messages, $useWebSearch, &$responses)
    {
        $apiKey = config('services.xai.api_key');
        
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
            'temperature' => 0.7,
            'max_tokens' => 4096,
        ];
        
        if ($useWebSearch) {
            $currentDate = date('Y-m-d');
            $lastMessage = end($messages);
            if ($lastMessage && $lastMessage['role'] === 'user') {
                $originalContent = $lastMessage['content'];
                $messages[count($messages) - 1]['content'] = 
                    "Current date: {$currentDate}. " . 
                    "Please search for and use only the most recent, up-to-date information when answering: " . 
                    $originalContent;
            }
            $payload['messages'] = $messages;
        }

        $ch = curl_init('https://api.x.ai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($model, &$responses) {
                return $this->handleStreamChunk($model, $data, $responses, 'grok');
            }
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    private function saveMultiCompareConversation($conversation, $userMessage, $allResponses, $uploadedFilePath = null, $fileContentMessage = null)
    {
        try {
            Log::info('Starting to save multi-compare conversation', [
                'conversation_id' => $conversation->id,
                'user_message_length' => strlen($userMessage),
                'responses_count' => count($allResponses),
                'has_file' => !is_null($uploadedFilePath),
                'responses_summary' => array_map(function($response) {
                    return [
                        'length' => strlen($response),
                        'preview' => substr($response, 0, 50)
                    ];
                }, $allResponses)
            ]);

            // Save user message
            $userMessageRecord = MultiCompareMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $userMessage,
            ]);

            Log::info('User message saved', ['message_id' => $userMessageRecord->id]);

            // ✅ Save file attachment if present
            if ($uploadedFilePath && $userMessageRecord) {
                if (is_array($uploadedFilePath) && isset($uploadedFilePath['file_path'], $uploadedFilePath['file_name'])) {
                    MultiCompareAttachment::create([
                        'message_id' => $userMessageRecord->id,
                        'file_path' => $uploadedFilePath['file_path'],
                        'file_name' => $uploadedFilePath['file_name'],
                        'file_type' => pathinfo($uploadedFilePath['file_name'], PATHINFO_EXTENSION),
                    ]);
                    
                    Log::info('Attachment saved successfully', [
                        'message_id' => $userMessageRecord->id,
                        'file_name' => $uploadedFilePath['file_name'],
                        'file_path' => $uploadedFilePath['file_path']
                    ]);
                } else {
                    Log::warning('Invalid uploadedFilePath structure', [
                        'uploadedFilePath' => $uploadedFilePath
                    ]);
                }
            }

            // ✅ Save system message with file content if present
            if ($fileContentMessage && $fileContentMessage['role'] === 'system') {
                MultiCompareMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'system',
                    'content' => is_array($fileContentMessage['content']) 
                        ? json_encode($fileContentMessage['content']) 
                        : $fileContentMessage['content'],
                ]);
                
                Log::info('System message (file content) saved');
            }

            // ✅ Save assistant responses - CRITICAL FIX
            if (!empty($allResponses)) {
                $assistantMessage = MultiCompareMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => json_encode($allResponses),
                    'all_responses' => $allResponses,
                ]);
                
                Log::info('Assistant responses saved successfully', [
                    'conversation_id' => $conversation->id,
                    'message_id' => $assistantMessage->id,
                    'response_count' => count($allResponses),
                    'models' => array_keys($allResponses),
                    'content_length' => strlen($assistantMessage->content)
                ]);
            } else {
                Log::error('No responses to save - allResponses is empty!', [
                    'conversation_id' => $conversation->id,
                    'allResponses' => $allResponses
                ]);
            }

            // Update conversation title if it's still default
            if ($conversation->title === 'New Comparison' || $conversation->title === 'Untitled Comparison') {
                $conversation->update([
                    'title' => $this->generateConversationTitle($userMessage)
                ]);
            }

            // Update conversation timestamp
            $conversation->touch();

            Log::info('Multi-compare conversation saved successfully', [
                'conversation_id' => $conversation->id,
                'total_messages' => $conversation->messages()->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error saving multi-compare conversation', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'allResponses_keys' => array_keys($allResponses ?? []),
            ]);
            throw $e;
        }
    }

    private function generateConversationTitle($message)
    {
        // Generate a meaningful title from the first message
        $title = Str::limit($message, 50, '...');
        $title = trim(preg_replace('/[^\w\s-]/', '', $title));
        
        if (empty($title)) {
            $title = 'Untitled Comparison';
        }
        
        return $title;
    }

    // Update getMultiCompareChats to support archived filter
    public function getMultiCompareChats(Request $request)
    {
        $showArchived = $request->input('show_archived', false);
        
        $query = MultiCompareConversation::where('user_id', auth()->id())
            ->with(['messages' => function($query) {
                $query->where('role', 'user')->latest()->limit(1);
            }])
            ->withCount('messages')
            ->orderBy('updated_at', 'desc');
        
        // Filter by archived status
        if ($showArchived === 'only') {
            $query->where('archived', true);
        } elseif ($showArchived === false || $showArchived === 'false') {
            $query->where('archived', false);
        }
        // If 'all', don't filter
        
        $conversations = $query->get();

        return response()->json($conversations->map(function ($conversation) {
            $lastUserMessage = $conversation->messages->first();
            
            return [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'selected_models' => $conversation->selected_models,
                'last_user_message' => $lastUserMessage ? $lastUserMessage->content : null,
                'message_count' => $conversation->messages_count,
                'optimization_mode' => $conversation->optimization_mode ?? 'fixed',
                'archived' => $conversation->archived ?? false,
                'updated_at' => $conversation->updated_at->toISOString(),
            ];
        }));
    }

    public function getMultiCompareConversation($id)
    {
        $conversation = MultiCompareConversation::where('id', $id)
            ->where('user_id', auth()->id())
            ->with(['messages' => function($query) {
                $query->with('attachment')->orderBy('created_at');
            }])
            ->firstOrFail();

        return response()->json([
            'id' => $conversation->id,
            'title' => $conversation->title,
            'selected_models' => $conversation->selected_models,
            'optimization_mode' => $conversation->optimization_mode ?? 'fixed', // ✅ ADD THIS
            'messages' => $conversation->messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'all_responses' => $message->all_responses,
                    'created_at' => $message->created_at->toISOString(),
                    'attachment' => $message->attachment ? [
                        'url' => rtrim(config('filesystems.disks.azure.url'), '/') . '/' . 
                                config('filesystems.disks.azure.container') . '/' . 
                                $message->attachment->file_path,
                        'name' => $message->attachment->file_name,
                        'type' => $message->attachment->file_type,
                    ] : null
                ];
            }),
            'updated_at' => $conversation->updated_at->toISOString(),
        ]);
    }

    public function deleteMultiCompareConversation($id)
    {
        $conversation = MultiCompareConversation::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $conversation->delete();

        return response()->json(['message' => 'Conversation deleted successfully']);
    }

    public function updateMultiCompareConversationTitle($id, Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $conversation = MultiCompareConversation::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $conversation->update([
            'title' => $request->input('title'),
        ]);

        return response()->json(['message' => 'Conversation title updated successfully']);
    }

    public function translateText(Request $request)
    {
        try {
            $request->validate([
                'text' => 'required|string',
                'target_lang' => 'required|string'
            ]);

            $text = $request->input('text');
            $targetLang = $request->input('target_lang');
            
            // ✅ FIXED: Remove the extra ->client() call
            $response = OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "You are a professional translator. Translate the following text to {$targetLang}. Provide ONLY the translation, without any explanations, notes, or additional text."
                    ],
                    [
                        'role' => 'user',
                        'content' => $text
                    ]
                ],
                'max_tokens' => 2000,
                'temperature' => 0.3,
            ]);
            
            $translatedText = $response->choices[0]->message->content;
            
            return response()->json([
                'translatedText' => trim($translatedText)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Translation error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Translation service error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function searchMultiCompareConversations(Request $request)
    {
        $searchTerm = $request->input('search', '');
        $userId = auth()->id();
        
        if (empty($searchTerm)) {
            // Return all conversations if no search term
            $conversations = DB::table('multi_compare_conversations')
                ->where('user_id', $userId)
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($conv) {
                    $conv->selected_models = json_decode($conv->selected_models, true) ?? [];
                    return $conv;
                });
            
            return response()->json($conversations);
        }
        
        // Search in both conversation titles and message content
        $conversations = DB::table('multi_compare_conversations as c')
            ->where('c.user_id', $userId)
            ->where(function($query) use ($searchTerm) {
                // Search in conversation title
                $query->where('c.title', 'LIKE', "%{$searchTerm}%")
                    // OR search in message content
                    ->orWhereExists(function($query) use ($searchTerm) {
                        $query->select(DB::raw(1))
                            ->from('multi_compare_messages as m')
                            ->whereColumn('m.conversation_id', 'c.id')
                            ->where('m.content', 'LIKE', "%{$searchTerm}%");
                    });
            })
            ->orderBy('c.updated_at', 'desc')
            ->select('c.*')
            ->groupBy('c.id') // ✅ CHANGED: Use groupBy instead of distinct
            ->get()
            ->map(function ($conv) {
                $conv->selected_models = json_decode($conv->selected_models, true) ?? [];
                return $conv;
            });
        
        return response()->json($conversations);
    }

    // Archive/Unarchive single conversation
public function toggleArchiveMultiCompareConversation($id)
{
    $conversation = MultiCompareConversation::where('id', $id)
        ->where('user_id', auth()->id())
        ->firstOrFail();

    $conversation->update([
        'archived' => !$conversation->archived
    ]);

    return response()->json([
        'message' => $conversation->archived ? 'Conversation archived' : 'Conversation unarchived',
        'archived' => $conversation->archived
    ]);
}

// Bulk delete conversations
public function bulkDeleteMultiCompareConversations(Request $request)
{
    $request->validate([
        'conversation_ids' => 'required|array',
        'conversation_ids.*' => 'exists:multi_compare_conversations,id'
    ]);

    $deleted = MultiCompareConversation::whereIn('id', $request->conversation_ids)
        ->where('user_id', auth()->id())
        ->delete();

    return response()->json([
        'message' => "{$deleted} conversation(s) deleted successfully",
        'deleted_count' => $deleted
    ]);
}

// Bulk archive/unarchive conversations
public function bulkArchiveMultiCompareConversations(Request $request)
{
    $request->validate([
        'conversation_ids' => 'required|array',
        'conversation_ids.*' => 'exists:multi_compare_conversations,id',
        'archive' => 'required|boolean'
    ]);

    $updated = MultiCompareConversation::whereIn('id', $request->conversation_ids)
        ->where('user_id', auth()->id())
        ->update(['archived' => $request->archive]);

    $action = $request->archive ? 'archived' : 'unarchived';
    
    return response()->json([
        'message' => "{$updated} conversation(s) {$action} successfully",
        'updated_count' => $updated
    ]);
}
}
