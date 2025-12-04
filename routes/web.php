<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\fahmidController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [fahmidController::class, 'edit'])->name('admin.profile.edit');
});

// Authenticated user routes
Route::middleware(['auth', 'verified', 'check.status', 'check.blocked.ip'])->group(function () {

    Route::get('/chattermate', function () {
        $experts = App\Models\Expert::whereIn('domain', ['expert-chat', 'ai-tutor'])->get();
        $groupedExperts = $experts->groupBy('domain')->map(function ($domainGroup) {
            return $domainGroup->groupBy('category');
        });

        $webSearchModels = App\Models\AISettings::active()
            ->where('supports_web_search', true)
            ->select('openaimodel', 'displayname')
            ->get();

        // Group by model type
        $claudeWebSearchModels = $webSearchModels->filter(function($model) {
            return stripos($model->openaimodel, 'claude') !== false;
        })->pluck('openaimodel')->toArray();
        
        $geminiWebSearchModels = $webSearchModels->filter(function($model) {
            return stripos($model->openaimodel, 'gemini') !== false;
        })->pluck('openaimodel')->toArray();

        return view('backend.chattermate.chat', compact(
            'groupedExperts', 
            'experts',
            'claudeWebSearchModels',
            'geminiWebSearchModels'
        ));
    })->name('chat.new');

    Route::match(['GET', 'POST'], '/chatss', [ChatController::class, 'chat'])->name('chatss');
    Route::post('/save-chat', [ChatController::class, 'saveChat'])->name('save-chat');
    Route::get('/get-chats', [ChatController::class, 'getChats'])->name('get-chats');
    Route::get('/get-expert-chats', [ChatController::class, 'getExpertChats'])->name('get-expert-chats');
    Route::get('/get-ai-tutor-expert-chats', [ChatController::class, 'getAiTutorExpertChats'])->name('get-ai-tutor-expert-chats');
    Route::get('/get-conversation/{id}', [ChatController::class, 'getConversation'])->name('get-conversation');
    Route::delete('/delete-conversation/{id}', [ChatController::class, 'deleteConversation'])->name('delete-conversation');
    Route::post('/translate', [ChatController::class, 'translate']);
    Route::put('/update-conversation-title/{id}', [ChatController::class, 'updateConversationTitle'])->name('update-conversation-title');
});

// Global Select Model
    Route::post('/select-model', [ChatController::class, 'selectModel'])->name('select-model');

require __DIR__.'/auth.php';
