<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAG Chat - Clever Creator.com Design</title>
    @include('admin.layouts.analytics')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" type="image/png" href="{{ config('filesystems.disks.azure.url') . config('filesystems.disks.azure.container') . '/' . $siteSettings->favicon }}">z
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f0f2f5;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo h1 {
            font-weight: 600;
            font-size: 1.8rem;
        }
        
        .logo-icon {
            background-color: white;
            color: #6a11cb;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }
        
        .main-container {
            display: flex;
            flex: 1;
        }
        
        .sidebar {
            width: 280px;
            background-color: white;
            border-right: 1px solid #e0e0e0;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            padding: 18px 20px;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            color: #444;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .new-session-btn {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: transform 0.2s;
        }
        
        .new-session-btn:hover {
            transform: scale(1.1);
        }
        
        .sessions-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px 0;
        }
        
        .session-item {
            padding: 12px 20px;
            cursor: pointer;
            transition: background-color 0.2s;
            border-left: 3px solid transparent;
            display: flex;
            align-items: center;
            gap: 10px;
            overflow: hidden; /* Prevent horizontal overflow */
        }
        
        .session-item:hover {
            background-color: #f8f9fa;
        }
        
        .session-item.active {
            background-color: #f0f4ff;
            border-left: 3px solid #2575fc;
        }

         .session-content {
            flex: 1; /* Take available space */
            min-width: 0; /* Allow text truncation */
        }
        
        .delete-session-btn {
            flex-shrink: 0; /* Prevent button from shrinking */
            margin-left: 10px; /* Add spacing between text and button */
        }
        
        .session-icon {
            color: #6a11cb;
            font-size: 1rem;
        }
        
        .session-info {
            flex: 1;
            overflow: hidden;
        }
        
        .session-name {
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .session-date {
            font-size: 0.8rem;
            color: #777;
        }
        
        .content-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .container {
            display: flex;
            flex: 1;
            padding: 20px;
            gap: 20px;
            max-width: 1600px;
            margin: 0 auto;
            width: 100%;
        }
        
        .panel {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .document-panel {
            flex: 1;
            min-width: 300px;
        }
        
        .chat-panel {
            flex: 1;
            min-width: 300px;
        }
        
        .panel-header {
            padding: 18px 24px;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            color: #444;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .panel-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 20px;
        }
        
        .upload-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .file-upload-container {
            position: relative;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            background-color: #f8fafc;
            transition: border-color 0.3s;
        }
        
        .file-upload-container:hover {
            border-color: #6a11cb;
        }
        
        .file-upload-container i {
            font-size: 2.5rem;
            color: #6a11cb;
            margin-bottom: 10px;
        }
        
        .file-upload-container p {
            margin-bottom: 15px;
            color: #64748b;
        }
        
        .file-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .upload-btn {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(106, 17, 203, 0.3);
        }
        
        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(106, 17, 203, 0.4);
        }
        
        .document-preview {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .document-view {
            flex: 1;
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .document-view iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .document-info {
            padding: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .document-icon {
            background-color: #eef5ff;
            color: #2575fc;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .document-details h4 {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .document-details p {
            color: #777;
            font-size: 0.9rem;
        }
        
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .chat-box {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
            padding: 15px;
            background-color: #f8fafc;
            border-radius: 12px;
        }
        
        .message {
            max-width: 85%;
            padding: 15px 20px;
            border-radius: 18px;
            line-height: 1.5;
            position: relative;
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .user-message {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 5px;
        }
        
        .assistant-message {
            background-color: white;
            color: #333;
            border: 1px solid #e0e0e0;
            align-self: flex-start;
            border-bottom-left-radius: 5px;
        }
        
        .message-header {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .user-message .message-header {
            color: rgba(255, 255, 255, 0.85);
        }
        
        .assistant-message .message-header {
            color: #6a11cb;
        }
        
        .message-icon {
            margin-right: 8px;
            font-size: 0.9rem;
        }
        
        .typing-indicator {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            background-color: white;
            border: 1px solid #e0e0e0;
            border-radius: 18px;
            align-self: flex-start;
            max-width: 120px;
            margin-top: 10px;
        }
        
        .typing-text {
            color: #777;
            font-size: 0.9rem;
            margin-left: 8px;
        }
        
        .dot {
            width: 8px;
            height: 8px;
            background-color: #6a11cb;
            border-radius: 50%;
            margin: 0 2px;
            animation: bounce 1.5s infinite;
        }
        
        .dot:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .dot:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        .chat-form {
            padding-top: 20px;
            display: flex;
            gap: 10px;
        }
        
        .message-input {
            flex: 1;
            padding: 15px 20px;
            border: 1px solid #ddd;
            border-radius: 30px;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.3s;
        }
        
        .message-input:focus {
            border-color: #6a11cb;
            box-shadow: 0 0 0 3px rgba(106, 17, 203, 0.1);
        }
        
        .send-btn {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: transform 0.2s;
        }
        
        .send-btn:hover {
            transform: scale(1.05);
        }
        
        .footer {
            text-align: center;
            padding: 15px;
            color: #777;
            font-size: 0.9rem;
            border-top: 1px solid #eee;
            background-color: white;
        }

        .message-text ul {
            padding-left: 20px;
            list-style-type: disc;
        }

        .message-text strong {
            font-weight: bold;
        }

        
        /* Responsive design */
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #e0e0e0;
            }
            
            .container {
                flex-direction: column;
                padding: 10px;
            }
            
            .panel {
                min-height: 50vh;
            }
            
            .message {
                max-width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="header" style="
        position: sticky; 
        top: 0; 
        z-index: 1000; 
        background-color: white; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        padding: 10px 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    ">
        <div class="logo" style="display: flex; align-items: center;">
            <div class="logo-icon">
                <i class="fas fa-file-pdf"></i>
            </div>
            <h1 style="margin-left: 10px;">RAG Clever Creator</h1>
        </div>

        @if(Auth::check())
            <a href="{{ Auth::user()->role === 'admin' ? route('admin.dashboard') : route('user.dashboard') }}"
            class="dashboard-button" 
            style="padding: 8px 16px; background-color: #6c5ce7; color: white; border-radius: 6px; text-decoration: none;">
                Go to Dashboard
            </a>
        @endif
    </div>


    <div class="main-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <span>Chat Sessions</span>
                <button class="new-session-btn" id="new-session-btn">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <!-- Update your sessions-list HTML -->
            <div class="sessions-list" id="sessions-list">
                @foreach($sessions as $sess)
                <div class="session-item flex justify-between items-center p-2 border rounded mb-2 {{ $sess->id == session('session_id') ? 'bg-blue-100' : '' }} overflow-hidden" 
                    data-session-id="{{ $sess->id }}">
                    <div class="session-content flex items-center gap-2">
                        <i class="fas fa-file-alt session-icon text-gray-600"></i>
                        <div class="session-info min-w-0"> <!-- Add min-w-0 for truncation -->
                            <div class="session-name text-sm font-medium">{{ $sess->file_name }}</div>
                            <div class="session-date text-xs text-gray-500">{{ $sess->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                    <button class="delete-session-btn text-red-500 hover:text-red-700" data-id="{{ $sess->id }}" title="Delete session">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="content-area">
            <div class="container">
                <!-- Document Panel -->
                <div class="panel document-panel">
                    <div class="panel-header">
                        <span><i class="fas fa-file-alt"></i> Document</span>
                    </div>
                    <div class="panel-content">
                        @php
                            $currentSession = session('session_id') ? \App\Models\RagSession::find(session('session_id')) : null;
                        @endphp

                        @if(!$currentSession)
                            <form action="/upload" method="post" enctype="multipart/form-data" class="upload-form">
                                @csrf
                                <div class="file-upload-container">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <h3>Upload a PDF or DOCX file</h3>
                                    <p>Supported formats: PDF, DOCX (Max file size: 10MB)</p>
                                    <input type="file" name="file" accept=".pdf,.docx" class="file-input" required>
                                    <button type="button" class="upload-btn">
                                        <i class="fas fa-upload"></i> Choose File
                                    </button>
                                </div>
                                <button type="submit" class="upload-btn">
                                    <i class="fas fa-paper-plane"></i> Upload Document
                                </button>
                            </form>
                        @else
                            <div class="document-preview">
                                <div class="document-view">
                                    <iframe src="{{ Storage::url($currentSession->file_path) }}"></iframe>
                                </div>
                                <div class="document-info">
                                    <div class="document-icon">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div class="document-details">
                                        <h4>{{ basename($currentSession->file_name) }}</h4>
                                        <p>Uploaded: {{ $currentSession->created_at->diffForHumans() }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Chat Panel -->
                <div class="panel chat-panel">
                    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span><i class="fas fa-comments"></i> Chat with Document</span>
                            @if($currentSession)
                                <button id="summarize-btn" title="Summarize Document" style="border: none; background: none; cursor: pointer;">
                                    <i class="fas fa-lightbulb" style="color: #facc15;"></i>
                                </button>
                            @endif
                        </div>
                        <span class="ai-status">
                            <i class="fas fa-circle" style="color: {{ $currentSession ? '#4ade80' : '#eab308' }}; font-size: 0.7rem;"></i> 
                            {{ $currentSession ? 'Ready' : 'Upload document first' }}
                        </span>
                    </div>

                    <div class="panel-content">
                        <div class="chat-container">
                            <div id="chat-box" class="chat-box">
                                @if($currentSession)
                                    @foreach($currentSession->messages as $message)
                                        <div class="message {{ $message->role === 'user' ? 'user-message' : 'assistant-message' }}">
                                            <div class="message-header">
                                                <i class="fas {{ $message->role === 'user' ? 'fa-user' : 'fa-robot' }} message-icon"></i>
                                                {{ $message->role === 'user' ? 'You' : 'Clever File Assistant' }}
                                            </div>
                                            <div class="message-text">
                                                {{ $message->content }}
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="message assistant-message">
                                        <div class="message-header">
                                            <i class="fas fa-robot message-icon"></i>
                                            Document Assistant
                                        </div>
                                        <div class="message-text">
                                            Welcome! Please upload a PDF or DOCX file to start chatting about your document.
                                        </div>
                                    </div>
                                @endif
                                
                                <div id="typing-indicator" class="typing-indicator" style="display: none;">
                                    <div class="dot"></div>
                                    <div class="dot"></div>
                                    <div class="dot"></div>
                                    <span class="typing-text">Thinking</span>
                                </div>
                            </div>
                            
                            <form id="chat-form" class="chat-form" {{ $currentSession ? '' : 'style="display: none;"' }}>
                                <textarea id="message" class="message-input" 
                                    placeholder="Ask something about your document..."
                                    {{ $currentSession ? '' : 'disabled' }} rows="1"
                                    style="overflow:hidden; resize:none; line-height:24px;">
                                </textarea>
                                <button type="submit" class="send-btn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>RAG Clever Creator &copy; {{ date('Y') }} | Session: #{{ session('session_id') ?? 'NONE' }}</p>
    </div>

    @include('admin.layouts.user_time_tracker')
    <script>
        // Set up CSRF token for Axios
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let currentSessionId = "{{ session('session_id') }}";
        
        // Get DOM elements
        const chatForm = document.getElementById('chat-form');
        const chatBox = document.getElementById('chat-box');
        const messageInput = document.getElementById('message');
        const typingIndicator = document.getElementById('typing-indicator');
        const newSessionBtn = document.getElementById('new-session-btn');
        const sessionsList = document.getElementById('sessions-list');
        
        // File upload interaction
        const fileInput = document.querySelector('.file-input');
        const uploadBtn = document.querySelector('.upload-btn[type="button"]');

        // Submit on Enter, newline on Shift+Enter
        messageInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm.dispatchEvent(new Event('submit'));
            }
        });

        // Auto-resize textarea
        messageInput.addEventListener('input', function () {
            this.style.height = 'auto'; // Reset
            const maxHeight = 5 * 24;   // 5 lines @ 24px
            const newHeight = Math.min(this.scrollHeight, maxHeight);
            this.style.height = newHeight + 'px';
        });

        
        if (uploadBtn) {
            uploadBtn.addEventListener('click', () => {
                fileInput.click();
            });
            
            fileInput.addEventListener('change', function(e) {
                if (this.files.length > 0) {
                    uploadBtn.innerHTML = `<i class="fas fa-file"></i> ${this.files[0].name}`;
                }
            });
        }

        // Summarize button click handler
        const summarizeBtn = document.getElementById('summarize-btn');

        if (summarizeBtn) {
            summarizeBtn.addEventListener('click', async () => {
                if (!currentSessionId) return;

                // Show typing indicator
                typingIndicator.style.display = 'flex';
                chatBox.scrollTop = chatBox.scrollHeight;

                try {
                    const response = await axios.post('/ask', {
                        session_id: currentSessionId,
                        message: 'Summarize this document in simple terms',
                    });

                    // Hide typing indicator
                    typingIndicator.style.display = 'none';

                    const assistantMsg = document.createElement('div');
                    assistantMsg.className = 'message assistant-message';
                    assistantMsg.innerHTML = `
                        <div class="message-header">
                            <i class="fas fa-robot message-icon"></i>
                            Document Assistant
                        </div>
                        <div class="message-text">${marked.parse(response.data.reply)}</div>
                    `;
                    chatBox.appendChild(assistantMsg);
                    chatBox.scrollTop = chatBox.scrollHeight;

                    loadSessions();
                } catch (error) {
                    typingIndicator.style.display = 'none';
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'message assistant-message';
                    errorMsg.innerHTML = `
                        <div class="message-header">
                            <i class="fas fa-exclamation-triangle message-icon"></i>
                            Error
                        </div>
                        <div class="message-text">Failed to summarize document.</div>
                    `;
                    chatBox.appendChild(errorMsg);
                    chatBox.scrollTop = chatBox.scrollHeight;
                }
            });
        }

        
        // Chat form submission
        chatForm.onsubmit = async (e) => {
            e.preventDefault();
            const msg = messageInput.value.trim();
            if (!msg) return;
            
            // Add user message to chat
            const userMsg = document.createElement('div');
            userMsg.className = 'message user-message';
            userMsg.innerHTML = `
                <div class="message-header">
                    <i class="fas fa-user message-icon"></i>
                    You
                </div>
                <div class="message-text">${msg}</div>
            `;
            chatBox.appendChild(userMsg);
            messageInput.value = '';
            
            // Scroll to bottom
            chatBox.scrollTop = chatBox.scrollHeight;
            
            // Show typing indicator
            typingIndicator.style.display = 'flex';
            chatBox.scrollTop = chatBox.scrollHeight;
            
            try {
                // Send request to server
                const response = await axios.post('/ask', {
                    session_id: currentSessionId,
                    message: msg,
                });
                
                // Hide typing indicator
                typingIndicator.style.display = 'none';
                
                // Add assistant response
                const assistantMsg = document.createElement('div');
                assistantMsg.className = 'message assistant-message';
                assistantMsg.innerHTML = `
                    <div class="message-header">
                        <i class="fas fa-robot message-icon"></i>
                        Document Assistant
                    </div>
                    <div class="message-text">${marked.parse(response.data.reply)}</div>
                `;
                chatBox.appendChild(assistantMsg);
                
                // Scroll to bottom
                chatBox.scrollTop = chatBox.scrollHeight;
                
                // Refresh the session list to show updated last message
                loadSessions();
            } catch (error) {
                // Hide typing indicator
                typingIndicator.style.display = 'none';
                
                // Show error message
                const errorMsg = document.createElement('div');
                errorMsg.className = 'message assistant-message';
                errorMsg.innerHTML = `
                    <div class="message-header">
                        <i class="fas fa-exclamation-triangle message-icon"></i>
                        Error
                    </div>
                    <div class="message-text">Sorry, something went wrong. Please try again.</div>
                `;
                chatBox.appendChild(errorMsg);
                
                // Scroll to bottom
                chatBox.scrollTop = chatBox.scrollHeight;
                
                console.error('Error:', error);
            }
        };
        
        // New session button
        newSessionBtn.addEventListener('click', () => {
            window.location.href = "{{ route('rag.clear_session') }}";
        });
        
        // Session item click handler
        sessionsList.addEventListener('click', (e) => {
            const sessionItem = e.target.closest('.session-item');
            if (sessionItem) {
                const sessionId = sessionItem.dataset.sessionId;
                switchSession(sessionId);
            }
        });
        
        // Function to switch sessions
        async function switchSession(sessionId) {
            try {
                // Set the new session in Laravel session
                const response = await axios.post('/session/switch', {
                    session_id: sessionId
                });
                
                // Reload the page to show the new session
                window.location.reload();
            } catch (error) {
                console.error('Error switching session:', error);
                alert('Failed to switch session. Please try again.');
            }
        }
        
        // Function to load sessions (for future updates)
        async function loadSessions() {
            try {
                const response = await axios.get('/sessions/list');
                // Update the sessions list (implementation depends on your needs)
            } catch (error) {
                console.error('Error loading sessions:', error);
            }
        }
        
        // Auto-scroll chat to bottom on load
        window.onload = function() {
            chatBox.scrollTop = chatBox.scrollHeight;
        };
    </script>

    <script>
    document.querySelectorAll('.delete-session-btn').forEach(button => {
        button.addEventListener('click', function () {
            if (!confirm('Are you sure you want to delete this session?')) return;

            const sessionId = this.dataset.id;

            fetch(`/rag/session/${sessionId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.querySelector(`[data-session-id="${sessionId}"]`).remove();
                } else {
                    alert('Failed to delete session.');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error deleting session.');
            });
        });
    });
</script>

</body>
</html>