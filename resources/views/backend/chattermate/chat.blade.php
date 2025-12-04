<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Chattermate AI Chatbot</title>
    @include('admin.layouts.analytics')
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/github.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    
    <!-- ✅ ADD: MathJax for mathematical expressions -->
    <script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    
    <!-- ✅ ADD: Chart.js for graphs and charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <link rel="shortcut icon" type="image/png" href="{{ config('filesystems.disks.azure.url') . config('filesystems.disks.azure.container') . '/' . $siteSettings->favicon }}">
    
    <!-- ✅ ADD: MathJax Configuration -->
    <script>
        window.MathJax = {
            tex: {
                inlineMath: [['$', '$'], ['\\(', '\\)']],
                displayMath: [['$$', '$$'], ['\\[', '\\]']],
                processEscapes: true,
                processEnvironments: true
            },
            options: {
                skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre']
            }
        };
    </script>


    <style>

/* ✅ ADD: Chart container styles */
        .chart-container {
            position: relative;
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .chart-canvas {
            max-width: 100%;
            height: 400px !important;
        }
        
        /* Math expression styles */
        .math-block {
            overflow-x: auto;
            padding: 10px;
            margin: 10px 0;
        }

        .gradient-bg-1{
            background: linear-gradient(to right, #1a0a24, #3a0750);
        }
        .chat-hover:hover {
            background: linear-gradient(45deg, #5447c4, #ac31a1);
        }

        .message-content pre {
            background-color: #f6f8fa;
            border-radius: 6px;
            padding: 16px;
            margin: 12px 0;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .message-content code {
            background-color: rgba(175,184,193,0.2);
            border-radius: 6px;
            padding: 0.2em 0.4em;
            font-size: 85%;
        }
        .message-content pre code {
            background-color: transparent;
            padding: 0;
            border-radius: 0;
        }
        .message-content table {
            border-collapse: collapse;
            width: 100%;
            margin: 12px 0;
        }
        .message-content th, .message-content td {
            border: 1px solid #dfe2e5;
            padding: 6px 13px;
        }
        .message-content tr:nth-child(2n) {
            background-color: #f6f8fa;
        }
        .message-content blockquote {
            border-left: 4px solid #dfe2e5;
            color: #6a737d;
            padding: 0 1em;
            margin: 0 0 16px 0;
        }
        .sidebar {
            transition: transform 0.3s ease;
        }
        .sidebar-hidden {
            transform: translateX(-100%);
        }
        .sidebar-visible {
            transform: translateX(0);
        }
        .conversation-item {
            transition: background-color 0.2s ease;
        }

        .delete-conversation-btn {
            transition: opacity 0.2s ease, color 0.2s ease;
        }

        .delete-conversation-btn:hover {
            color: #ef4444 !important; /* red-500 */
        }
        /* @media (min-width: 1536px) {
            .sidebar {
                transform: translateX(0);
            }
        } */
        /* Add these new styles to your existing style section */
        .code-block-container {
            position: relative;
        }
        .code-block-container:hover .copy-code-button {
            opacity: 1;
        }
        .copy-code-button {
            position: absolute;
            right: 8px;
            top: 8px;
            opacity: 0;
            transition: opacity 0.2s ease;
            background-color: #f6f8fa;
            border: 1px solid #e1e4e8;
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 12px;
            cursor: pointer;
        }
        .copy-code-button:hover {
            background-color: #e1e4e8;
        }
        .copy-code-button.copied {
            background-color: #e6ffed;
            border-color: #2ea043;
            color: #2ea043;
        }
        
        /* New styles for textarea and input area */
        .input-area {
            position: relative;
            width: 100%;
        }
        #message-input {
            resize: none;
            min-height: 44px;
            max-height: 200px;
            overflow-y: auto;
            line-height: 1.5;
            padding-right: 60px; /* Space for the send button */
        }
        #send-button {
            position: absolute;
            right: 12px;
            bottom: 12px;
        }
        .user-message {
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        /* Message action buttons */
        .message-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 12px;
        }

        .message-action-btn {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 4px;
            border: 1px solid transparent;
        }

        .copy-all-button {
            background-color: #f3f4f6;
            border-color: #e5e7eb;
            color: #374151;
        }

        .copy-all-button:hover {
            background-color: #e5e7eb;
        }

        .regenerate-button {
            background-color: #3b82f6;
            color: white;
            border-color: #2563eb;
        }

        .regenerate-button:hover {
            background-color: #2563eb;
        }

        /* Existing messages might need some spacing adjustment */
        .bg-white.border {
            padding-bottom: 32px; /* Make space for buttons */
        }

        /* ChatGPT-like link styling */
        a {
            color: #1d4ed8;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        a:hover {
            color: #1e40af;
            text-decoration: underline;
        }

        /* External link icon */
        a > span {
            font-size: 0.75em;
            vertical-align: super;
            line-height: 1;
        }

        /* ANimation Loader Stream */
       @keyframes bounce-delay {
        0%, 80%, 100% { transform: scale(0); }
        40% { transform: scale(1); }
        }

        .thinking-indicator .dot {
        animation: bounce-delay 1.4s infinite ease-in-out both;
        box-shadow: 0 0 8px rgba(139, 92, 246, 0.6);
        background: linear-gradient(135deg, #a78bfa, #8b5cf6);
        }

        .thinking-indicator .dot:nth-child(1) { animation-delay: -0.32s; }
        .thinking-indicator .dot:nth-child(2) { animation-delay: -0.16s; }
        .thinking-indicator .dot:nth-child(3) { animation-delay: 0s; }

        /* AUTO OPTIMIZE */
@keyframes checkbox-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}
.checkbox-changed {
    animation: checkbox-pulse 0.3s ease-in-out;
}

/* Compact sidebar layout */
.provider-section {
    font-size: 13px;
    color: #ddd;
}

/* Option line */
.provider-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 3px 4px;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.2s ease;
}
.provider-option:hover {
    background-color: rgba(255, 255, 255, 0.05);
}

.provider-option input {
    margin-right: 6px;
}

.provider-title {
    display: flex;
    align-items: center;
    gap: 4px;
    font-weight: 500;
    color: #e5e5e5;
    font-size: 13px;
    white-space: nowrap;
}

.pro-badge {
    background-color: #7e22ce;
    color: #fff;
    font-size: 10px;
    padding: 1px 4px;
    border-radius: 3px;
    font-weight: 600;
}

/* Tooltip styling */
.tooltip {
    position: relative;
    display: inline-block;
    cursor: help;
}

.tooltip .tooltip-text {
    visibility: hidden;
    opacity: 0;
    width: max-content;
    max-width: 220px;
    background-color: #2d2d2d;
    color: #ccc;
    text-align: left;
    border-radius: 4px;
    padding: 5px 8px;
    position: absolute;
    z-index: 10;
    bottom: 125%;
    left: 50%;
    transform: translateX(-50%);
    font-size: 11.5px;
    line-height: 1.3;
    box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    transition: opacity 0.2s ease;
}

.tooltip:hover .tooltip-text {
    visibility: visible;
    opacity: 1;
}

/* Info icon tooltip */
.info-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-left: 6px;
    color: #888;
}
.info-icon:hover {
    color: #aaa;
}
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        
        <!-- Sidebar -->
       <div class="sidebar gradient-bg-1 text-white w-64 flex-shrink-0 fixed xl:relative h-full z-10 sidebar-visible" style="position: fixed" id="sidebar">
            <div class="p-4 flex flex-col h-full">
                <!-- Added logo here -->
                <div class="flex items-center justify-center mb-6">
                    <a href="{{ route('home') }}" class="logo-lg fw-bold fs-4 text-light text-xl font-bold hover:text-white transition-colors duration-200">
                        Clever Creator AI
                    </a>
                </div>

                <!-- Credits/Tokens Stats -->
                <div class="flex items-center justify-center mb-4 px-3">
                    <div class="flex items-center justify-between w-full px-3 py-2 rounded-lg"
                        style="background: linear-gradient(135deg, #7b2ff7, #f107a3); color: #fff;">
                        <div class="text-center">
                            <small>Credits Left</small>
                            <div id="credits_left" class="font-bold">{{ Auth::user()->credits_left }}</div>
                        </div>
                        <div class="h-6 border-l border-white/40 mx-2"></div>
                        <div class="text-center">
                            <small>Tokens Left</small>
                            <div id="tokens_left" class="font-bold">{{ Auth::user()->tokens_left }}</div>
                        </div>
                    </div>
                </div>

                <a href="{{ Auth::check() ? (Auth::user()->role === 'admin' ? route('admin.dashboard') : route('user.dashboard')) : route('login') }}" class="flex items-center justify-between w-full p-3 rounded-md border border-gray-700 chat-hover mb-4" title="Go to Dashboard">
                    <span class="flex items-center text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10.707 1.293a1 1 0 00-1.414 0l-7 7A1 1 0 003 9h1v7a1 1 0 001 1h4a1 1 0 001-1V13h2v3a1 1 0 001 1h4a1 1 0 001-1V9h1a1 1 0 00.707-1.707l-7-7z" />
                        </svg>
                        Dashboard
                    </span>
                </a>

                <!-- Rest of your sidebar content remains the same -->
                <button id="new-chat-btn" class="flex items-center justify-between w-full p-3 rounded-md border border-gray-700 chat-hover mb-4" title="Start New Chat">
                    <span class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        New chat
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                    </svg>
                </button>
                
                <div class="flex-1 overflow-y-auto" id="chat-history">
                    <!-- Chat history will be loaded here -->
                    <div class="space-y-2">
                        <div class="p-3 rounded-md chat-hover cursor-pointer flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd" />
                            </svg>
                            <span class="truncate">Loading chats...</span>
                        </div>
                    </div>
                </div>

                <!-- Expert Mode: Compact and Professional -->
                <div class="mb-4 px-3 py-2">
                    <label class="block text-xs font-semibold text-gray-300 mb-2">Expert Mode</label>
                    
                    <div class="flex flex-wrap gap-2 items-center" id="expert-container">
                        @foreach($experts->take(4) as $expert)
                         @if(auth()->user()->hasExpertAccess($expert->id))
                        <div class="expert-card flex items-center justify-center w-8 h-8 rounded-full bg-purple-600 hover:bg-purple-700 text-white text-xs font-medium cursor-pointer transition"
                            data-expert-id="{{ $expert->id }}"
                            data-system-message="{{ $expert->expertise }}"
                            data-domain="{{ $expert->domain }}"
                            title="{{ $expert->expert_name }}">
                            @if($expert->icon)
                                <div class="w-4 h-4">
                                    {!! $expert->icon !!}
                                </div>
                            @else
                                {{ \Illuminate\Support\Str::limit($expert->expert_name, 1, '') }}
                            @endif
                        </div>
                        @endif
                        @endforeach

                        @if($experts->count() > 4)
                        <button id="show-more-btn" type="button"
                            class="w-8 h-8 rounded-full bg-purple-100 hover:bg-purple-200 text-purple-700 text-xs font-bold transition">
                            ...
                        </button>
                        @endif
                    </div>

                    <div id="more-experts" class="hidden mt-2 flex flex-wrap gap-2">
                        @foreach($experts->skip(4) as $expert)
                         @if(auth()->user()->hasExpertAccess($expert->id))
                        <div class="expert-card flex items-center justify-center w-8 h-8 rounded-full bg-purple-600 hover:bg-purple-700 text-white text-xs font-medium cursor-pointer transition"
                            data-expert-id="{{ $expert->id }}"
                            data-system-message="{{ $expert->expertise }}"
                            data-domain="{{ $expert->domain }}"
                            title="{{ $expert->expert_name }}">
                            @if($expert->icon)
                                <div class="w-4 h-4">
                                    {!! $expert->icon !!}
                                </div>
                            @else
                                {{ \Illuminate\Support\Str::limit($expert->expert_name, 1, '') }}
                            @endif
                        </div>
                        @endif
                        @endforeach
                    </div>

                    <input type="hidden" name="expert_id" id="expert-id">
                </div>
                
                <div class="pt-2 border-t border-gray-700 relative">
                    @php
                        use App\Models\AISettings;
                        
                        // Common variables
                        $selectedLabel = 'Select AI Model';
                        $aiModels = [];
                        $selectedModel = null;
                        $tokensLeft = Auth::user()->tokens_left;
                        $isLocked = $tokensLeft == 0;
                        $defaultModel = app('siteSettings')->default_model;

                        if (Auth::check()) {
                            if (Auth::user()->role === 'admin') {
                                // Admin: Get all available models
                                $models = AISettings::active()
                                    ->whereNotNull('openaimodel')
                                    ->pluck('displayname', 'openaimodel')
                                    ->unique()
                                    ->toArray();

                                $selectedModel = Auth::user()->selected_model ?? 'gpt-4o-mini';
                                $aiModels = collect($models)->map(function($label, $value) {
                                    return ['value' => $value, 'label' => $label];
                                })->values()->toArray();
                                
                                $selectedLabel = $models[$selectedModel] ?? 'Select AI Model';
                            } else {
                                $selectedModel = Auth::user()->selected_model ?? 'gpt-4o-mini';
                                $selectedLabel = $selectedModel;
                            }
                        }
                    @endphp

                    <!-- Rest of the template remains the same -->
                    <div id="dropdownTrigger" class="p-3 rounded-md chat-hover cursor-pointer flex items-center justify-between">
                        <div class="flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-300" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 2a1 1 0 01.993.883L11 3v1.071A7.002 7.002 0 0116.938 9H18a1 1 0 01.117 1.993L18 11h-1.071A7.002 7.002 0 0111 16.938V18a1 1 0 01-1.993.117L9 18v-1.071A7.002 7.002 0 013.062 11H2a1 1 0 01-.117-1.993L2 9h1.071A7.002 7.002 0 019 3.062V2a1 1 0 011-1zm0 5a3 3 0 100 6 3 3 0 000-6z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-gray-200">
                                {{ $selectedLabel }}
                                @if($isLocked)
                                    <small class="text-orange-400 ml-2">(Limited)</small>
                                @endif
                            </span>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>

                    @if (Auth::user()->role === 'admin')
                        <form id="modelForm" action="{{ route('select-model') }}" method="POST" class="absolute bottom-full mb-2 left-0 w-full z-20 hidden">
                            @csrf
                            <ul class="bg-gray-800 text-white rounded-md border border-gray-700 overflow-y-auto shadow-lg max-h-60 sm:max-h-80">
                                @foreach ($aiModels as $model)
                                    @php
                                        $isDisabled = $isLocked && $model['value'] !== $defaultModel;
                                        $isSelected = trim($selectedModel) === trim($model['value']);
                                    @endphp
                                    <li>
                                        <a href="#"
                                            data-model="{{ $model['value'] }}"
                                            class="dropdown-item block px-4 py-2 text-sm {{ $isDisabled ? 'text-gray-500 cursor-not-allowed' : 'text-gray-300 hover:text-white' }} {{ $isSelected ? 'bg-gray-700 font-semibold' : '' }}"
                                            @if($isDisabled) style="pointer-events: none; opacity: 0.5;" @endif>
                                            {{ ucfirst($model['label']) }}
                                            @if($isSelected)
                                                <span class="ml-2 text-green-400">✓</span>
                                            @endif
                                            @if($isDisabled)
                                                <span class="ml-2 text-red-400">🔒</span>
                                            @elseif($model['value'] === $defaultModel && $isLocked)
                                                <span class="ml-2 text-blue-400">(Default)</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                            <input type="hidden" name="aiModel" id="aiModelInput" value="{{ $selectedModel }}">
                        </form>
                    @else
                        <form id="modelForm" action="{{ route('select-model') }}" method="POST" class="absolute bottom-full mb-2 left-0 w-full z-20 hidden">
                            @csrf
                            <ul class="bg-gray-800 text-white rounded-md border border-gray-700 overflow-y-auto shadow-lg max-h-60 sm:max-h-80">
                                @foreach (auth()->user()->aiModels() as $model)
                                    @php
                                        $isDisabled = $isLocked && $model !== $defaultModel;
                                        $isSelected = trim($selectedModel) === trim($model);
                                    @endphp
                                    <li>
                                        <a href="#"
                                            data-model="{{ $model }}"
                                            class="dropdown-item block px-4 py-2 text-sm {{ $isDisabled ? 'text-white-500 cursor-not-allowed' : 'text-gray-300 hover:text-white' }} {{ $isSelected ? 'bg-gray-700 font-semibold' : '' }}"
                                            @if($isDisabled) style="pointer-events: none; opacity: 0.5;" @endif>
                                            {{ ucfirst($model) }}
                                            @if($isSelected)
                                                <span class="ml-2 text-green-400">✓</span>
                                            @endif
                                            @if($isDisabled)
                                                <span class="ml-2 text-red-400">🔒</span>
                                            @elseif($model === $defaultModel && $isLocked)
                                                <span class="ml-2 text-blue-400">(Default)</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                            <input type="hidden" name="aiModel" id="aiModelInput" value="{{ $selectedModel }}">
                        </form>
                    @endif
                    
                   <!-- Auto-optimize and Cross-provider Selection Options -->

<div class="px-3 py-2 border-t border-gray-700 provider-section">
    <!-- Section title with info tooltip -->
    <div class="flex items-center justify-between mb-1">
        <span class="text-sm font-semibold text-gray-300">AI Optimization</span>
        <div class="tooltip">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 info-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="tooltip-text">May switch between OpenAI, Claude, Gemini, or Grok when enabled.</span>
        </div>
    </div>

    <!-- Auto-optimize -->
    <label class="provider-option" for="auto-optimize-checkbox">
        <div class="flex items-center">
            <input 
                type="checkbox" 
                id="auto-optimize-checkbox"
                class="w-4 h-4 text-purple-600 border-gray-600 rounded focus:ring-purple-500 focus:ring-offset-gray-900"
                checked
            >
            <div class="tooltip">
                <span class="provider-title">Auto-optimize (Same Provider)</span>
                <span class="tooltip-text">AI picks the best model from your current provider automatically.</span>
            </div>
        </div>
    </label>

    <!-- Cross-provider -->
    <label class="provider-option" for="cross-provider-checkbox">
        <div class="flex items-center">
            <input 
                type="checkbox" 
                id="cross-provider-checkbox"
                class="w-4 h-4 text-purple-600 border-gray-600 rounded focus:ring-purple-500 focus:ring-offset-gray-900"
            >
            <div class="tooltip">
                <span class="provider-title">Smart Selection (Any Provider)</span>
                <span class="pro-badge">Pro</span>
                <span class="tooltip-text">Lets AI switch between providers like OpenAI, Claude, Gemini, or Grok for the best result.</span>
            </div>
        </div>
    </label>
</div>
                </div>

                {{-- Settings --}}
                @include('components.side_profile_setting_dropdown')

            </div>
        </div>
        
        <!-- Main content -->
        <div class="flex-1 flex flex-col h-full overflow-hidden">
            <!-- Mobile header with menu button -->
                <div class=" fixed top-0 left-0 right-0 z-50 bg-gray-800 text-white p-4 flex items-center">
                    <button id="sidebar-toggle" class="mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h1 class="text-xl font-semibold">Chattermate</h1>
                </div>

            <!-- Expert Modal -->
            <div id="expert-modal" class="fixed inset-0 bg-black bg-opacity-40 z-50 hidden items-center justify-center">
                <div class="bg-white max-w-4xl w-full rounded-lg shadow-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Choose an Expert</h3>
                        <button id="close-modal" class="text-gray-600 hover:text-red-500 text-xl">&times;</button>
                    </div>
                    <div class="space-y-8 max-h-[70vh] overflow-y-auto">
                        @foreach($groupedExperts as $domain => $categories)
                            <div>
                                <h3 class="text-xl font-bold text-blue-700 capitalize mb-2">{{ str_replace('-', ' ', $domain) }}</h3>
                                @foreach($categories as $category => $experts)
                                    <div class="mb-4">
                                        <h4 class="text-purple-700 font-semibold mb-2">{{ $category ?: 'Uncategorized' }}</h4>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                          @foreach($experts as $expert)
                                            @if(auth()->user()->hasExpertAccess($expert->id))
                                                <div class="expert-card flex items-center p-3 border rounded-lg cursor-pointer hover:bg-blue-50 transition"
                                                    data-expert-id="{{ $expert->id }}"
                                                    data-domain="{{ $expert->domain }}"
                                                    data-system-message="{{ $expert->expertise }}">
                                                    @if($expert->icon)
                                                        <div class="w-8 h-8 mr-2">{!! $expert->icon !!}</div>
                                                    @elseif(!empty($expert->image))
                                                        <img src="{{ config('filesystems.disks.azure.url') . config('filesystems.disks.azure.container') . '/' . $expert->image }}"
                                                            alt="{{ $expert->expert_name }}" 
                                                            class="w-8 h-8 rounded-full mr-2 object-cover" />
                                                    @endif
                                                    <span>{{ $expert->expert_name }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            
            <!-- Chat area -->
           <div class="flex-1 overflow-y-auto p-4 bg-gray-50 xl:pt-10 pt-[72px]" style="position: relative" id="chat-container">
                <div class="max-w-3xl mx-auto space-y-4">
                    <!-- Messages will appear here -->
                    <div class="flex justify-center items-center h-full" id="empty-state">
                        <div class="text-center p-8">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                            </svg>
                            <h2 class="text-xl font-semibold text-gray-600 mt-4">Start a new conversation</h2>
                            <p class="text-gray-500 mt-2">Ask anything or try one of these examples:</p>
                            <div class="mt-6 space-y-3">
                                <button class="bg-white border rounded-lg p-3 w-full text-left hover:bg-gray-100 example-prompt">
                                    "Explain quantum computing in simple terms"
                                </button>
                                <button class="bg-white border rounded-lg p-3 w-full text-left hover:bg-gray-100 example-prompt">
                                    "Got any creative ideas for a 10 year old's birthday?"
                                </button>
                                <button class="bg-white border rounded-lg p-3 w-full text-left hover:bg-gray-100 example-prompt">
                                    "How do I make an HTTP request in JavaScript?"
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>  
            
            <!-- Input area -->
            <div class="p-4 bg-white border-t">
                <div class="max-w-3xl mx-auto">
                 <form id="chat-form" class="relative" enctype="multipart/form-data">
                    @csrf
                    <div class="input-area relative flex flex-col gap-2">
                        <!-- Attachment preview (unchanged) -->
                        <div id="attachment-preview" class="hidden flex items-center gap-2 bg-gray-100 text-sm px-3 py-2 rounded-lg border border-gray-300">
                            <svg class="h-4 w-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828L18 9.828M16 16h.01"></path>
                            </svg>
                            <span id="file-name" class="truncate max-w-[200px]">document.pdf</span>
                            <button type="button" id="remove-file" class="ml-auto text-gray-500 hover:text-red-500">✕</button>
                        </div>

                        <!-- Input area -->
                        <div class="relative w-full">
                            <!-- Hidden file input -->
                            <input type="file" name="pdf" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.webp,.gif,image/*" id="pdf-upload" class="hidden">

                            <!-- Dropdown toggle icon (left) -->
                            <button type="button" id="toggle-tools" title="Advance Tools"
                                class="absolute left-2 bottom-3 text-gray-600 hover:text-purple-600 focus:outline-none p-2">
                                <i class='las la-tools'></i>
                            </button>

                            <!-- Upload button (moved slightly to right to avoid overlap with dropdown icon) -->
                            <button type="button" id="trigger-upload" title="Upload File"
                                class="absolute left-10 bottom-3 bg-purple-500 text-white p-2 rounded-lg hover:bg-purple-600 focus:outline-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 8.25v6.25a4.25 4.25 0 11-8.5 0V6.5a2.75 2.75 0 115.5 0v7.25a1 1 0 11-2 0V8.25" />
                                </svg>
                            </button>

                             <!-- Add hidden input for editing -->
                            <input type="hidden" id="editing-message-id" name="editing_message_id" value="">

                            <div id="selected-tool-display" class="mt-2 text-sm text-gray-600 hidden">
                                <span class="font-medium">Selected Tool:</span> <span id="selected-tool-name">None</span>
                            </div>

                            <!-- Textarea -->
                            <textarea id="message-input" name="message" rows="1"
                                class="w-full p-3 pl-20 pr-12 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                                placeholder="Type your message..." autocomplete="off"></textarea>

                            <!-- Send Button -->
                            <button type="submit" id="send-button" title="Send Message"
                                class="absolute right-2 bottom-3 bg-purple-500 text-white p-2 rounded-lg hover:bg-purple-600 focus:outline-none disabled:opacity-50"
                                disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13a1 1 0 102 0V9.414l1.293 1.293a1 1 0 001.414-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>

                            <!-- Stop Button -->
                            <button type="button" id="stop-button" title="Stop Response"
                                class="absolute right-2 bottom-3 bg-red-500 text-white p-2 rounded-lg hover:bg-red-600 focus:outline-none hidden">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8 7a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V8a1 1 0 00-1-1H8z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>

                            <!-- Dropdown panel -->
                            <div id="tools-dropdown" class="absolute left-2 bottom-14 w-48 bg-white border rounded-lg shadow-lg z-50 hidden">
                                @php
                                    $currentModel = Auth::user()->selected_model ?? 'gpt-4o-mini';
                                    $isClaudeModel = str_contains(strtolower($currentModel), 'claude');
                                    $isGeminiModel = str_contains(strtolower($currentModel), 'gemini');
                                    $isGrokModel = str_contains(strtolower($currentModel), 'grok');
                                @endphp
                                
                                {{-- Web Search - Available for specific models --}}
                                @if(auth()->user()->hasFeature('chattermate.search_web'))
                                    <label class="flex items-center px-4 py-2 hover:bg-gray-100 cursor-pointer" id="web-search-option">
                                        <input type="checkbox" id="web_search" name="web_search" class="mr-2 tool-option" 
                                            data-label="{{ $isClaudeModel ? 'Search Web (Claude)' : ($isGeminiModel ? 'Search Web (Gemini)' : ($isGrokModel ? 'Live Search (Grok)' : 'Search Web (OpenAI)')) }}">
                                        <span class="text-sm text-gray-700" id="web-search-label">
                                            {{ $isClaudeModel ? '🔍 Search Web (Claude)' : ($isGeminiModel ? '🔍 Search Web (Gemini)' : ($isGrokModel ? '🔍 Live Search (Grok)' : '🔍 Search Web (OpenAI)')) }}
                                        </span>
                                    </label>
                                @else
                                    <label class="flex items-center px-4 py-2 bg-gray-50 cursor-not-allowed" title="Requires Premium Plan">
                                        <input type="checkbox" disabled class="mr-2">
                                        <span class="text-sm text-gray-500 line-through">Search Web 🔒 Pro</span>
                                    </label>
                                @endif

                                {{-- Image Generation - Available for OpenAI and Grok --}}
                                <div id="create-image-wrapper" style="{{ $isClaudeModel || $isGeminiModel ? 'display: none;' : 'display: block;' }}">
                                    @if(auth()->user()->hasFeature('chattermate.generate_image'))
                                        <label class="flex items-center px-4 py-2 hover:bg-gray-100 cursor-pointer">
                                            <input type="checkbox" id="create_image" name="create_image" class="mr-2 tool-option" data-label="Create Image">
                                            <span class="text-sm text-gray-700">
                                                {{ $isGrokModel ? '🎨 Create Image (Grok)' : '🎨 Create Image' }}
                                            </span>
                                        </label>
                                    @else
                                        <label class="flex items-center px-4 py-2 bg-gray-50 cursor-not-allowed" title="Requires Premium Plan">
                                            <input type="checkbox" disabled class="mr-2">
                                            <span class="text-sm text-gray-500 line-through">Create Image 🔒 Pro</span>
                                        </label>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Free Tokens --}}
                        @if($tokensLeft == 0)
                            <div class="alert alert-success text-center mt-3" role="alert"
                                style="border-radius: 12px; background: linear-gradient(135deg, #d4fc79, #96e6a1); color: #155724; padding: 18px; font-size: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                🎉 <strong>Good news!</strong><br>
                                You’ve reached your token limit, but we’ve added <strong>bonus tokens</strong> so you can keep chatting without interruption.  
                                Enjoy your free boost — just for you! 🚀
                            </div>
                        @endif



                        <!-- Info note -->
                        <p class="text-xs text-gray-500 mt-2 text-center">
                            <strong>Clever Creator AI</strong> can make mistakes. Consider checking important information.
                        </p>
                    </div>
                </form>

                <!-- Image Modal -->
                <div id="image-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-80 hidden">
                    <div class="relative">
                        <img id="modal-image" class="max-h-screen max-w-screen rounded-lg" />
                        <button id="modal-close" class="absolute top-2 right-2 text-white text-xl bg-black bg-opacity-50 px-2 rounded hover:bg-opacity-75">&times;</button>
                    </div>
                </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const chatForm = document.getElementById('chat-form');
        const messageInput = document.getElementById('message-input');
        const chatContainer = document.getElementById('chat-container');
        const newChatBtn = document.getElementById('new-chat-btn');
        const chatHistory = document.getElementById('chat-history');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const emptyState = document.getElementById('empty-state');
        const examplePrompts = document.querySelectorAll('.example-prompt');
        const sendButton = document.getElementById('send-button');
        const stopButton = document.getElementById('stop-button');
        const fileInput = document.getElementById('pdf-upload');
        const triggerUpload = document.getElementById('trigger-upload');
        const attachmentPreview = document.getElementById('attachment-preview');
        const fileNameSpan = document.getElementById('file-name');
        const removeFileBtn = document.getElementById('remove-file');

        let currentConversationId = null;
        let conversation = [];
        let isWaitingForResponse = false;

        let abortController = null;
        let isStreaming = false;

        let currentAssistantId = null;
        let isEditing = false;

        
        // Initialize the app
        document.addEventListener('DOMContentLoaded', () => {
            loadChatHistory();
            
            // Check if we have a conversation ID in the URL
            const urlParams = new URLSearchParams(window.location.search);
            const conversationId = urlParams.get('conversation');
            
            if (conversationId) {
                loadConversation(conversationId);
            }
            
            // Focus the textarea on page load
            messageInput.focus();
        });

        // Trigger file dialog
        triggerUpload.addEventListener('click', () => {
            fileInput.click();
        });

       // Show selected file (with image preview if applicable)
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (file) {
                // Clear existing preview
                fileNameSpan.innerHTML = '';

                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    img.className = 'max-h-24 rounded';
                    fileNameSpan.appendChild(img);
                } else {
                    fileNameSpan.textContent = file.name;
                }

                attachmentPreview.classList.remove('hidden');
            }
        });

        // Remove selected file
        removeFileBtn.addEventListener('click', () => {
            fileInput.value = '';
            attachmentPreview.classList.add('hidden');
        });
        
        // Toggle sidebar on mobile
        sidebarToggle?.addEventListener('click', (e) => {
            e.stopPropagation(); // Prevent click from bubbling to document
            sidebar.classList.toggle('sidebar-hidden');
            sidebar.classList.toggle('sidebar-visible');
        });

        // Close sidebar when clicking outside - ONLY ON MOBILE
        document.addEventListener('click', function (e) {
            const isSidebarOpen = sidebar.classList.contains('sidebar-visible');
            const isMobile = window.innerWidth < 1290; // Check if mobile screen

            // Only close sidebar on mobile when clicking outside
            if (isMobile && isSidebarOpen && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.add('sidebar-hidden');
                sidebar.classList.remove('sidebar-visible');
            }
        });

        // Auto Update User Stats
        function updateUserStats() {
            fetch('/user/stats')
                .then(res => res.json())
                .then(data => {
                    if (data.credits_left !== undefined && data.tokens_left !== undefined) {
                        const creditsElem = document.getElementById('credits_left');
                        const tokensElem = document.getElementById('tokens_left');

                        if (creditsElem) creditsElem.textContent = data.credits_left;
                        if (tokensElem) tokensElem.textContent = data.tokens_left;
                    }
                })
                .catch(err => console.error('Error fetching updated stats:', err));
        }

        function addChatGPTLinkStyles(element) {
            // Style links
            const links = element.querySelectorAll('a');
            links.forEach(link => {
                // Add Tailwind classes for ChatGPT-like styling
                link.classList.add(
                    'text-blue-600',      // Default blue color
                    'hover:text-blue-800', // Darker blue on hover
                    'hover:underline',    // Underline on hover
                    'transition-colors',   // Smooth color transition
                    'duration-200'         // Transition duration
                );
                
                // Add external link icon
                if (link.href && !link.href.startsWith(window.location.origin)) {
                    const icon = document.createElement('span');
                    icon.innerHTML = '&nbsp;↗';
                    icon.classList.add('inline-block', 'text-xs', 'align-super');
                    link.appendChild(icon);
                }
            });

            // Style lists (bullet points and numbered lists)
            element.querySelectorAll('ul').forEach(ul => {
                ul.classList.add('list-disc', 'pl-6', 'my-2', 'space-y-1');
            });
            
            element.querySelectorAll('ol').forEach(ol => {
                ol.classList.add('list-decimal', 'pl-6', 'my-2', 'space-y-1');
            });
            
            // Style list items
            element.querySelectorAll('li').forEach(li => {
                li.classList.add('mb-1');
                
                // Handle nested lists
                if (li.querySelector('ul, ol')) {
                    li.classList.add('mt-1');
                }
            });

            // Style code blocks (if not already handled elsewhere)
            element.querySelectorAll('pre').forEach(pre => {
                pre.classList.add('bg-gray-100', 'p-3', 'rounded', 'overflow-x-auto', 'my-2');
            });
            
            element.querySelectorAll('code:not(pre code)').forEach(code => {
                code.classList.add('bg-gray-100', 'px-1', 'py-0.5', 'rounded', 'text-sm');
            });

            // Style blockquotes
            element.querySelectorAll('blockquote').forEach(blockquote => {
                blockquote.classList.add('border-l-4', 'border-gray-300', 'pl-4', 'my-2', 'text-gray-600');
            });

            // Style tables
            element.querySelectorAll('table').forEach(table => {
                table.classList.add('border-collapse', 'w-full', 'my-2');
            });
            
            element.querySelectorAll('th').forEach(th => {
                th.classList.add('border', 'px-4', 'py-2', 'bg-gray-100', 'text-left');
            });
            
            element.querySelectorAll('td').forEach(td => {
                td.classList.add('border', 'px-4', 'py-2');
            });
        }

        const expertCards = document.querySelectorAll('.expert-card');
        const expertIdInput = document.getElementById('expert-id');
        const shouldRedirectOnExpertClick = true;

        const modal = document.getElementById('expert-modal');
        const showMoreBtn = document.getElementById('show-more-btn');
        const closeModalBtn = document.getElementById('close-modal');

        // Expert click handler
        document.querySelectorAll('.expert-card').forEach(card => {
            card.addEventListener('click', () => {
                const expertId = card.dataset.expertId;
                const domain = card.dataset.domain;

                let redirectUrl = '/expert-chat'; // default
                if (domain === 'ai-tutor') {
                    redirectUrl = '/ai-tutor-expert-chat';
                }

                window.location.href = `${redirectUrl}?expert_id=${expertId}`;
            });
        });

        document.getElementById('close-modal')?.addEventListener('click', () => {
            document.getElementById('expert-modal')?.classList.add('hidden');
        });


        // Show modal
        if (showMoreBtn) {
            showMoreBtn.addEventListener('click', () => {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            });
        }

        // Close modal
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', () => {
                modal.classList.add('hidden');
            });
        }
        
        // Load chat history from server
        function loadChatHistory() {
            fetch('/get-chats', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error loading chat history:', data.error);
                    return;
                }
                
               chatHistory.innerHTML = `
                    <div class="space-y-2">
                        ${data.map(chat => `
                            <div class="p-3 rounded-md chat-hover cursor-pointer flex items-center justify-between group conversation-item ${currentConversationId === chat.id ? 'bg-gray-800' : ''}" 
                                data-id="${chat.id}">
                                <div class="flex items-center flex-1 min-w-0 conversation-title-container">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="truncate conversation-title">${chat.title}</span>
                                    <input type="text" class="hidden w-full bg-gray-700 text-white px-2 py-1 rounded border border-gray-600 focus:outline-none focus:border-purple-500 conversation-title-input" value="${chat.title}">
                                </div>
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button class="edit-conversation-btn text-white-400 hover:text-blue-400 p-1" 
                                            data-id="${chat.id}" title="Edit title">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button class="delete-conversation-btn text-white-400 hover:text-red-500 transition-colors p-1" 
                                            data-id="${chat.id}" title="Delete conversation">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
                
                // Add click handlers to conversation items
                document.querySelectorAll('.conversation-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const id = item.getAttribute('data-id');
                        loadConversation(id);

                        // Update URL
                        window.history.pushState({}, '', `?conversation=${id}`);

                        // Close sidebar ONLY on mobile
                        if (window.innerWidth < 1290) {
                            sidebar.classList.add('sidebar-hidden');
                            sidebar.classList.remove('sidebar-visible');
                        }
                    });
                });
            })
            .catch(error => {
                console.error('Error loading chat history:', error);
            });
        }
        
        // Load a specific conversation
        function loadConversation(id) {
            fetch(`/get-conversation/${id}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error loading conversation:', data.error);
                    return;
                }
                
                // Update UI
                const messagesContainer = chatContainer.querySelector('.space-y-4');
                messagesContainer.innerHTML = '';
                
                data.messages.forEach(message => {
                    addMessage(message.role, message.content, message.attachment ?? null);
                });
                
                // Update conversation state
                currentConversationId = id;
                conversation = data.messages;
                
                // Hide empty state
                emptyState.style.display = 'none';
                
                // Update active conversation in sidebar
                document.querySelectorAll('.conversation-item').forEach(item => {
                    if (item.getAttribute('data-id') === id.toString()) {
                        item.classList.add('bg-gray-800');
                    } else {
                        item.classList.remove('bg-gray-800');
                    }
                });
                
                // Scroll to bottom
                chatContainer.scrollTop = chatContainer.scrollHeight;

                // Enhance code blocks after all messages are loaded
                enhanceCodeBlocks();
            })
            .catch(error => {
                console.error('Error loading conversation:', error);
            });
        }
        
        // Start a new conversation
        function newConversation() {
            // Clear the chat container
            const messagesContainer = chatContainer.querySelector('.space-y-4');
            messagesContainer.innerHTML = '';
            
            // Reset conversation state
            currentConversationId = null;
            conversation = [];
            
            // Show empty state
            emptyState.style.display = 'block';
            
            // Update URL
            window.history.pushState({}, '', window.location.pathname);
            
            // Remove active class from all conversation items
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('bg-gray-800');
            });

            // Close sidebar on mobile
            if (window.innerWidth < 768) {
                sidebar.classList.add('sidebar-hidden');
                sidebar.classList.remove('sidebar-visible');
            }
            
            // Focus the input
            messageInput.focus();
        }

        // Image modal functionality
       function openImageModal(src, alt = '') {
            const modal = document.getElementById('image-modal');
            const modalImg = document.getElementById('modal-image');
            const closeBtn = document.getElementById('modal-close');

            modalImg.src = src;
            modalImg.alt = alt;
            modal.classList.remove('hidden');

            closeBtn.onclick = () => {
                modal.classList.add('hidden');
            };

            modal.onclick = (e) => {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                }
            };
        }

        // Add new functions for editing and regenerating
        function editMessage(assistantId) {
            const assistantIndex = conversation.findIndex(m => m.id === assistantId);
            if (assistantIndex === -1 || assistantIndex === 0) return;
            
            const userMessage = conversation[assistantIndex - 1];
            
            // Set editing state
            document.getElementById('editing-message-id').value = assistantId;
            messageInput.value = userMessage.content;
            resizeTextarea();
            messageInput.focus();
            
            // Remove messages after the edited assistant message
            removeMessagesAfter(assistantId);
        }

        function regenerateMessage(assistantId) {
            const assistantIndex = conversation.findIndex(m => m.id === assistantId);
            if (assistantIndex === -1 || assistantIndex === 0) return;
            
            const userMessage = conversation[assistantIndex - 1];
            
            // Set editing state
            document.getElementById('editing-message-id').value = assistantId;
            
            // Remove messages after the user message
            removeMessagesAfter(userMessage.id);
            
            // Submit the form
            messageInput.value = userMessage.content;
            chatForm.dispatchEvent(new Event('submit'));
        }

        function removeMessagesAfter(messageId) {
            const messageIndex = conversation.findIndex(m => m.id === messageId);
            if (messageIndex === -1) return;
            
            // Remove from conversation array
            conversation.splice(messageIndex + 1);
            
            // Remove from DOM
            const messages = document.querySelectorAll('.message-container');
            let found = false;
            
            messages.forEach(message => {
                if (found) message.remove();
                if (message.id === messageId) found = true;
            });
        }

        async function translateMessage(messageId, targetLang) {
            const messageEl = document.getElementById(messageId);
            if (!messageEl) return;

            const originalContent = messageEl.querySelector('.message-content').innerText;

            try {
                const response = await fetch('/translate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        text: originalContent,
                        target_lang: targetLang
                    })
                });

                const data = await response.json();
                if (data.success) {
                    // Show translation below the original
                    const translatedDiv = document.createElement('div');
                    translatedDiv.className = 'mt-2 p-2 bg-gray-50 rounded text-sm border-l-4 border-green-500';
                    translatedDiv.innerHTML = `<strong>(${targetLang.toUpperCase()})</strong> ${data.translation}`;
                    messageEl.querySelector('.message-content').appendChild(translatedDiv);
                } else {
                    alert('Translation failed: ' + (data.error || 'Unknown error'));
                }
            } catch (err) {
                console.error('Translation error:', err);
            }
        }

        // Add a message to the chat
        function addMessage(role, content, attachment = null, id = null) {
            if (role === 'system') return;

            const messageDiv = document.createElement('div');
            messageDiv.className = `flex ${role === 'user' ? 'justify-end' : 'justify-start'} message-container`;
            
            if (id) {
                messageDiv.id = id;
            } else {
                messageDiv.id = `${role}-${Date.now()}`;
            }

            const bubbleDiv = document.createElement('div');
            bubbleDiv.className = `rounded-lg p-4 ${role === 'user' ? 'bg-purple-600 text-white' : 'bg-white border'} relative overflow-y-auto`;
            
            const contentDiv = document.createElement('div');
            contentDiv.className = role === 'user' ? 'user-message' : 'message-content';

            // Create message actions container (for both copy and read aloud buttons)
            const actionsDiv = document.createElement('div');
            actionsDiv.className = 'message-actions flex space-x-2 mt-2'; // Added mt-2 for spacing

             // Add Edit Button
            const editButton = document.createElement('button');
            editButton.className = 'message-action-btn edit-button';
            editButton.title = 'Edit message';
            editButton.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            `;
            editButton.addEventListener('click', () => {
                editMessage(currentAssistantId);
            });

            // Create Copy Button (for both user and assistant)
            const copyButton = document.createElement('button');
            copyButton.className = 'message-action-btn copy-all-button';
            copyButton.title = 'Copy message';
            copyButton.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M4 4a2 2 0 012-2h6a2 2 0 012 2v1h-2V4H6v10h1v2H6a2 2 0 01-2-2V4z" />
                    <path d="M9 8a2 2 0 012-2h5a2 2 0 012 2v8a2 2 0 01-2 2h-5a2 2 0 01-2-2V8z" />
                </svg>
                <span class="sr-only">Copy</span>
            `;

            copyButton.addEventListener('click', () => {
                const hasTable = contentDiv.querySelector('table');
                
                if (hasTable) {
                    // For content with tables, create both HTML and formatted plain text versions
                    const fullHTML = contentDiv.innerHTML;
                    
                    // Create formatted plain text version
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = fullHTML;
                    
                    // Convert tables to tab-separated format while preserving other content
                    const tables = tempDiv.querySelectorAll('table');
                    tables.forEach(table => {
                        const rows = table.querySelectorAll('tr');
                        let tableText = '';
                        
                        rows.forEach(row => {
                            const cells = row.querySelectorAll('th, td');
                            const rowText = Array.from(cells).map(cell => cell.textContent.trim()).join('\t');
                            tableText += rowText + '\n';
                        });
                        
                        // Replace the table with formatted text
                        table.replaceWith(document.createTextNode('\n' + tableText + '\n'));
                    });
                    
                    const formattedText = tempDiv.textContent;
                    
                    // Try to copy both HTML and plain text versions
                    if (navigator.clipboard && navigator.clipboard.write) {
                        const clipboardItem = new ClipboardItem({
                            'text/html': new Blob([fullHTML], { type: 'text/html' }),
                            'text/plain': new Blob([formattedText], { type: 'text/plain' })
                        });
                        
                        navigator.clipboard.write([clipboardItem]).then(() => {
                            const originalHTML = copyButton.innerHTML;
                            copyButton.innerHTML = 'Copied!';
                            setTimeout(() => {
                                copyButton.innerHTML = originalHTML;
                            }, 2000);
                        }).catch(() => {
                            // Fallback to plain text
                            navigator.clipboard.writeText(formattedText);
                            const originalHTML = copyButton.innerHTML;
                            copyButton.innerHTML = 'Copied!';
                            setTimeout(() => {
                                copyButton.innerHTML = originalHTML;
                            }, 2000);
                        });
                    } else {
                        // Fallback for older browsers
                        navigator.clipboard.writeText(formattedText).then(() => {
                            const originalHTML = copyButton.innerHTML;
                            copyButton.innerHTML = 'Copied!';
                            setTimeout(() => {
                                copyButton.innerHTML = originalHTML;
                            }, 2000);
                        });
                    }
                } else {
                    // For content without tables, use the existing logic
                    const textToCopy = contentDiv.textContent;
                    navigator.clipboard.writeText(textToCopy).then(() => {
                        const originalHTML = copyButton.innerHTML;
                        copyButton.innerHTML = 'Copied!';
                        setTimeout(() => {
                            copyButton.innerHTML = originalHTML;
                        }, 2000);
                    }).catch(err => {
                        console.error('Failed to copy text:', err);
                        copyButton.textContent = 'Failed to copy!';
                    });
                }
            });

            // Create Read Aloud Button (for both user and assistant)
            const readAloudButton = document.createElement('button');
            readAloudButton.className = 'message-action-btn read-aloud-button';
            readAloudButton.title = 'Read aloud';
            readAloudButton.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.707.707L4.586 13H2a1 1 0 01-1-1V8a1 1 0 011-1h2.586l3.707-3.707a1 1 0 011.09-.217zM14.657 2.929a1 1 0 011.414 0A9.972 9.972 0 0119 10a9.972 9.972 0 01-2.929 7.071 1 1 0 01-1.414-1.414A7.971 7.971 0 0017 10c0-2.21-.894-4.208-2.343-5.657a1 1 0 010-1.414zm-2.829 2.828a1 1 0 011.415 0A5.983 5.983 0 0115 10a5.984 5.984 0 01-1.757 4.243 1 1 0 01-1.415-1.415A3.984 3.984 0 0013 10a3.983 3.983 0 00-1.172-2.828 1 1 0 010-1.415z" clip-rule="evenodd" />
                </svg>
                <span class="sr-only">Read aloud</span>
            `;

            let currentSpeech = null;
            readAloudButton.addEventListener('click', () => {
                // Stop any currently playing speech
                if (currentSpeech) {
                    window.speechSynthesis.cancel();
                    currentSpeech = null;
                    readAloudButton.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.707.707L4.586 13H2a1 1 0 01-1-1V8a1 1 0 011-1h2.586l3.707-3.707a1 1 0 011.09-.217zM14.657 2.929a1 1 0 011.414 0A9.972 9.972 0 0119 10a9.972 9.972 0 01-2.929 7.071 1 1 0 01-1.414-1.414A7.971 7.971 0 0017 10c0-2.21-.894-4.208-2.343-5.657a1 1 0 010-1.414zm-2.829 2.828a1 1 0 011.415 0A5.983 5.983 0 0115 10a5.984 5.984 0 01-1.757 4.243 1 1 0 01-1.415-1.415A3.984 3.984 0 0013 10a3.983 3.983 0 00-1.172-2.828 1 1 0 010-1.415z" clip-rule="evenodd" />
                        </svg>
                        <span class="sr-only">Read aloud</span>
                    `;
                    return;
                }

                const textToRead = contentDiv.textContent;
                if (!textToRead.trim()) return;

                const speech = new SpeechSynthesisUtterance(textToRead);
                speech.rate = 1;
                speech.pitch = 1;
                speech.volume = 1;

                // Change button to stop icon while playing
                readAloudButton.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8 7a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V8a1 1 0 00-1-1H8z" clip-rule="evenodd" />
                    </svg>
                    <span class="sr-only">Stop reading</span>
                `;

                speech.onend = () => {
                    currentSpeech = null;
                    readAloudButton.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.707.707L4.586 13H2a1 1 0 01-1-1V8a1 1 0 011-1h2.586l3.707-3.707a1 1 0 011.09-.217zM14.657 2.929a1 1 0 011.414 0A9.972 9.972 0 0119 10a9.972 9.972 0 01-2.929 7.071 1 1 0 01-1.414-1.414A7.971 7.971 0 0017 10c0-2.21-.894-4.208-2.343-5.657a1 1 0 010-1.414zm-2.829 2.828a1 1 0 011.415 0A5.983 5.983 0 0115 10a5.984 5.984 0 01-1.757 4.243 1 1 0 01-1.415-1.415A3.984 3.984 0 0013 10a3.983 3.983 0 00-1.172-2.828 1 1 0 010-1.415z" clip-rule="evenodd" />
                        </svg>
                        <span class="sr-only">Read aloud</span>
                    `;
                };

                currentSpeech = speech;
                window.speechSynthesis.speak(speech);
            });

            if (role === 'user') {
                contentDiv.textContent = content;

                if (attachment) {
                    const attachmentDiv = document.createElement('div');
                    attachmentDiv.className = 'mt-2 text-sm text-blue-100 italic break-all';

                    let name = '';
                    let url = '';

                    if (typeof attachment === 'string') {
                        name = attachment;
                        url = '#';
                    } else if (typeof attachment === 'object') {
                        name = attachment.name;
                        url = attachment.url;
                    }

                    if (!url || url === '#') {
                        const file = fileInput?.files?.[0];
                        if (file && file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function (e) {
                                const img = document.createElement('img');
                                img.src = e.target.result;
                                img.alt = name;
                                img.className = 'mt-2 max-w-xs rounded-lg border cursor-pointer hover:opacity-90 transition';
                                img.addEventListener('click', () => {
                                    openImageModal(e.target.result, name);
                                });
                                contentDiv.appendChild(img);
                            };
                            reader.readAsDataURL(file);
                        } else {
                            attachmentDiv.textContent = `📎 ${name}`;
                            contentDiv.appendChild(attachmentDiv);
                        }
                    } else {
                        const isImage = name.match(/\.(jpg|jpeg|png|gif|webp|bmp)$/i);
                        if (isImage) {
                            const img = document.createElement('img');
                            img.src = url;
                            img.alt = name;
                            img.className = 'max-w-xs rounded-lg border mb-1';
                            img.addEventListener('click', () => {
                                openImageModal(url, name);
                            });
                            contentDiv.appendChild(img);

                            const downloadBtn = document.createElement('a');
                            downloadBtn.href = url;
                            downloadBtn.download = name;
                            downloadBtn.title = 'Download image';
                            downloadBtn.className = 'inline-flex items-center space-x-1 text-blue-600 hover:text-blue-800 text-sm';
                            downloadBtn.innerHTML = `
                                <div class="flex items-center space-x-1 text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                                    </svg>
                                    <span>Download</span>
                                </div>
                            `;
                            contentDiv.appendChild(downloadBtn);
                        } else {
                            const attachmentDiv = document.createElement('div');
                            attachmentDiv.className = 'mt-2 text-sm text-blue-600';

                            const fileLink = document.createElement('a');
                            fileLink.href = url;
                            fileLink.download = name;
                            fileLink.className = 'underline hover:text-blue-800';
                            fileLink.textContent = `📎 ${name}`;

                            attachmentDiv.appendChild(fileLink);
                            contentDiv.appendChild(attachmentDiv);
                        }
                    }

                    contentDiv.appendChild(document.createElement('br'));
                }

            } else {
                // ✅ NEW: Check for MULTIPLE chart blocks
                const chartRegex = /```chart\n([\s\S]*?)\n```/g;
                const chartMatches = [...content.matchAll(chartRegex)];
                
                if (chartMatches.length > 0) {
                    try {
                        // Remove ALL chart blocks from content
                        let textContent = content;
                        chartMatches.forEach(match => {
                            textContent = textContent.replace(match[0], '');
                        });
                        textContent = textContent.trim();
                        
                        // Render markdown text if any
                        if (textContent) {
                            contentDiv.innerHTML = marked.parse(textContent, {
                                gfm: true,
                                breaks: true,
                                headerIds: false,
                                mangle: false
                            });
                        }
                        
                        // ✅ Render EACH chart
                        chartMatches.forEach((match, index) => {
                            try {
                                const chartData = JSON.parse(match[1]);
                                
                                // Create chart container
                                const chartContainer = document.createElement('div');
                                chartContainer.className = 'chart-container';
                                
                                const canvas = document.createElement('canvas');
                                canvas.className = 'chart-canvas';
                                canvas.id = `chart-${Date.now()}-${index}`;
                                
                                chartContainer.appendChild(canvas);
                                contentDiv.appendChild(chartContainer);
                                
                                // Render chart after DOM is ready
                                setTimeout(() => {
                                    renderChart(canvas, chartData);
                                }, 100);
                                
                            } catch (e) {
                                console.error(`Error parsing chart ${index + 1}:`, e);
                            }
                        });
                        
                    } catch (e) {
                        console.error('Error parsing chart data:', e);
                        // Fallback to regular markdown
                        contentDiv.innerHTML = marked.parse(content, {
                            gfm: true,
                            breaks: true,
                            headerIds: false,
                            mangle: false
                        });
                    }
                } else if (typeof content === 'string' && content.match(/\.(jpeg|jpg|png|gif|webp)$/i)) {
                    // Image handling (keep existing code)
                    const imgContainer = document.createElement('div');
                    imgContainer.className = 'my-4';
                    const img = document.createElement('img');
                    img.src = content;
                    img.alt = 'Generated image';
                    img.className = 'rounded-lg max-w-full h-auto shadow-md cursor-pointer';
                    img.addEventListener('click', () => {
                        openImageModal(content, 'Generated image');
                    });
                    imgContainer.appendChild(img);
                    contentDiv.appendChild(imgContainer);
                } else {
                    // Regular markdown rendering
                    contentDiv.innerHTML = marked.parse(content, {
                        gfm: true,
                        breaks: true,
                        headerIds: false,
                        mangle: false
                    });

                    addChatGPTLinkStyles(contentDiv);
                    
                    // Render math expressions
                    if (window.MathJax && window.MathJax.typesetPromise) {
                        window.MathJax.typesetPromise([contentDiv]).catch((err) => {
                            console.error('MathJax rendering error:', err);
                        });
                    }
                }
            }

            // Add content to bubble first
            bubbleDiv.appendChild(contentDiv);

            // Then add action buttons after content
            actionsDiv.appendChild(readAloudButton);
            actionsDiv.appendChild(copyButton);
            // In addMessage function, only add edit button for assistant messages
            if (role === 'user') {
                actionsDiv.appendChild(editButton);
            }
            bubbleDiv.appendChild(actionsDiv);

            messageDiv.appendChild(bubbleDiv);

            const messagesContainer = chatContainer.querySelector('.space-y-4') || document.createElement('div');
            if (!chatContainer.querySelector('.space-y-4')) {
                messagesContainer.className = 'space-y-4';
                chatContainer.appendChild(messagesContainer);
            }
            messagesContainer.appendChild(messageDiv);

            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        // ✅ NEW: Function to render charts
        function renderChart(canvas, chartData) {
            const ctx = canvas.getContext('2d');
            
            // Destroy existing chart if any
            const existingChart = Chart.getChart(canvas);
            if (existingChart) {
                existingChart.destroy();
            }
            
            // Default chart configuration
            const config = {
                type: chartData.type || 'line',
                data: chartData.data || {
                    labels: chartData.labels || [],
                    datasets: chartData.datasets || []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: !!chartData.title,
                            text: chartData.title || ''
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        }
                    },
                    scales: chartData.type !== 'pie' && chartData.type !== 'doughnut' ? {
                        x: {
                            display: true,
                            title: {
                                display: !!chartData.xLabel,
                                text: chartData.xLabel || ''
                            }
                        },
                        y: {
                            display: true,
                            title: {
                                display: !!chartData.yLabel,
                                text: chartData.yLabel || ''
                            }
                        }
                    } : {}
                }
            };
            
            // Merge custom options if provided
            if (chartData.options) {
                config.options = { ...config.options, ...chartData.options };
            }
            
            new Chart(ctx, config);
        }

        // Function to add copy buttons to all code blocks
        function addCopyButtonsToCodeBlocks() {
            document.querySelectorAll('pre').forEach((preElement) => {
                // Skip if we've already added a copy button
                if (preElement.querySelector('.copy-code-button')) return;
                
                // Create container div
                const container = document.createElement('div');
                container.className = 'code-block-container';
                
                // Wrap the pre element in the container
                preElement.parentNode.insertBefore(container, preElement);
                container.appendChild(preElement);
                
                // Create and add the copy button
                const copyButton = document.createElement('button');
                copyButton.className = 'copy-code-button';
                copyButton.textContent = 'Copy';
                copyButton.title = 'Copy to clipboard';
                container.appendChild(copyButton);
                
                // Get the code content
                const code = preElement.querySelector('code')?.innerText || preElement.innerText;
                
                // Add click event
                copyButton.addEventListener('click', () => {
                    navigator.clipboard.writeText(code).then(() => {
                        copyButton.textContent = 'Copied!';
                        copyButton.classList.add('copied');
                        setTimeout(() => {
                            copyButton.textContent = 'Copy';
                            copyButton.classList.remove('copied');
                        }, 2000);
                    }).catch(err => {
                        console.error('Failed to copy text: ', err);
                        copyButton.textContent = 'Failed';
                        setTimeout(() => {
                            copyButton.textContent = 'Copy';
                        }, 2000);
                    });
                });
            });
        }
        
        // Auto-resize textarea based on content
        function resizeTextarea() {
            messageInput.style.height = 'auto';
            messageInput.style.height = `${Math.min(messageInput.scrollHeight, 200)}px`;
        }

        // Enhance code blocks with syntax highlighting and copy buttons
        function enhanceCodeBlocks() {
            document.querySelectorAll('pre code').forEach((block) => {
                if (block.dataset.highlighted === 'true') return;
                hljs.highlightElement(block);
                block.dataset.highlighted = 'true';

                const pre = block.parentElement;
                pre.classList.add('code-block-container');

                if (!pre.querySelector('.copy-code-button')) {
                    const copyBtn = document.createElement('button');
                    copyBtn.className = 'copy-code-button';
                    copyBtn.textContent = 'Copy';

                    copyBtn.addEventListener('click', () => {
                        navigator.clipboard.writeText(block.innerText).then(() => {
                            copyBtn.textContent = 'Copied!';
                            copyBtn.classList.add('copied');
                            setTimeout(() => {
                                copyBtn.textContent = 'Copy';
                                copyBtn.classList.remove('copied');
                            }, 2000);
                        });
                    });

                    pre.appendChild(copyBtn);
                }
            });
        }

        // Load saved preference from localStorage
        document.addEventListener('DOMContentLoaded', () => {
            const autoOptimizeCheckbox = document.getElementById('auto-optimize-checkbox');
            
            // Load saved state (default to true/checked)
            const savedState = localStorage.getItem('auto_optimize_model');
            if (savedState !== null) {
                autoOptimizeCheckbox.checked = savedState === 'true';
            }
            
            // Save state when changed
            autoOptimizeCheckbox.addEventListener('change', function() {
                localStorage.setItem('auto_optimize_model', this.checked);
                
                // Optional: Show visual feedback
                const notification = document.createElement('div');
                notification.className = 'fixed top-20 right-4 z-50 px-4 py-2 rounded-lg shadow-lg bg-purple-600 text-white text-sm animate-fade-in';
                notification.textContent = this.checked 
                    ? '✓ Auto-optimization enabled' 
                    : '✗ Auto-optimization disabled';
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.opacity = '0';
                    notification.style.transition = 'opacity 0.3s';
                    setTimeout(() => notification.remove(), 300);
                }, 2000);
            });
        });

// Handle form submission
chatForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Check if we're editing a message
    const editingMessageId = document.getElementById('editing-message-id').value;
    isEditing = editingMessageId !== '';

    const expertId = expertIdInput.value;
    const message = messageInput.value.trim();
    if (!message || isWaitingForResponse) return;

    // Hide empty state if it's visible
    emptyState.style.display = 'none';
    
    const attachmentName = fileInput?.files?.[0]?.name || null;

    // Add user message to UI
    addMessage('user', message, attachmentName);
    
    // Add to conversation history
    conversation.push({
        role: 'user',
        content: message,
        id: `user-${Date.now()}`
    });
    
    // Clear input and reset textarea height
    messageInput.value = '';
    resizeTextarea();
    sendButton.disabled = true;
    
    // Show stop button and hide send button
    sendButton.classList.add('hidden');
    stopButton.classList.remove('hidden');
    
    // Create assistant message element
    const assistantDiv = document.createElement('div');
    assistantDiv.className = 'flex justify-start message-container';
    assistantDiv.id = `assistant-${Date.now()}`;
    currentAssistantId = assistantDiv.id;
    
    const assistantBubble = document.createElement('div');
    assistantBubble.className = 'rounded-lg p-4 bg-white border overflow-y-auto';
    
    const assistantContent = document.createElement('div');
    assistantContent.innerHTML = `
        <div class="thinking-indicator flex items-center space-x-1">
            <div class="dot w-2 h-2 rounded-full"></div>
            <div class="dot w-2 h-2 rounded-full"></div>
            <div class="dot w-2 h-2 rounded-full"></div>
        </div>
        `;

    assistantContent.className = 'message-content';
    
    assistantBubble.appendChild(assistantContent);
    assistantDiv.appendChild(assistantBubble);
    
    const messagesContainer = chatContainer.querySelector('.space-y-4');
    messagesContainer.appendChild(assistantDiv);
    
    // Scroll to bottom
    chatContainer.scrollTop = chatContainer.scrollHeight;
    
    // Create a new conversation if this is the first message
    if (!currentConversationId && conversation.length === 1) {
        try {
            const response = await fetch('/save-chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    title: 'New Chat',
                    messages: []
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                currentConversationId = data.conversation_id;
                loadChatHistory();
            }
        } catch (error) {
            console.error('Error creating new conversation:', error);
        }
    }

    const formData = new FormData();
    formData.append('message', message);
    formData.append('expert_id', expertId);
    formData.append('conversation_id', currentConversationId);
    formData.append('conversation', JSON.stringify(conversation));

    // ✅ UPDATED: Add both checkbox values
    const autoOptimizeCheckbox = document.getElementById('auto-optimize-checkbox');
    const crossProviderCheckbox = document.getElementById('cross-provider-checkbox');
    
    formData.append('auto_optimize_model', autoOptimizeCheckbox?.checked ? '1' : '0');
    formData.append('allow_cross_provider', crossProviderCheckbox?.checked ? '1' : '0');

    console.log('Form submission parameters:', {
        auto_optimize: autoOptimizeCheckbox?.checked,
        cross_provider: crossProviderCheckbox?.checked,
        message: message.substring(0, 50) + '...'
    });

    if (fileInput && fileInput.files.length > 0) {
        formData.append('pdf', fileInput.files[0]);
    }

    // Get checkbox value for web search
    const webSearchCheckbox = document.getElementById('web_search');
    const webSearchEnabled = webSearchCheckbox?.checked;

    const createImageCheckbox = document.getElementById('create_image');
    const createImageEnabled = createImageCheckbox?.checked;

    if (webSearchEnabled) {
        formData.append('web_search', webSearchCheckbox?.checked ? '1' : '0');
    }

    if (createImageEnabled) {
        formData.append('create_image', '1');
    }
    
    // Stream response from OpenAI
    try {
        isWaitingForResponse = true;
        isStreaming = true;
        abortController = new AbortController();
        let fullResponse = '';
        
        const response = await fetch('/chatss', {
            method: 'POST',
            headers: {
                'Accept': 'text/event-stream',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData,
            signal: abortController.signal
        });
        
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            assistantContent.innerHTML = `<p class="text-red-500">${errorData.error || 'An error occurred'}</p>`;
            return;
        }

                
                if (!response.body) {
                    throw new Error('No response body received');
                }
                
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let chunkCount = 0;
                
                while (isStreaming) {
                    const { done, value } = await reader.read();
                    chunkCount++;
                    
                    if (done) {
                        console.log(`Stream completed after ${chunkCount} chunks`);
                        // ✅ Update tokens and credits after generation
                        updateUserStats();
                        break;
                    }
                    
                    const chunk = decoder.decode(value);
                    console.log(`Received chunk ${chunkCount}:`, chunk);
                    
                    const lines = chunk.split('\n\n').filter(line => line.trim());
                    
                    lines.forEach(line => {
                        if (line.startsWith('data:')) {
                            try {
                                const data = JSON.parse(line.substring(5).trim());

                            // Handle image responses
                                if (data.image) {
                                    // Create image element
                                    const imgContainer = document.createElement('div');
                                    imgContainer.className = 'my-4';
                                    
                                    const img = document.createElement('img');
                                    img.src = data.image;
                                    img.alt = data.prompt;
                                    img.className = 'rounded-lg max-w-full h-auto shadow-md';
                                    
                                    const promptText = document.createElement('p');
                                    promptText.className = 'text-sm text-gray-600 mt-2';
                                    promptText.textContent = data.prompt;
                                    
                                    imgContainer.appendChild(img);
                                    imgContainer.appendChild(promptText);
                                    
                                    // Replace thinking indicator with image
                                    assistantContent.innerHTML = '';
                                    assistantContent.appendChild(imgContainer);
                                    
                                    // Save to conversation history
                                    fullResponse = data.image;
                                    conversation.push({
                                        role: 'assistant',
                                        content: data.image,
                                        type: 'image',
                                        prompt: data.prompt
                                    });
                                } 
                                // Handle text responses
                                else if (data.content) {
                                    fullResponse += data.content;
                                    
                                    // ✅ NEW: Check for MULTIPLE chart blocks
                                    const chartRegex = /```chart\n([\s\S]*?)\n```/g;
                                    const chartMatches = [...fullResponse.matchAll(chartRegex)];
                                    
                                    if (chartMatches.length > 0) {
                                        try {
                                            // Remove ALL chart blocks from content
                                            let textContent = fullResponse;
                                            chartMatches.forEach(match => {
                                                textContent = textContent.replace(match[0], '');
                                            });
                                            textContent = textContent.trim();
                                            
                                            // Clear previous content
                                            assistantContent.innerHTML = '';
                                            
                                            // Render text if any (BEFORE charts)
                                            if (textContent) {
                                                const textDiv = document.createElement('div');
                                                textDiv.innerHTML = marked.parse(textContent, {
                                                    gfm: true,
                                                    breaks: true,
                                                    headerIds: false,
                                                    mangle: false
                                                });
                                                assistantContent.appendChild(textDiv);
                                            }
                                            
                                            // ✅ Render EACH chart
                                            chartMatches.forEach((match, index) => {
                                                try {
                                                    const chartData = JSON.parse(match[1]);
                                                    
                                                    // Create chart container
                                                    const chartContainer = document.createElement('div');
                                                    chartContainer.className = 'chart-container';
                                                    
                                                    const canvas = document.createElement('canvas');
                                                    canvas.className = 'chart-canvas';
                                                    canvas.id = `chart-${Date.now()}-${index}`;
                                                    
                                                    chartContainer.appendChild(canvas);
                                                    assistantContent.appendChild(chartContainer);
                                                    
                                                    // Render chart immediately
                                                    renderChart(canvas, chartData);
                                                    
                                                } catch (e) {
                                                    console.error(`Error parsing chart ${index + 1}:`, e);
                                                }
                                            });
                                            
                                        } catch (e) {
                                            console.error('Error processing charts during stream:', e);
                                            // Fallback to regular markdown
                                            assistantContent.innerHTML = marked.parse(fullResponse, {
                                                gfm: true,
                                                breaks: true,
                                                headerIds: false,
                                                mangle: false
                                            });
                                        }
                                    } else {
                                        // Regular markdown rendering (no charts detected)
                                        assistantContent.innerHTML = marked.parse(fullResponse, {
                                            gfm: true,
                                            breaks: true,
                                            headerIds: false,
                                            mangle: false
                                        });
                                    }

                                    addChatGPTLinkStyles(assistantContent);
                                    addCopyButtonsToCodeBlocks();
                                    
                                    // Render math expressions during streaming
                                    if (window.MathJax && window.MathJax.typesetPromise) {
                                        window.MathJax.typesetPromise([assistantContent]).catch((err) => {
                                            console.log('MathJax rendering during stream:', err);
                                        });
                                    }

                                    chatContainer.scrollTop = chatContainer.scrollHeight;
                                    
                                    document.querySelectorAll('pre code').forEach((block) => {
                                        hljs.highlightElement(block);
                                    });
                                }
                            } catch (e) {
                                console.error('Error parsing SSE data:', e);
                            }
                        }
                    });
                }
                
                if (isStreaming) { // Only push to conversation if not stopped
                     conversation.push({
                        role: 'assistant',
                        content: fullResponse,
                        id: currentAssistantId
                    });
                    console.log('Updated conversation:', conversation);
                    
                    // Reload chat history to update titles
                    loadChatHistory();
                    
                    // Create Read Aloud Button for streamed assistant messages
                    const readAloudButton = document.createElement('button');
                    readAloudButton.className = 'message-action-btn read-aloud-button';
                    readAloudButton.title = 'Read aloud';
                    readAloudButton.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.707.707L4.586 13H2a1 1 0 01-1-1V8a1 1 0 011-1h2.586l3.707-3.707a1 1 0 011.09-.217zM14.657 2.929a1 1 0 011.414 0A9.972 9.972 0 0119 10a9.972 9.972 0 01-2.929 7.071 1 1 0 01-1.414-1.414A7.971 7.971 0 0017 10c0-2.21-.894-4.208-2.343-5.657a1 1 0 010-1.414zm-2.829 2.828a1 1 0 011.415 0A5.983 5.983 0 0115 10a5.984 5.984 0 01-1.757 4.243 1 1 0 01-1.415-1.415A3.984 3.984 0 0013 10a3.983 3.983 0 00-1.172-2.828 1 1 0 010-1.415z" clip-rule="evenodd" />
                        </svg>
                        <span class="sr-only">Read aloud</span>
                    `;

                    let currentSpeech = null;
                    readAloudButton.addEventListener('click', () => {
                        if (currentSpeech) {
                            window.speechSynthesis.cancel();
                            currentSpeech = null;
                            readAloudButton.innerHTML = `
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.707.707L4.586 13H2a1 1 0 01-1-1V8a1 1 0 011-1h2.586l3.707-3.707a1 1 0 011.09-.217zM14.657 2.929a1 1 0 011.414 0A9.972 9.972 0 0119 10a9.972 9.972 0 01-2.929 7.071 1 1 0 01-1.414-1.414A7.971 7.971 0 0017 10c0-2.21-.894-4.208-2.343-5.657a1 1 0 010-1.414zm-2.829 2.828a1 1 0 011.415 0A5.983 5.983 0 0115 10a5.984 5.984 0 01-1.757 4.243 1 1 0 01-1.415-1.415A3.984 3.984 0 0013 10a3.983 3.983 0 00-1.172-2.828 1 1 0 010-1.415z" clip-rule="evenodd" />
                                </svg>
                                <span class="sr-only">Read aloud</span>
                            `;
                            return;
                        }

                        const textToRead = assistantContent.textContent;
                        if (!textToRead.trim()) return;

                        const speech = new SpeechSynthesisUtterance(textToRead);
                        speech.rate = 1;
                        speech.pitch = 1;
                        speech.volume = 1;

                        readAloudButton.innerHTML = `
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8 7a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V8a1 1 0 00-1-1H8z" clip-rule="evenodd" />
                            </svg>
                            <span class="sr-only">Stop reading</span>
                        `;

                        speech.onend = () => {
                            currentSpeech = null;
                            readAloudButton.innerHTML = `
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.707.707L4.586 13H2a1 1 0 01-1-1V8a1 1 0 011-1h2.586l3.707-3.707a1 1 0 011.09-.217zM14.657 2.929a1 1 0 011.414 0A9.972 9.972 0 0119 10a9.972 9.972 0 01-2.929 7.071 1 1 0 01-1.414-1.414A7.971 7.971 0 0017 10c0-2.21-.894-4.208-2.343-5.657a1 1 0 010-1.414zm-2.829 2.828a1 1 0 011.415 0A5.983 5.983 0 0115 10a5.984 5.984 0 01-1.757 4.243 1 1 0 01-1.415-1.415A3.984 3.984 0 0013 10a3.983 3.983 0 00-1.172-2.828 1 1 0 010-1.415z" clip-rule="evenodd" />
                                </svg>
                                <span class="sr-only">Read aloud</span>
                            `;
                        };

                        currentSpeech = speech;
                        window.speechSynthesis.speak(speech);
                    });

                    // === Add copy button for final assistant message ===
                    const copyButton = document.createElement('button');
                    copyButton.className = 'message-action-btn copy-all-button';
                    copyButton.title = 'Copy message';
                    copyButton.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M4 4a2 2 0 012-2h6a2 2 0 012 2v1h-2V4H6v10h1v2H6a2 2 0 01-2-2V4z" />
                            <path d="M9 8a2 2 0 012-2h5a2 2 0 012 2v8a2 2 0 01-2 2h-5a2 2 0 01-2-2V8z" />
                        </svg>
                        <span class="sr-only">Copy</span>
                    `;

                    copyButton.addEventListener('click', () => {
                        const hasTable = assistantContent.querySelector('table');
                        
                        if (hasTable) {
                            // For content with tables, create both HTML and formatted plain text versions
                            const fullHTML = assistantContent.innerHTML;
                            
                            // Create formatted plain text version
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = fullHTML;
                            
                            // Convert tables to tab-separated format while preserving other content
                            const tables = tempDiv.querySelectorAll('table');
                            tables.forEach(table => {
                                const rows = table.querySelectorAll('tr');
                                let tableText = '';
                                
                                rows.forEach(row => {
                                    const cells = row.querySelectorAll('th, td');
                                    const rowText = Array.from(cells).map(cell => cell.textContent.trim()).join('\t');
                                    tableText += rowText + '\n';
                                });
                                
                                // Replace the table with formatted text
                                table.replaceWith(document.createTextNode('\n' + tableText + '\n'));
                            });
                            
                            const formattedText = tempDiv.textContent;
                            
                            // Try to copy both HTML and plain text versions
                            if (navigator.clipboard && navigator.clipboard.write) {
                                const clipboardItem = new ClipboardItem({
                                    'text/html': new Blob([fullHTML], { type: 'text/html' }),
                                    'text/plain': new Blob([formattedText], { type: 'text/plain' })
                                });
                                
                                navigator.clipboard.write([clipboardItem]).then(() => {
                                    copyButton.innerHTML = 'Copied!';
                                    setTimeout(() => {
                                        copyButton.innerHTML = `
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                                                <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                                            </svg>
                                            <span class="sr-only">Copy</span>
                                        `;
                                    }, 2000);
                                }).catch(() => {
                                    // Fallback to plain text
                                    navigator.clipboard.writeText(formattedText);
                                    copyButton.innerHTML = 'Copied!';
                                    setTimeout(() => {
                                        copyButton.innerHTML = `
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                                                <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                                            </svg>
                                            <span class="sr-only">Copy</span>
                                        `;
                                    }, 2000);
                                });
                            } else {
                                // Fallback for older browsers
                                navigator.clipboard.writeText(formattedText).then(() => {
                                    copyButton.innerHTML = 'Copied!';
                                    setTimeout(() => {
                                        copyButton.innerHTML = `
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                                                <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                                            </svg>
                                            <span class="sr-only">Copy</span>
                                        `;
                                    }, 2000);
                                });
                            }
                        } else {
                            // For content without tables, use the existing logic
                            const textToCopy = assistantContent.textContent;
                            navigator.clipboard.writeText(textToCopy).then(() => {
                                copyButton.innerHTML = 'Copied!';
                                setTimeout(() => {
                                    copyButton.innerHTML = `
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                                            <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                                        </svg>
                                        <span class="sr-only">Copy</span>
                                    `;
                                }, 2000);
                            }).catch(err => {
                                console.error('Failed to copy text:', err);
                                copyButton.textContent = 'Failed to copy!';
                            });
                        }
                    });

                    // Add Edit Button
                    const editButton = document.createElement('button');
                    editButton.className = 'message-action-btn edit-button';
                    editButton.title = 'Edit message';
                    editButton.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    `;
                    editButton.addEventListener('click', () => {
                        editMessage(currentAssistantId);
                    });

                    // Add Regenerate Button
                    const regenerateButton = document.createElement('button');
                    regenerateButton.className = 'message-action-btn regenerate-button';
                    regenerateButton.title = 'Regenerate response';
                    regenerateButton.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    `;
                    regenerateButton.addEventListener('click', () => {
                        regenerateMessage(currentAssistantId);
                    });

                    // Add Translate Button
                    const translateButton = document.createElement('button');
                    translateButton.className = 'message-action-btn translate-button';
                    translateButton.title = 'Translate message';
                    translateButton.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-translate" viewBox="0 0 16 16">
                            <path d="M4.545 6.714 4.11 8H3l1.862-5h1.284L8 8H6.833l-.435-1.286zm1.634-.736L5.5 3.956h-.049l-.679 2.022z"/>
                            <path d="M0 2a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v3h3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-3H2a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v7a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zm7.138 9.995q.289.451.63.846c-.748.575-1.673 1.001-2.768 1.292.178.217.451.635.555.867 1.125-.359 2.08-.844 2.886-1.494.777.665 1.739 1.165 2.93 1.472.133-.254.414-.673.629-.89-1.125-.253-2.057-.694-2.82-1.284.681-.747 1.222-1.651 1.621-2.757H14V8h-3v1.047h.765c-.318.844-.74 1.546-1.272 2.13a6 6 0 0 1-.415-.492 2 2 0 0 1-.94.31"/>
                        </svg>
                    `;
                    translateButton.addEventListener('click', () => {
                        const lang = prompt("Enter language code or name (e.g., 'bn' for Bangla, 'es' for Spanish, 'fr' for French):");
                        if (lang) {
                            translateMessage(currentAssistantId, lang.trim());
                        }
                    });

                    const actionsDiv = document.createElement('div');
                    actionsDiv.className = 'message-actions';
                    actionsDiv.appendChild(readAloudButton);  // Add read aloud button first
                    actionsDiv.appendChild(copyButton);
                    // actionsDiv.appendChild(editButton);
                    actionsDiv.appendChild(translateButton);
                    actionsDiv.appendChild(regenerateButton);
                    assistantBubble.appendChild(actionsDiv);

                    // Reset editing state
                    if (isEditing) {
                        document.getElementById('editing-message-id').value = '';
                        isEditing = false;
                    }
                }
                
           } catch (error) {
            if (error.name === 'AbortError') {
                console.log('Fetch aborted by user');
                assistantContent.innerHTML += '<p class="text-gray-500 mt-2">[Response stopped by user]</p>';
            } else {
                console.error('Error:', error);
                assistantContent.innerHTML = '<p class="text-red-500">An error occurred. Please try again.</p>';
                
                // Add retry button
                const retryButton = document.createElement('button');
                retryButton.className = 'mt-2 px-3 py-1 bg-purple-500 text-white rounded hover:bg-purple-600';
                retryButton.textContent = 'Retry';
                retryButton.onclick = () => chatForm.dispatchEvent(new Event('submit'));
                assistantContent.appendChild(retryButton);
            }
        } finally {
            isWaitingForResponse = false;
            isStreaming = false;
            abortController = null;
            messageInput.focus();

            // Clear file input
            const fileInput = document.getElementById('pdf-upload');
            fileInput.value = '';

            // Hide and clear attachment preview
            const preview = document.getElementById('attachment-preview');
            const fileNameSpan = document.getElementById('file-name');
            preview.classList.add('hidden');
            fileNameSpan.textContent = '';
            
            // Show send button and hide stop button
            sendButton.classList.remove('hidden');
            stopButton.classList.add('hidden');
        }
    });

        // Add event listener for the stop button
        stopButton.addEventListener('click', () => {
            if (isStreaming && abortController) {
                isStreaming = false;
                abortController.abort();
                
                // Immediately show send button and hide stop button
                sendButton.classList.remove('hidden');
                stopButton.classList.add('hidden');
            }
        });
    
        // New chat button handler
        newChatBtn.addEventListener('click', newConversation);
    
        // Example prompt handlers
        examplePrompts.forEach(prompt => {
            prompt.addEventListener('click', () => {
                messageInput.value = prompt.textContent.trim().replace(/"/g, '');
                resizeTextarea();
                sendButton.disabled = false;
                chatForm.dispatchEvent(new Event('submit'));
            });
        });
            
        function isMobileDevice() {
            return /Android|iPhone|iPad|iPod|Opera Mini|IEMobile|WPDesktop/i.test(navigator.userAgent);
        }

        messageInput.addEventListener('keydown', (e) => {
            const isMobile = isMobileDevice();

            if (e.key === 'Enter') {
                if (isMobile) {
                    // On mobile, allow new line (default behavior)
                    return;
                }

                if (!e.shiftKey) {
                    e.preventDefault(); // Prevent newline
                    chatForm.dispatchEvent(new Event('submit'));
                }
            }
        });

    
        // Auto-resize textarea as user types
        messageInput.addEventListener('input', () => {
            resizeTextarea();
            sendButton.disabled = messageInput.value.trim() === '';
        });

        // Handle paste events to maintain formatting
        messageInput.addEventListener('paste', async (e) => {
            const items = e.clipboardData.items;
            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                if (item.type.indexOf("image") !== -1) {
                    const blob = item.getAsFile();
                    if (blob) {
                        // Optional: Show a preview or simulate file selection
                        const file = new File([blob], "pasted-image.png", { type: blob.type });

                        // Update the file input with the pasted image
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);
                        fileInput.files = dataTransfer.files;

                        // Show image preview
                        const img = document.createElement('img');
                        img.src = URL.createObjectURL(file);
                        img.className = 'max-h-24 rounded';

                        // Clear and append image to preview area
                        fileNameSpan.innerHTML = '';
                        fileNameSpan.appendChild(img);

                        attachmentPreview.classList.remove('hidden');
                    }
                }
            }

            // Let the paste happen for text, then resize the textarea
            setTimeout(() => {
                resizeTextarea();
                sendButton.disabled = messageInput.value.trim() === '';
            }, 0);
        });

        // Handle conversation deletion
        document.addEventListener('click', async (e) => {
            if (e.target.closest('.delete-conversation-btn')) {
                const button = e.target.closest('.delete-conversation-btn');
                const id = button.getAttribute('data-id');
                
                if (confirm('Are you sure you want to delete this conversation?')) {
                    try {
                        const response = await fetch(`/delete-conversation/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            }
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            // Remove the conversation from the sidebar
                            button.closest('.conversation-item').remove();
                            
                            // If we deleted the current conversation, start a new one
                            if (currentConversationId === id) {
                                newConversation();
                            }
                        } else {
                            alert('Failed to delete conversation');
                        }
                    } catch (error) {
                        console.error('Error deleting conversation:', error);
                        alert('Error deleting conversation');
                    }
                }
            }
        });

        // Edit Title
        // Handle conversation title editing
        document.addEventListener('click', async (e) => {
            // Edit button clicked
            if (e.target.closest('.edit-conversation-btn')) {
                e.stopPropagation();
                const button = e.target.closest('.edit-conversation-btn');
                const conversationItem = button.closest('.conversation-item');
                const titleSpan = conversationItem.querySelector('.conversation-title');
                const titleInput = conversationItem.querySelector('.conversation-title-input');
                
                // Hide span, show input
                titleSpan.classList.add('hidden');
                titleInput.classList.remove('hidden');
                titleInput.focus();
                titleInput.select();
                
                // Save on Enter or blur
                const saveTitle = async () => {
                    const newTitle = titleInput.value.trim();
                    if (!newTitle) {
                        titleInput.value = titleSpan.textContent;
                        titleInput.classList.add('hidden');
                        titleSpan.classList.remove('hidden');
                        return;
                    }
                    
                    const id = button.getAttribute('data-id');
                    
                    try {
                        const response = await fetch(`/update-conversation-title/${id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ title: newTitle })
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            titleSpan.textContent = newTitle;
                            titleInput.classList.add('hidden');
                            titleSpan.classList.remove('hidden');
                        } else {
                            alert('Failed to update title');
                            titleInput.classList.add('hidden');
                            titleSpan.classList.remove('hidden');
                        }
                    } catch (error) {
                        console.error('Error updating title:', error);
                        alert('Error updating title');
                        titleInput.classList.add('hidden');
                        titleSpan.classList.remove('hidden');
                    }
                };
                
                titleInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        saveTitle();
                    } else if (e.key === 'Escape') {
                        titleInput.value = titleSpan.textContent;
                        titleInput.classList.add('hidden');
                        titleSpan.classList.remove('hidden');
                    }
                });
                
                titleInput.addEventListener('blur', saveTitle, { once: true });
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const trigger = document.getElementById('dropdownTrigger');
            const dropdown = document.getElementById('modelForm');
            const dropdownItems = document.querySelectorAll('.dropdown-item[data-model]');
            const modelInput = document.getElementById('aiModelInput');
            const modelForm = document.getElementById('modelForm');

            // Function to check if model is Claude
            function isClaudeModel(model) {
                return model.toLowerCase().includes('claude');
            }

            // Function to check if model is Grok
            function isGrokModel(model) {
                return model.toLowerCase().includes('grok') || model.toLowerCase().includes('aurora');
            }

            // Function to check if model is Gemini
            function isGeminiModel(model) {
                return model.toLowerCase().includes('gemini');
            }

            // Toggle dropdown
            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdown.classList.toggle('hidden');
            });

            // Handle model selection
            dropdownItems.forEach(function(item) {
                item.addEventListener('click', async function(event) {
                    event.preventDefault();
                    
                    var isDisabled = this.style.pointerEvents === 'none';
                    if (isDisabled) {
                        return;
                    }
                    
                    const selectedModel = this.getAttribute('data-model');
                    const selectedLabel = this.textContent.trim().replace('✓', '').trim();
                    
                    dropdown.classList.add('hidden');
                    
                    const triggerSpan = trigger.querySelector('span');
                    const originalText = triggerSpan.innerHTML;
                    triggerSpan.innerHTML = '<span class="text-gray-400">Changing model...</span>';
                    
                    try {
                        const response = await fetch('{{ route("select-model") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                aiModel: selectedModel
                            })
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            triggerSpan.innerHTML = selectedLabel + 
                                '@if($isLocked)<small class="text-orange-400 ml-2">(Limited)</small>@endif';
                            
                            dropdownItems.forEach(function(dropdownItem) {
                                dropdownItem.classList.remove('bg-gray-700', 'font-semibold');
                                const checkmark = dropdownItem.querySelector('.text-green-400');
                                if (checkmark) {
                                    checkmark.remove();
                                }
                            });
                            
                            const checkmark = document.createElement('span');
                            checkmark.className = 'ml-2 text-green-400';
                            checkmark.textContent = '✓';
                            item.classList.add('bg-gray-700', 'font-semibold');
                            item.appendChild(checkmark);
                            
                            // Update feature availability
                            updateFeatureAvailabilityForModel(selectedModel, data.is_claude || false);
                            
                            showNotification('✓ Model updated to ' + selectedLabel, 'success');
                            
                        } else {
                            triggerSpan.innerHTML = originalText;
                            showNotification(data.message || 'Failed to update model', 'error');
                        }
                        
                    } catch (error) {
                        console.error('Error updating model:', error);
                        triggerSpan.innerHTML = originalText;
                        showNotification('Error updating model. Please try again.', 'error');
                    }
                });
            });

            document.addEventListener('click', function(e) {
                if (!dropdown.contains(e.target) && !trigger.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });

            // Function to check if model supports image generation
            function supportsImageGeneration(model) {
                const modelLower = model.toLowerCase();
                
                // OpenAI models that support image generation
                if (modelLower.includes('dall-e') || 
                    modelLower.includes('gpt-4') || 
                    modelLower.includes('gpt-4o')) {
                    return true;
                }
                
                // Grok models - ANY Grok model can use image generation
                if (modelLower.includes('grok')) {
                    return true;
                }
                
                // Aurora (if it's an alias for Grok image models)
                if (modelLower.includes('aurora')) {
                    return true;
                }
                
                return false;
            }

            // Function to check if model is Grok
            function isGrokModel(model) {
                return model.toLowerCase().includes('grok');
            }

            // Function to check if model is Gemini
            function isGeminiModel(model) {
                return model.toLowerCase().includes('gemini');
            }

            // Function to check if model is Claude
            function isClaudeModel(model) {
                return model.toLowerCase().includes('claude');
            }

            // ✅ DYNAMIC: Replace hardcoded arrays with data from server
            const claudeWebSearchModels = @json($claudeWebSearchModels ?? []);
            const geminiSearchSupportedModels = @json($geminiWebSearchModels ?? []);

            // Function to check if Claude model supports web search
            function claudeSupportsWebSearch(model) {
                return claudeWebSearchModels.includes(model);
            }

            // Function to check if Gemini model supports web search  
            function geminiSupportsWebSearch(model) {
                return geminiSearchSupportedModels.includes(model);
            }
            
            // Function to update features based on model
            function updateFeatureAvailabilityForModel(model, isClaude) {
                const createImageWrapper = document.getElementById('create-image-wrapper');
                const createImageCheckbox = document.getElementById('create_image');
                const webSearchCheckbox = document.getElementById('web_search');
                const webSearchLabel = document.getElementById('web-search-label');
                const webSearchOption = document.getElementById('web-search-option');
                const selectedToolDisplay = document.getElementById('selected-tool-display');
                const selectedToolName = document.getElementById('selected-tool-name');
                
                // Check model types
                const isGemini = isGeminiModel(model);
                const isGrok = isGrokModel(model);
                const supportsImages = supportsImageGeneration(model);
                
                console.log('Model:', model, 'IsGrok:', isGrok, 'SupportsImages:', supportsImages);
                
                // IMAGE GENERATION FEATURE (keep existing logic)
                if (createImageWrapper && createImageCheckbox) {
                    if (supportsImages) {
                        createImageWrapper.style.display = 'block';
                        createImageCheckbox.disabled = false;
                        console.log('Image generation enabled for model:', model);
                    } else {
                        createImageWrapper.style.display = 'none';
                        createImageCheckbox.checked = false;
                        createImageCheckbox.disabled = true;
                        console.log('Image generation disabled for model:', model);
                    }
                }
                
                // ✅ UPDATED: WEB SEARCH FEATURE using dynamic checks
                if (webSearchCheckbox && webSearchLabel && webSearchOption) {
                    if (isGrok) {
                        // Grok supports Live Search (keep existing logic)
                        webSearchCheckbox.disabled = false;
                        webSearchLabel.innerHTML = '🔍 Live Search (Grok)';
                        webSearchOption.classList.remove('bg-gray-50', 'cursor-not-allowed');
                        webSearchOption.classList.add('hover:bg-gray-100', 'cursor-pointer');
                        webSearchOption.title = 'Real-time search from web and X (Twitter)';
                        
                        webSearchCheckbox.setAttribute('data-label', 'Live Search (Grok)');
                        
                        if (webSearchCheckbox.checked && selectedToolDisplay && selectedToolName) {
                            selectedToolName.textContent = 'Live Search (Grok)';
                        }
                    } else if (isGemini) {
                        // ✅ Use dynamic database check instead of hardcoded array
                        const geminiSupportsSearch = geminiSupportsWebSearch(model);
                        
                        if (geminiSupportsSearch) {
                            webSearchCheckbox.disabled = false;
                            webSearchLabel.innerHTML = '🔍 Search Web (Gemini Grounding)';
                            webSearchOption.classList.remove('bg-gray-50', 'cursor-not-allowed');
                            webSearchOption.classList.add('hover:bg-gray-100', 'cursor-pointer');
                            webSearchOption.title = 'Google Search grounding - $35 per 1,000 queries';
                            
                            webSearchCheckbox.setAttribute('data-label', 'Search Web (Gemini)');
                            
                            if (webSearchCheckbox.checked && selectedToolDisplay && selectedToolName) {
                                selectedToolName.textContent = 'Search Web (Gemini)';
                            }
                        } else {
                            const wasChecked = webSearchCheckbox.checked;
                            webSearchCheckbox.checked = false;
                            webSearchCheckbox.disabled = true;
                            webSearchLabel.innerHTML = '🔍 Search Web <span class="text-red-500 text-xs">(Not available for this model)</span>';
                            webSearchOption.classList.add('bg-gray-50', 'cursor-not-allowed');
                            webSearchOption.classList.remove('hover:bg-gray-100', 'cursor-pointer');
                            webSearchOption.title = 'Web search is not available for this Gemini model';
                            
                            if (wasChecked && selectedToolDisplay) {
                                selectedToolDisplay.classList.add('hidden');
                                selectedToolName.textContent = 'None';
                            }
                        }
                    } else if (isClaude) {
                        // ✅ Use dynamic database check instead of hardcoded array  
                        const supportsWebSearch = claudeSupportsWebSearch(model);
                        
                        if (supportsWebSearch) {
                            webSearchCheckbox.disabled = false;
                            webSearchLabel.innerHTML = '🔍 Search Web (Claude)';
                            webSearchOption.classList.remove('bg-gray-50', 'cursor-not-allowed');
                            webSearchOption.classList.add('hover:bg-gray-100', 'cursor-pointer');
                            webSearchOption.title = '';
                            
                            webSearchCheckbox.setAttribute('data-label', 'Search Web (Claude)');
                            
                            if (webSearchCheckbox.checked && selectedToolDisplay && selectedToolName) {
                                selectedToolName.textContent = 'Search Web (Claude)';
                            }
                        } else {
                            const wasChecked = webSearchCheckbox.checked;
                            webSearchCheckbox.checked = false;
                            webSearchCheckbox.disabled = true;
                            webSearchLabel.innerHTML = '🔍 Search Web <span class="text-red-500 text-xs">(Not available)</span>';
                            webSearchOption.classList.add('bg-gray-50', 'cursor-not-allowed');
                            webSearchOption.classList.remove('hover:bg-gray-100', 'cursor-pointer');
                            webSearchOption.title = 'Web search is not available for this Claude model';
                            
                            if (wasChecked && selectedToolDisplay) {
                                selectedToolDisplay.classList.add('hidden');
                                selectedToolName.textContent = 'None';
                            }
                        }
                    } else {
                        // OpenAI models (keep existing logic)
                        webSearchCheckbox.disabled = false;
                        webSearchLabel.innerHTML = '🔍 Search Web (OpenAI)';
                        webSearchOption.classList.remove('bg-gray-50', 'cursor-not-allowed');
                        webSearchOption.classList.add('hover:bg-gray-100', 'cursor-pointer');
                        webSearchOption.title = '';
                        
                        webSearchCheckbox.setAttribute('data-label', 'Search Web (OpenAI)');
                        
                        if (webSearchCheckbox.checked && selectedToolDisplay && selectedToolName) {
                            selectedToolName.textContent = 'Search Web (OpenAI)';
                        }
                    }
                }
                
                // Check if any tool is still checked, if not hide the display
                if (selectedToolDisplay) {
                    const anyToolChecked = document.querySelector('.tool-option:checked');
                    if (!anyToolChecked) {
                        selectedToolDisplay.classList.add('hidden');
                        selectedToolName.textContent = 'None';
                    }
                }
            }

               // Initial update on page load
            const currentModel = "{{ Auth::user()->selected_model ?? 'gpt-4o-mini' }}";
            updateFeatureAvailabilityForModel(currentModel, isClaudeModel(currentModel));
                 
            // ✅ UPDATED: Update form validation to use dynamic checks
            const chatForm = document.getElementById('chat-form');
            if (chatForm) {
                chatForm.addEventListener('submit', function(e) {
                    const currentSelectedModel = "{{ Auth::user()->selected_model ?? 'gpt-4o-mini' }}";
                    const isClaude = isClaudeModel(currentSelectedModel);
                    const isGemini = isGeminiModel(currentSelectedModel);
                    const isGrok = isGrokModel(currentSelectedModel);
                    const supportsImages = supportsImageGeneration(currentSelectedModel);
                    
                    console.log('Form submit - Model:', currentSelectedModel, 'IsGrok:', isGrok, 'Supports Images:', supportsImages);
                    
                    // Validate image generation
                    const createImageCheckbox = document.getElementById('create_image');
                    if (createImageCheckbox && createImageCheckbox.checked) {
                        if (!supportsImages) {
                            e.preventDefault();
                            e.stopPropagation();
                            alert('❌ Image generation is not supported with this model. Please select a model that supports image generation:\n\n• OpenAI: GPT-4, GPT-4o, DALL-E models\n• Grok: All Grok models support image generation');
                            return false;
                        }
                    }
                    
                    // ✅ UPDATED: Validate web search using dynamic checks
                    const webSearchCheckbox = document.getElementById('web_search');
                    if (webSearchCheckbox && webSearchCheckbox.checked) {
                        if (isClaude) {
                            const supportsWebSearch = claudeSupportsWebSearch(currentSelectedModel);
                            if (!supportsWebSearch) {
                                e.preventDefault();
                                e.stopPropagation();
                                alert('❌ Web search is not available for this Claude model.\n\nPlease check the AI Settings to see which models support web search.');
                                return false;
                            }
                        } else if (isGemini) {
                            const supportsSearch = geminiSupportsWebSearch(currentSelectedModel);
                            
                            if (!supportsSearch) {
                                e.preventDefault();
                                e.stopPropagation();
                                alert('❌ Web search is not available for this Gemini model.\n\nPlease check the AI Settings to see which models support web search.\n\nNote: Google Search grounding costs $35 per 1,000 queries.');
                                return false;
                            }
                        }
                        // Grok always supports Live Search, so no additional validation needed
                    }
                });
            }

         
            
            // Notification function
            function showNotification(message, type = 'success') {
                const existing = document.getElementById('model-notification');
                if (existing) {
                    existing.remove();
                }
                
                const notification = document.createElement('div');
                notification.id = 'model-notification';
                notification.className = `fixed top-20 right-4 z-50 px-4 py-3 rounded-lg shadow-lg ${
                    type === 'success' ? 'bg-green-500' : 'bg-red-500'
                } text-white animate-fade-in`;
                notification.textContent = message;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.opacity = '0';
                    notification.style.transition = 'opacity 0.3s';
                    setTimeout(() => notification.remove(), 300);
                }, 3000);
            }
        });
    </script>

    <style>
        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fade-in 0.3s ease-out;
        }
    </style>

    <script>
        document.getElementById('toggle-tools').addEventListener('click', function () {
            const dropdown = document.getElementById('tools-dropdown');
            dropdown.classList.toggle('hidden');
        });

        document.addEventListener('click', function (e) {
            const dropdown = document.getElementById('tools-dropdown');
            const toggle = document.getElementById('toggle-tools');
            if (!dropdown.contains(e.target) && !toggle.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>

    {{-- DRAG AND DROP --}}
    <script>
        // Use the form (or input-area) as drop target
        const dropTarget = document.getElementById('chat-container'); // or just messageInput or a parent div

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropTarget.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        // Optional: visual highlight on drag (if desired)
        dropTarget.addEventListener('dragover', () => {
            dropTarget.classList.add('ring-2', 'ring-purple-400'); // temporary glow effect
        });
        dropTarget.addEventListener('dragleave', () => {
            dropTarget.classList.remove('ring-2', 'ring-purple-400');
        });
        dropTarget.addEventListener('drop', (e) => {
            dropTarget.classList.remove('ring-2', 'ring-purple-400');

            const file = e.dataTransfer.files[0];
            if (!file) return;

            // Optional: validate file type
            if (!file.type.match(/image.*|pdf|msword|officedocument|text|application/)) {
                alert("Unsupported file type.");
                return;
            }

            // Programmatically assign dropped file to hidden file input
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileInput.files = dataTransfer.files;

            // Show preview
            fileNameSpan.innerHTML = '';
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.className = 'max-h-24 rounded';
                fileNameSpan.appendChild(img);
            } else {
                fileNameSpan.textContent = file.name;
            }

            attachmentPreview.classList.remove('hidden');
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const toolOptions = document.querySelectorAll(".tool-option");
            const toolDisplayWrapper = document.getElementById("selected-tool-display");
            const toolDisplay = document.getElementById("selected-tool-name");

            toolOptions.forEach(option => {
                option.addEventListener("change", function () {
                    if (this.checked) {
                        // Uncheck all other options
                        toolOptions.forEach(opt => {
                            if (opt !== this) opt.checked = false;
                        });

                        // Show and update selected tool
                        toolDisplay.textContent = this.dataset.label;
                        toolDisplayWrapper.classList.remove("hidden");
                    } else {
                        // Hide display if none selected
                        const anyChecked = Array.from(toolOptions).some(opt => opt.checked);
                        if (!anyChecked) {
                            toolDisplayWrapper.classList.add("hidden");
                            toolDisplay.textContent = "None";
                        }
                    }
                });
            });
        });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const autoOptimizeCheckbox = document.getElementById('auto-optimize-checkbox');
        const crossProviderCheckbox = document.getElementById('cross-provider-checkbox');
        
        // Load saved states (default auto-optimize to true)
        const savedAutoOptimize = localStorage.getItem('auto_optimize_model');
        const savedCrossProvider = localStorage.getItem('allow_cross_provider');
        
        if (savedAutoOptimize !== null) {
            autoOptimizeCheckbox.checked = savedAutoOptimize === 'true';
        }
        
        if (savedCrossProvider !== null) {
            crossProviderCheckbox.checked = savedCrossProvider === 'true';
        }
        
        // Handle auto-optimize checkbox changes
        autoOptimizeCheckbox.addEventListener('change', function() {
            localStorage.setItem('auto_optimize_model', this.checked);
            
            // If turning off auto-optimize, also turn off cross-provider
            if (!this.checked && crossProviderCheckbox.checked) {
                crossProviderCheckbox.checked = false;
                localStorage.setItem('allow_cross_provider', 'false');
            }
            
            showOptimizationNotification(
                this.checked ? '✓ Auto-optimization enabled (Same Provider)' : '✗ Auto-optimization disabled',
                this.checked ? 'success' : 'info'
            );
        });
        
        // Handle cross-provider checkbox changes
        crossProviderCheckbox.addEventListener('change', function() {
            localStorage.setItem('allow_cross_provider', this.checked);
            
            // If enabling cross-provider, must also enable auto-optimize
            if (this.checked && !autoOptimizeCheckbox.checked) {
                autoOptimizeCheckbox.checked = true;
                localStorage.setItem('auto_optimize_model', 'true');
            }
            
            const message = this.checked 
                ? '✓ Smart Selection enabled (Any Provider)' 
                : '✗ Smart Selection disabled';
            
            showOptimizationNotification(message, this.checked ? 'success' : 'info');
        });
        
        function showOptimizationNotification(message, type = 'success') {
            const notification = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-purple-600' : 'bg-blue-600';
            
            notification.className = `fixed top-20 right-4 z-50 px-4 py-2 rounded-lg shadow-lg ${bgColor} text-white text-sm animate-fade-in`;
            notification.innerHTML = `
                <div class="flex items-center space-x-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
                    </svg>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.3s';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
    });
    
    // Update the form submission to include both parameters
    const originalFormSubmit = chatForm.addEventListener;
    chatForm.addEventListener('submit', function(e) {
        const autoOptimizeCheckbox = document.getElementById('auto-optimize-checkbox');
        const crossProviderCheckbox = document.getElementById('cross-provider-checkbox');
        
        // Add hidden inputs for the checkboxes
        let autoOptimizeInput = document.querySelector('input[name="auto_optimize_model"]');
        if (!autoOptimizeInput) {
            autoOptimizeInput = document.createElement('input');
            autoOptimizeInput.type = 'hidden';
            autoOptimizeInput.name = 'auto_optimize_model';
            chatForm.appendChild(autoOptimizeInput);
        }
        autoOptimizeInput.value = autoOptimizeCheckbox?.checked ? '1' : '0';
        
        let crossProviderInput = document.querySelector('input[name="allow_cross_provider"]');
        if (!crossProviderInput) {
            crossProviderInput = document.createElement('input');
            crossProviderInput.type = 'hidden';
            crossProviderInput.name = 'allow_cross_provider';
            chatForm.appendChild(crossProviderInput);
        }
        crossProviderInput.value = crossProviderCheckbox?.checked ? '1' : '0';
        
        // Log for debugging
        console.log('Submitting with:', {
            auto_optimize: autoOptimizeCheckbox?.checked,
            cross_provider: crossProviderCheckbox?.checked
        });
    });
</script>

</body>
</html>