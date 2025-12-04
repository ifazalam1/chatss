<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Chattermate AI Model Comparison</title>
    @include('admin.layouts.analytics')
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/github.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    
    <!-- MathJax for mathematical expressions -->
    <script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    
    <!-- Chart.js for graphs and charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- PDF.js for PDF preview -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    
    <!-- Mammoth.js for DOCX preview -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
    
    <link rel="shortcut icon" type="image/png" href="{{ config('filesystems.disks.azure.url') . config('filesystems.disks.azure.container') . '/' . $siteSettings->favicon }}">
    
    <!-- MathJax Configuration -->
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
        
        // PDF.js worker configuration
        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }
    </script>

    <style>
        /* ✅ Minimal attachment styling */
        .user-prompt .truncate {
            max-width: 200px;
        }
                /* ✅ User message attachment styling */
        .user-prompt .border-t {
            margin-top: 12px;
            padding-top: 12px;
        }

        /* Smooth hover effect for preview button */
        .user-prompt button:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        /* File icon animation */
        .user-prompt i.la-file,
        .user-prompt i.la-file-pdf,
        .user-prompt i.la-file-word {
            transition: transform 0.2s ease;
        }

        .user-prompt button:hover i {
            transform: scale(1.1);
        }

        /* ✅ IMPROVED: Inline attachment badge styling */
        .attachment-badge-inline {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 8px;
            margin-top: 8px;
            backdrop-filter: blur(4px);
        }

        /* Preview button mini */
        .preview-button-mini {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 8px;
            background: rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 6px;
            color: #8b5cf6;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .preview-button-mini:hover {
            background: rgba(139, 92, 246, 0.2);
            border-color: rgba(139, 92, 246, 0.5);
            transform: translateY(-1px);
        }

        .preview-button-mini i {
            font-size: 14px;
        }

        /* Better file name display */
        .attachment-badge-inline .font-medium {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Model search styling */
        #model-search-input:focus {
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        .dropdown-search-container {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        /* Smooth transitions for filtered items */
        .dropdown-model {
            transition: opacity 0.2s ease, max-height 0.2s ease;
        }

        .dropdown-provider {
            transition: opacity 0.2s ease, max-height 0.2s ease;
        }

        /* Highlight search matches (optional) */
        #model-dropdown-fixed .dropdown-model:hover {
            background-color: #f3f4f6;
        }

        /* Three-dot menu styles */
        .conversation-actions-menu {
            position: relative;
        }

        .conversation-menu-button {
            padding: 4px 8px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .conversation-menu-button:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .conversation-actions-dropdown {
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 4px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 150px;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
        }

        .conversation-actions-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .conversation-action-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            cursor: pointer;
            transition: background-color 0.2s;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
            color: #374151;
        }

        .conversation-action-item:last-child {
            border-bottom: none;
        }

        .conversation-action-item:hover {
            background-color: #f9fafb;
        }

        .conversation-action-item i {
            font-size: 16px;
        }

        .conversation-action-item.archive-action:hover {
            background-color: #fef3c7;
            color: #92400e;
        }

        .conversation-action-item.edit-action:hover {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .conversation-action-item.delete-action:hover {
            background-color: #fee2e2;
            color: #991b1b;
        }

/* Indeterminate checkbox styling */
#select-all-checkbox:indeterminate {
    background-color: #8b5cf6;
    border-color: #8b5cf6;
}

#select-all-checkbox:indeterminate::before {
    content: '';
    display: block;
    width: 10px;
    height: 2px;
    background: white;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

        /* Selection Mode Styles */
.conversation-checkbox {
    flex-shrink: 0;
}

#bulk-actions-bar {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Archived conversation styling */
.conversation-item.archived {
    background-color: #f9fafb;
    border-left: 3px solid #f59e0b;
}

/* Disabled bulk action buttons */
button:disabled {
    cursor: not-allowed;
}
        /* Mode Switch Confirmation Modal */
.mode-switch-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    backdrop-filter: blur(4px);
}

.mode-switch-modal-content {
    background: white;
    border-radius: 16px;
    padding: 32px;
    max-width: 500px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.mode-switch-modal-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.mode-switch-modal-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.mode-switch-modal-title {
    font-size: 24px;
    font-weight: 700;
    color: #1f2937;
}

.mode-switch-modal-body {
    color: #4b5563;
    font-size: 15px;
    line-height: 1.6;
    margin-bottom: 24px;
}

.mode-switch-modal-info {
    background: #f3f4f6;
    border-left: 4px solid #8b5cf6;
    padding: 12px 16px;
    border-radius: 8px;
    margin-top: 16px;
    font-size: 14px;
    color: #374151;
}

.mode-switch-modal-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.mode-switch-modal-btn {
    padding: 10px 24px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.mode-switch-modal-btn-cancel {
    background: #e5e7eb;
    color: #374151;
}

.mode-switch-modal-btn-cancel:hover {
    background: #d1d5db;
}

.mode-switch-modal-btn-confirm {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.mode-switch-modal-btn-confirm:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

/* Search highlight */
mark {
    background-color: #fef08a;
    padding: 2px 4px;
    border-radius: 2px;
    font-weight: 500;
}

/* Loading spinner */
@keyframes spin {
    to { transform: rotate(360deg); }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

/* Search result indicator */
.conversation-item .la-search {
    font-size: 14px;
}

        /* Search input animation */
#conversation-search:focus {
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
}

/* Tooltip animation */
.group:hover span {
    animation: tooltipFadeIn 0.2s ease-out;
}

@keyframes tooltipFadeIn {
    from {
        opacity: 0;
        transform: translate(-50%, -5px);
    }
    to {
        opacity: 1;
        transform: translate(-50%, 0);
    }
}

/* Smooth transition for filtered items */
.conversation-item {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.conversation-item.hidden {
    display: none;
}

        /* ✅ NEW: Optimization Mode Toggle Styles */
.optimization-mode-btn {
    background: transparent;
    color: rgba(255, 255, 255, 0.7);
    border: 1px solid transparent;
    cursor: pointer;
}

.optimization-mode-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.optimization-mode-btn.active {
    background: rgba(139, 92, 246, 0.9);
    color: white;
    border-color: rgba(139, 92, 246, 1);
    box-shadow: 0 0 10px rgba(139, 92, 246, 0.5);
}

/* Mode indicator in model panel */
.model-optimization-indicator {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 6px;
    background: rgba(139, 92, 246, 0.2);
    border: 1px solid rgba(139, 92, 246, 0.5);
    border-radius: 4px;
    font-size: 10px;
    color: #a78bfa;
    margin-left: 8px;
}

.model-optimization-indicator i {
    font-size: 12px;
}


/* Inline translation display */
.translation-inline {
    margin-top: 12px;
    padding: 12px;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border-left: 3px solid #0ea5e9;
    border-radius: 8px;
    animation: slideDown 0.3s ease-out;
}

.translation-inline-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid #bae6fd;
}

.translation-inline-label {
    font-size: 12px;
    font-weight: 600;
    color: #0369a1;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.translation-inline-actions {
    display: flex;
    gap: 6px;
}

.translation-inline-btn {
    padding: 4px 8px;
    font-size: 11px;
    background: white;
    border: 1px solid #bae6fd;
    border-radius: 4px;
    color: #0369a1;
    cursor: pointer;
    transition: all 0.2s;
}

.translation-inline-btn:hover {
    background: #f0f9ff;
    border-color: #7dd3fc;
}

.translation-inline-text {
    font-size: 14px;
    color: #0c4a6e;
    line-height: 1.6;
    white-space: pre-wrap;
    word-wrap: break-word;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Enhanced Message Design with Left/Right Layout */
.conversation-entry {
    border-bottom: none;
    padding-bottom: 0;
    margin-bottom: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* User message - Right side */
.user-prompt {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 12px 16px;
    border-radius: 18px 18px 4px 18px;
    font-size: 14px;
    font-weight: 400;
    color: #ffffff;
    white-space: pre-wrap;
    word-wrap: break-word;
    line-height: 1.6;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    position: relative;
    max-width: 75%;
    margin-left: auto; /* Push to right */
    margin-right: 0;
}

/* Assistant message - Left side */
.assistant-response {
    color: #111827;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    padding: 12px 16px;
    border-radius: 18px 18px 18px 4px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    position: relative;
    max-width: 75%;
    margin-right: auto; /* Push to left */
    margin-left: 0;
}

/* Message Action Buttons - Always visible */
.message-actions {
    display: flex;
    gap: 4px;
    margin-top: 8px;
    opacity: 1; /* Changed from 0 to 1 - always visible */
    transition: all 0.2s ease;
}

/* User message actions - Align right */
.user-prompt + .message-actions {
    justify-content: flex-end;
    margin-right: 0;
    margin-left: auto;
    max-width: 75%;
}

/* Assistant message actions - Align left */
.assistant-response .message-actions {
    justify-content: flex-start;
    margin-left: 0;
    margin-right: auto;
}

/* Optional: Add hover effect for emphasis */
.conversation-entry:hover .message-actions {
    opacity: 1;
    transform: translateY(-2px);
}

.message-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 10px;
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 11px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 500;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.message-action-btn:hover {
    background: #ffffff;
    color: #374151;
    border-color: #d1d5db;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
}

.message-action-btn i {
    font-size: 14px;
}

.message-action-btn.active {
    background: #8b5cf6;
    color: white;
    border-color: #8b5cf6;
}

/* For user message actions - purple theme */
.user-prompt + .message-actions .message-action-btn {
    background: rgba(255, 255, 255, 0.95);
    border-color: rgba(102, 126, 234, 0.3);
    color: #667eea;
}

.user-prompt + .message-actions .message-action-btn:hover {
    background: rgba(255, 255, 255, 1);
    border-color: rgba(102, 126, 234, 0.5);
    color: #5a4dd8;
}

/* For assistant message actions */
.assistant-response .message-actions .message-action-btn {
    background: rgba(139, 92, 246, 0.08);
    border-color: rgba(139, 92, 246, 0.2);
    color: #8b5cf6;
}

.assistant-response .message-actions .message-action-btn:hover {
    background: rgba(139, 92, 246, 0.15);
    border-color: rgba(139, 92, 246, 0.3);
    color: #7c3aed;
}

/* Translate dropdown */
.translate-dropdown {
    position: absolute;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    padding: 8px;
    min-width: 200px;
    z-index: 100;
    display: none;
    margin-top: 4px;
}

.translate-dropdown.show {
    display: block;
}

.translate-option {
    padding: 8px 12px;
    cursor: pointer;
    border-radius: 4px;
    font-size: 13px;
    transition: background 0.2s;
}

.translate-option:hover {
    background: #f3f4f6;
}

/* Regenerating indicator */
.regenerating-indicator {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #fef3c7;
    border: 1px solid #fbbf24;
    border-radius: 6px;
    font-size: 12px;
    color: #92400e;
    margin-top: 8px;
}

.regenerating-indicator .spinner-sm {
    width: 14px;
    height: 14px;
    border: 2px solid #fbbf24;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}


        .close-model-btn {
            cursor: pointer;
        }

        .close-model-btn:hover {
            transform: scale(1.1);
        }

        .close-model-btn:hover i {
            color: #ef4444 !important;
        }
        /* Textarea auto-resize */
        #message-input {
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
        }

        #message-input::-webkit-scrollbar {
            width: 6px;
        }

        #message-input::-webkit-scrollbar-track {
            background: transparent;
        }

        #message-input::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        /* Options dropdown animations */
        #options-dropdown {
            animation: slideUp 0.2s ease-out;
            transform-origin: bottom;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Dropdown arrow indicator */
        #options-dropdown::before {
            content: '';
            position: absolute;
            bottom: -6px;
            right: 12px;
            width: 12px;
            height: 12px;
            background: white;
            transform: rotate(45deg);
            box-shadow: 2px 2px 3px rgba(0, 0, 0, 0.1);
        }

        /* Make sure dropdown is above other elements */
        .relative {
            position: relative;
        }

        /* Smooth transitions for all interactive elements */
        #compare-form button,
        #compare-form label {
            transition: all 0.2s ease;
        }

        /* Active state for options button */
        #options-dropdown-btn.active {
            color: #fff;
            transform: rotate(90deg);
        }

        /* Image Modal Styles */
        #image-modal {
            z-index: 9999;
        }

        #image-modal img {
            object-fit: contain;
        }

        #modal-close {
            z-index: 10000;
        }

        /* Image preview in attachment */
        #file-name img {
            max-height: 80px;
            max-width: 150px;
            border-radius: 0.5rem;
            object-fit: cover;
        }

        #attachment-preview {
            display: inline-flex !important;
        }

        #attachment-preview.hidden {
            display: none !important;
        }

        #file-name {
            max-width: 250px;
        }

        /* Disabled state for create image */
        #create-image-label.opacity-50 {
            opacity: 0.5;
        }

        #create-image-label.cursor-not-allowed {
            cursor: not-allowed;
        }

        /* ✅ NEW: Attachment Preview Modal Styles */
        #attachment-preview-modal {
            z-index: 9999;
        }

        #attachment-preview-modal .modal-content {
            max-width: 90vw;
            /* max-height: 90vh; */
            overflow: hidden;
        }

        #preview-content {
            max-height: calc(90vh - 120px);
            overflow-y: auto;
        }

        #preview-content::-webkit-scrollbar {
            width: 8px;
        }

        #preview-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        #preview-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        #preview-content::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .pdf-page-canvas {
            border: 1px solid #e5e7eb;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .docx-preview {
            padding: 20px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-family: 'Times New Roman', serif;
            line-height: 1.6;
        }

        .preview-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            color: #6b7280;
        }

        .preview-loading .spinner {
            border: 4px solid #f3f4f6;
            border-top: 4px solid #8b5cf6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .preview-button {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 12px;
            color: #374151;
            cursor: pointer;
            transition: all 0.2s;
        }

        .preview-button:hover {
            background: #e5e7eb;
            color: #1f2937;
        }

        .attachment-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-top: 8px;
        }

        .attachment-badge .file-icon {
            font-size: 18px;
        }

        .attachment-badge .file-info {
            flex: 1;
        }

        .attachment-badge .file-name {
            font-weight: 500;
            font-size: 13px;
            color: #111827;
        }

        .attachment-badge .file-size {
            font-size: 11px;
            color: #6b7280;
        }

        .gradient-bg-1 {
            background: linear-gradient(to right, #1a0a24, #3a0750);
        }

        .chat-hover:hover {
            background: linear-gradient(45deg, #5447c4, #ac31a1);
        }

        .model-panel {
            min-height: 500px;
            height: 100%;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

       .model-panel.maximized {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1000;
    height: 100vh;
    margin: 0;
    border-radius: 0;
    box-shadow: none;
    display: flex;
    flex-direction: column;
}

.model-panel.maximized .model-response {
    flex: 1;
    overflow-y: auto;
    max-height: none;
}

/* Hide main chat input when maximized */
#main-chat-form.hidden-on-maximize {
    display: none !important;
}

/* Maximized chat input */
.maximized-chat-input {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1001;
    background: white;
    border-top: 2px solid #e5e7eb;
    padding: 16px;
    box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.1);
}

.maximized-header-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1001;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.maximized-header-overlay .model-name {
    font-size: 18px;
    font-weight: 600;
}

.maximized-header-overlay .close-maximize-btn {
    background: rgba(255, 255, 255, 0.2);
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.maximized-header-overlay .close-maximize-btn:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Adjust model response padding when maximized */
.model-panel.maximized .model-response {
    padding-top: 70px; /* Space for header */
    padding-bottom: 100px; /* Space for input */
}

        .model-panel.hidden-panel {
            display: none;
        }

        #models-container.has-maximized {
            position: relative;
        }

        #models-container.has-maximized::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            backdrop-filter: blur(4px);
        }

        .maximize-model-btn {
            cursor: pointer;
        }

        .maximize-model-btn:hover {
            transform: scale(1.1);
        }

        .model-response {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }

       

        .model-conversation {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .conversation-entry {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 12px;
            margin-bottom: 12px;
        }

        .conversation-entry:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .user-prompt {
            background: #f3f4f6;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.6;
        }

        /* Message content specific styling */
        .user-prompt a {
            color: #385cd1;
            text-decoration: underline;
            word-break: break-all;
            opacity: 0.9;
        }

        .user-prompt a:hover {
            opacity: 1;
        }

        .assistant-response .message-content {
            color: #1f2937;
        }

        .assistant-response {
            color: #111827;
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

        .thinking-indicator .dot {
            animation: bounce-delay 1.4s infinite ease-in-out both;
            box-shadow: 0 0 8px rgba(139, 92, 246, 0.6);
            background: linear-gradient(135deg, #a78bfa, #8b5cf6);
        }

        .thinking-indicator .dot:nth-child(1) { animation-delay: -0.32s; }
        .thinking-indicator .dot:nth-child(2) { animation-delay: -0.16s; }
        .thinking-indicator .dot:nth-child(3) { animation-delay: 0s; }

        @keyframes bounce-delay {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }

        .chart-container {
            position: relative;
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            min-height: 400px;
        }

        .chart-canvas {
            width: 100% !important;
            height: 400px !important;
        }

        .model-panel-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 16px;
            border-radius: 8px 8px 0 0;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .model-status {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.2);
        }

        .model-status.waiting {
            background: rgba(255, 193, 7, 0.8);
            color: #000;
        }

        .model-status.running {
            background: rgba(40, 167, 69, 0.8);
            animation: pulse 2s infinite;
        }

        .model-status.completed {
            background: rgba(40, 167, 69, 0.8);
        }

        .model-status.error {
            background: rgba(220, 53, 69, 0.8);
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
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
            color: #ef4444 !important;
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

        .code-block-container:hover .copy-code-button {
            opacity: 1;
        }

        .copy-code-button:hover {
            background-color: #e1e4e8;
        }

        .copy-code-button.copied {
            background-color: #28a745;
            color: white;
            border-color: #28a745;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 250px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1000;
            border-radius: 8px;
            max-height: 400px;
            overflow-y: auto;
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-provider {
            padding: 8px 12px;
            background-color: #f8f9fa;
            font-weight: 600;
            border-bottom: 1px solid #e9ecef;
            color: #495057;
        }

        .dropdown-model {
            padding: 8px 16px;
            cursor: pointer;
            transition: background-color 0.2s;
            border-bottom: 1px solid #f1f3f4;
        }

        .dropdown-model:hover {
            background-color: #e9ecef;
        }

        .dropdown-model.selected {
            background-color: #e7f3ff;
            color: #0066cc;
        }

        /* Responsive grid classes */
        .models-grid-1 { grid-template-columns: 1fr; }
        .models-grid-2 { grid-template-columns: repeat(2, 1fr); }
        .models-grid-3 { grid-template-columns: repeat(3, 1fr); }
        .models-grid-4 { grid-template-columns: repeat(4, 1fr); }

        @media (max-width: 1536px) {
            .models-grid-4 { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 1024px) {
            .models-grid-4, .models-grid-3 { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 640px) {
            .models-grid-4, .models-grid-3, .models-grid-2 { grid-template-columns: 1fr; }
        }

        /* Debug styles */
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 8px;
            margin: 8px 0;
            border-radius: 4px;
            font-size: 12px;
            font-family: monospace;
        }
    </style>
</head>
<body class="gradient-bg-1 min-h-screen flex">

    <!-- Sidebar for conversation history -->
    <div id="sidebar" class="sidebar sidebar-hidden fixed inset-y-0 left-0 z-50 w-80 bg-white shadow-lg flex flex-col">
        <!-- Sidebar Header -->
        <div class="p-4 border-b border-gray-200">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-gray-800">Chat History</h2>
                <button id="close-sidebar" class="text-gray-500 hover:text-gray-700">
                    <i class="las la-times text-xl"></i>
                </button>
            </div>
            
            <!-- Search Input -->
            <div class="relative mb-3">
                <input 
                    type="text" 
                    id="conversation-search" 
                    placeholder="Search conversations..." 
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                >
                <i class="las la-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <button id="clear-search" class="hidden absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                    <i class="las la-times-circle"></i>
                </button>
            </div>

            <!-- Filter and Selection Controls -->
            <div class="flex items-center justify-between gap-2">
                <!-- Archive Filter Dropdown -->
                <div class="relative flex-1">
                    <select id="archive-filter" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 bg-white">
                        <option value="active">Active Chats</option>
                        <option value="archived">Archived</option>
                        <option value="all">All Chats</option>
                    </select>
                </div>
                
                <!-- Select Mode Toggle -->
                <button id="toggle-select-mode" class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap" title="Select multiple">
                    <i class="las la-check-square"></i> Select
                </button>
            </div>
        </div>

        <!-- Bulk Actions Bar (Hidden by default) -->
      <!-- Bulk Actions Bar (Hidden by default) -->
<div id="bulk-actions-bar" class="hidden bg-purple-50 border-b border-purple-200 p-3">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <!-- Select All Checkbox -->
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" id="select-all-checkbox" class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded cursor-pointer">
                <span class="text-sm font-medium text-purple-900">Select All</span>
            </label>
            <div class="text-sm font-medium text-purple-900 border-l border-purple-300 pl-3">
                <span id="bulk-selected-count">0</span> selected
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button id="bulk-archive-btn" class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                <i class="las la-archive"></i> Archive
            </button>
            <button id="bulk-delete-btn" class="px-3 py-1.5 text-xs bg-red-600 text-white rounded hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                <i class="las la-trash"></i> Delete
            </button>
            <button id="cancel-select-btn" class="px-3 py-1.5 text-xs bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition-colors">
                Cancel
            </button>
        </div>
    </div>
</div>

        <!-- Conversations List -->
        <div class="flex-1 overflow-y-auto p-4">
            <!-- No results message (hidden by default) -->
            <div id="no-search-results" class="hidden text-center text-gray-500 py-8">
                <i class="las la-search text-4xl mb-2"></i>
                <p>No conversations found</p>
            </div>
            
            <div id="conversations-list" class="space-y-2">
                <!-- Conversations will be loaded here -->
            </div>
        </div>

        <!-- Sidebar Footer -->
        <div class="p-4 border-t border-gray-200">
            <div class="flex items-center justify-center space-x-3">
                <!-- New Conversation Button -->
                <button 
                    id="new-conversation" 
                    class="flex-1 bg-purple-600 text-white p-3 rounded-lg hover:bg-purple-700 transition-colors relative group"
                    title="New Comparison"
                >
                    <i class="las la-plus text-xl"></i>
                    <span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
                        New Comparison
                    </span>
                </button>
                
                <!-- Dashboard Button -->
                <button 
                    id="goto-dashboard" 
                    class="flex-1 bg-gray-600 text-white p-3 rounded-lg hover:bg-gray-700 transition-colors relative group"
                    title="Go to Dashboard"
                >
                    <i class="las la-home text-xl"></i>
                    <span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
                        Dashboard
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- ✅ NEW: Attachment Preview Modal -->
    <div id="attachment-preview-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-80 hidden">
        <div class="modal-content bg-white rounded-lg shadow-2xl overflow-hidden" style="width: 90vw; max-width: 1200px;">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-4 border-b border-gray-200 bg-gradient-to-r from-purple-600 to-indigo-600 text-white">
                <div class="flex items-center space-x-3">
                    <i class="las la-file-alt text-2xl"></i>
                    <div>
                        <h3 id="preview-modal-title" class="text-lg font-semibold">File Preview</h3>
                        <p id="preview-modal-filename" class="text-sm opacity-90"></p>
                    </div>
                </div>
                <button id="close-preview-modal" class="text-white hover:bg-white/20 p-2 rounded-lg transition-colors">
                    <i class="las la-times text-2xl"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div id="preview-content" class="p-6 bg-gray-50">
                <div class="preview-loading">
                    <div class="spinner"></div>
                    <p class="mt-4">Loading preview...</p>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="flex items-center justify-end space-x-3 p-4 border-t border-gray-200 bg-gray-50">
                <button id="download-attachment-btn" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="las la-download mr-2"></i>Download
                </button>
                <button id="close-preview-modal-btn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen">
        <!-- Header with compact model selection -->
        <!-- Header with model selection and optimization mode -->
        <div class="bg-white/10 backdrop-blur-sm border-b border-white/20 p-4">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center space-x-4">
                    <button id="toggle-sidebar" class="text-white hover:bg-white/10 p-2 rounded-lg transition-colors">
                        <i class="las la-bars text-xl"></i>
                    </button>
                    <h1 class="text-2xl font-bold text-white">AI Model Comparison</h1>
                </div>

                <!-- Model Selection and Optimization Mode -->
                <div class="flex items-center space-x-4 flex-wrap gap-3">
                    <!-- ✅ NEW: 3-Way Optimization Mode Toggle -->
                    <div class="flex items-center space-x-2 bg-white/10 rounded-lg p-1">
                        <button type="button" class="optimization-mode-btn active px-3 py-1.5 rounded-md text-sm font-medium transition-all" data-mode="fixed" title="Keep your selected models as-is">
                            Fixed
                        </button>
                        <button type="button" class="optimization-mode-btn px-3 py-1.5 rounded-md text-sm font-medium transition-all" data-mode="smart_same" title="AI will optimize within the same provider family">
                            Smart (Same)
                        </button>
                        <button type="button" class="optimization-mode-btn px-3 py-1.5 rounded-md text-sm font-medium transition-all" data-mode="smart_all" title="AI will choose the best models across all providers">
                            Smart (All) <span class="ml-1 text-xs bg-purple-500 px-1.5 py-0.5 rounded">Pro</span>
                        </button>
                    </div>

                    <!-- Model Dropdown -->
                    <div class="relative">
                        <button id="model-dropdown-btn" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition-colors flex items-center space-x-2">
                            <i class="las la-robot"></i>
                            <span id="selected-count">0 Models</span>
                            <i class="las la-chevron-down"></i>
                        </button>
                        
                        <!-- ✅ Fixed Mode Dropdown - Show all models with search -->
                        <div id="model-dropdown-fixed" class="dropdown-content">
                            <!-- Search Input -->
                            <div class="dropdown-search-container" style="position: sticky; top: 0; z-index: 10; background: white; padding: 12px; border-bottom: 1px solid #e5e7eb;">
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        id="model-search-input" 
                                        placeholder="Search models..." 
                                        class="w-full pl-9 pr-9 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                        autocomplete="off"
                                    >
                                    <i class="las la-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                    <button 
                                        type="button" 
                                        id="clear-model-search" 
                                        class="hidden absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    >
                                        <i class="las la-times-circle"></i>
                                    </button>
                                </div>
                                <div id="model-search-count" class="text-xs text-gray-500 mt-1 hidden">
                                    <span id="model-match-count">0</span> models found
                                </div>
                            </div>
                            
                            <!-- No Results Message -->
                            <div id="no-model-results" class="hidden text-center py-8 text-gray-500">
                                <i class="las la-search text-3xl mb-2"></i>
                                <p class="text-sm">No models found</p>
                            </div>
                            
                            <!-- Models List -->
                            <div id="models-list-container">
                                @foreach($availableModels as $provider => $models)
                                    <div class="dropdown-provider" data-provider="{{ $provider }}">{{ ucfirst($provider) }}</div>
                                    @foreach($models as $model)
                                        <div class="dropdown-model" 
                                            data-model="{{ $model->openaimodel }}" 
                                            data-provider="{{ $provider }}"
                                            data-display-name="{{ $model->displayname }}"
                                            data-search-text="{{ strtolower($model->displayname . ' ' . $model->openaimodel . ' ' . $provider) }}">
                                            <label class="flex items-center space-x-2 cursor-pointer">
                                                <input type="checkbox" name="models[]" value="{{ $model->openaimodel }}" 
                                                    class="model-checkbox text-purple-600 focus:ring-purple-500"
                                                    data-provider="{{ $provider }}"
                                                    data-display-name="{{ $model->displayname }}"
                                                    data-cost="{{ number_format((float)($model->cost_per_m_tokens), 6, '.', '') }}">
                                                <span>{{ $model->displayname }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                @endforeach
                            </div>
                        </div>          
                        <!-- ✅ Smart Mode Dropdown - Show only providers -->
                        <div id="model-dropdown-smart" class="dropdown-content hidden">
                            @foreach($availableModels as $provider => $models)
                                @php
                                    // Get the first model from this provider for the data-first-model attribute
                                    $firstModel = $models->first();
                                @endphp
                                <div class="dropdown-model" 
                                    data-provider="{{ $provider }}"
                                    data-display-name="{{ ucfirst($provider) }} Smart Mode">
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="checkbox" name="providers[]" value="{{ $provider }}" 
                                            class="provider-checkbox text-purple-600 focus:ring-purple-500"
                                            data-provider="{{ $provider }}"
                                            data-display-name="{{ ucfirst($provider) }} Smart Mode"
                                            data-first-model="{{ $firstModel->openaimodel }}">
                                        <span class="font-semibold">{{ ucfirst($provider) }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- User Stats -->
                    <div class="flex items-center space-x-4 text-white/80 text-sm">
                        <span>Credits: <span id="credits_left">{{ auth()->user()->credits_left ?? '∞' }}</span></span>
                        <span>Tokens: <span id="tokens_left">{{ auth()->user()->tokens_left ?? '∞' }}</span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Interface -->
        <div class="flex-1 p-4 overflow-hidden">
            <div class="max-w-full mx-auto h-full flex flex-col">
                
                <!-- Models Container -->
                <div id="models-container" class="flex-1 grid gap-4 mb-4 overflow-auto">
                    <div class="flex items-center justify-center h-full">
                        <div class="text-center text-white/60">
                            <i class="las la-robot text-6xl mb-4"></i>
                            <p class="text-lg">Select models from the dropdown above to start comparing</p>
                        </div>
                    </div>
                </div>

                <!-- Chat Input -->
                <form id="compare-form" class="bg-white/10 backdrop-blur-sm rounded-lg p-4">
                    <!-- File Upload Preview -->
                    <!-- ✅ File Upload Preview with Icon-Only Button -->
                    <div id="attachment-preview" class="hidden bg-white/20 p-3 rounded-lg mb-3 inline-block max-w-max">
                        <div class="flex items-center space-x-3">
                            <i class="las la-paperclip text-white text-xl"></i>
                            <div id="file-name" class="text-white text-sm"></div>
                            <div class="flex items-center space-x-2 ml-auto">
                                <button type="button" id="preview-attachment-btn" class="text-white/80 hover:text-white bg-white/10 hover:bg-white/20 p-2 rounded transition-colors" title="Preview file">
                                    <i class="las la-eye text-lg"></i>
                                </button>
                                <button type="button" id="remove-file" class="text-red-400 hover:text-red-300 bg-white/10 hover:bg-white/20 p-2 rounded transition-colors" title="Remove file">
                                    <i class="las la-times text-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Main Input Area -->
                    <div class="flex items-end space-x-3">
                        <!-- Left Side: Input with inline controls -->
                        <div class="flex-1 relative">
                            <div class="flex items-end bg-white/20 border border-white/30 rounded-lg focus-within:ring-2 focus-within:ring-purple-500">
                                <!-- Textarea -->
                                <textarea 
                                    id="message-input" 
                                    name="message" 
                                    placeholder="Type your message here..." 
                                    class="flex-1 p-3 bg-transparent text-white placeholder-white/60 focus:outline-none resize-none min-h-[52px] max-h-[200px]"
                                    rows="1"
                                    required></textarea>
                                
                                <!-- Inline Controls -->
                                <div class="flex items-center space-x-2 p-2 pb-3">
                                    <!-- Attachment Button -->
                                    <label for="file-input" class="cursor-pointer text-white/70 hover:text-white transition-colors" title="Attach file">
                                        <i class="las la-paperclip text-xl"></i>
                                    </label>
                                    <input type="file" id="file-input" name="pdf" class="hidden" 
                                        accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.webp,.gif">
                                    
                                    <!-- Options Dropdown Button -->
                                    <div class="relative">
                                        <button type="button" id="options-dropdown-btn" class="text-white/70 hover:text-white transition-colors" title="More options">
                                            <i class="las la-sliders-h text-xl"></i>
                                        </button>
                                        
                                        <!-- Dropdown Menu -->
                                        <div id="options-dropdown" class="hidden absolute bottom-full right-0 mb-2 bg-white rounded-lg shadow-lg py-2 min-w-[200px] z-50">
                                            <label class="flex items-center space-x-3 px-4 py-2 hover:bg-gray-100 cursor-pointer transition-colors">
                                                <input type="checkbox" id="web-search" name="web_search" class="text-purple-600 focus:ring-purple-500 rounded">
                                                <div class="flex items-center space-x-2">
                                                    <i class="las la-search text-gray-700"></i>
                                                    <span class="text-gray-700 text-sm">Web Search</span>
                                                </div>
                                            </label>
                                            
                                            <label class="flex items-center space-x-3 px-4 py-2 hover:bg-gray-100 cursor-pointer transition-colors" id="create-image-label">
                                                <input type="checkbox" id="create-image" name="create_image" class="text-purple-600 focus:ring-purple-500 rounded">
                                                <div class="flex items-center space-x-2">
                                                    <i class="las la-image text-gray-700"></i>
                                                    <span class="text-gray-700 text-sm">Generate Image</span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Side: Action Buttons -->
                        <div class="flex flex-col space-y-2">
                            <button type="submit" id="send-button" 
                                    class="bg-purple-600 hover:bg-purple-700 text-white p-3 rounded-lg font-medium transition-colors flex items-center justify-center min-w-[52px] min-h-[52px]">
                                <i class="las la-paper-plane text-xl"></i>
                            </button>
                            
                            <button type="button" id="stop-button" 
                                    class="hidden bg-red-600 hover:bg-red-700 text-white p-3 rounded-lg font-medium transition-colors flex items-center justify-center min-w-[52px] min-h-[52px]">
                                <i class="las la-stop text-xl"></i>
                            </button>
                        </div>
                    </div>
                     <!-- Helper Text -->
                    <div class="text-white/50 text-xs mt-1 px-1">
                        Press Enter to send, Shift+Enter for new line
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        window.debugMode = true;
        window.chartProcessingLog = [];
        let currentAttachmentFile = null; // ✅ NEW: Store current attachment file
let imageGenModelMapping = {}; // ✅ CRITICAL: Initialize image generation model mapping

        function debugLog(message, data = null) {
            if (window.debugMode) {
                console.log('[CHAT DEBUG]', message, data || '');
                window.chartProcessingLog.push({
                    time: new Date().toISOString(),
                    message: message,
                    data: data
                });
            }
        }

        // Initialize variables
        let conversationHistory = {};
        let abortController = null;
        let selectedModels = [];
        let currentConversationId = null;
        let modelResponseElements = {};

        // ✅ NEW: Mapping between original model IDs and optimized model IDs
        let modelIdMapping = {}; // Maps: optimizedModel -> originalModel

        // Get DOM elements
        const compareForm = document.getElementById('compare-form');
        const messageInput = document.getElementById('message-input');
        const sendButton = document.getElementById('send-button');
        const stopButton = document.getElementById('stop-button');
        const modelsContainer = document.getElementById('models-container');
        const fileInput = document.getElementById('file-input');
        const attachmentPreview = document.getElementById('attachment-preview');
        const fileNameSpan = document.getElementById('file-name');
        const removeFileButton = document.getElementById('remove-file');
        const sidebar = document.getElementById('sidebar');
        const toggleSidebarButton = document.getElementById('toggle-sidebar');
        const closeSidebarButton = document.getElementById('close-sidebar');
        const newConversationButton = document.getElementById('new-conversation');
        const conversationsList = document.getElementById('conversations-list');
        const modelDropdownBtn = document.getElementById('model-dropdown-btn');
        const modelDropdown = document.getElementById('model-dropdown');
        const selectedCountSpan = document.getElementById('selected-count');
        const createImageCheckbox = document.getElementById('create-image');
        const createImageLabel = document.getElementById('create-image-label');
        const optionsDropdownBtn = document.getElementById('options-dropdown-btn');
        const optionsDropdown = document.getElementById('options-dropdown');

        // ✅ NEW: Preview modal elements
        const previewModal = document.getElementById('attachment-preview-modal');
        const closePreviewModalBtn = document.getElementById('close-preview-modal');
        const closePreviewModalFooterBtn = document.getElementById('close-preview-modal-btn');
        const previewContent = document.getElementById('preview-content');
        const previewModalTitle = document.getElementById('preview-modal-title');
        const previewModalFilename = document.getElementById('preview-modal-filename');
        const downloadAttachmentBtn = document.getElementById('download-attachment-btn');
        const previewAttachmentBtn = document.getElementById('preview-attachment-btn');

        function isImageURL(str) {
            if (!str || typeof str !== 'string') return false;
            try {
                const url = new URL(str);
                const pathname = url.pathname.toLowerCase();
                const imageExtensions = ['.png', '.jpg', '.jpeg', '.gif', '.webp', '.bmp'];
                return imageExtensions.some(ext => pathname.endsWith(ext));
            } catch (e) {
                return false;
            }
        }
        // Auto-resize textarea
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 200) + 'px';
        });

        // Options dropdown toggle
        optionsDropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            optionsDropdown.classList.toggle('hidden');
            optionsDropdownBtn.classList.toggle('active');
        });

        // Check Chart.js loading
        debugLog('Chart.js status', typeof Chart !== 'undefined' ? 'LOADED' : 'FAILED');

        // ✅ NEW: Optimization Mode Management
        let currentOptimizationMode = 'fixed'; // default mode

        // Initialize optimization mode from localStorage
        document.addEventListener('DOMContentLoaded', function() {
            // ✅ CHANGED: Always start with Smart(All) mode on page load
            const savedMode = 'smart_all'; // Force Smart(All) mode on every page load
            
            console.log('Page loaded with mode:', savedMode);
            
            // Set the mode (this will also generate panels)
            setOptimizationMode(savedMode, true); // Skip confirmation on page load
            
            // Load conversations list
            loadConversations();
        });

        // Optimization mode button handlers
        document.querySelectorAll('.optimization-mode-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const mode = this.dataset.mode;
                setOptimizationMode(mode);
            });
        });

        // ✅ NEW: Show custom modal for mode switch confirmation
        function showModeSwitchConfirmation(fromMode, toMode) {
            return new Promise((resolve) => {
                const modeNames = {
                    'fixed': 'Fixed Mode',
                    'smart_same': 'Smart (Same Provider)',
                    'smart_all': 'Smart (All Providers)'
                };
                
                const modal = document.createElement('div');
                modal.className = 'mode-switch-modal';
                modal.innerHTML = `
                    <div class="mode-switch-modal-content">
                        <div class="mode-switch-modal-header">
                            <div class="mode-switch-modal-icon">
                                <i class="las la-exchange-alt"></i>
                            </div>
                            <div class="mode-switch-modal-title">Switch Mode?</div>
                        </div>
                        <div class="mode-switch-modal-body">
                            <p>You're about to switch from <strong>${modeNames[fromMode]}</strong> to <strong>${modeNames[toMode]}</strong>.</p>
                            <p style="margin-top: 12px;">This will start a <strong>new conversation</strong>. Your current chat will be saved in history.</p>
                            <div class="mode-switch-modal-info">
                                <i class="las la-info-circle"></i> You can access your previous conversation anytime from the sidebar.
                            </div>
                        </div>
                        <div class="mode-switch-modal-actions">
                            <button class="mode-switch-modal-btn mode-switch-modal-btn-cancel">
                                Cancel
                            </button>
                            <button class="mode-switch-modal-btn mode-switch-modal-btn-confirm">
                                <i class="las la-check"></i> Start New Conversation
                            </button>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
                
                // Fade in animation
                setTimeout(() => {
                    modal.style.opacity = '1';
                }, 10);
                
                // Handle cancel
                modal.querySelector('.mode-switch-modal-btn-cancel').addEventListener('click', () => {
                    modal.style.opacity = '0';
                    setTimeout(() => {
                        modal.remove();
                        resolve(false);
                    }, 200);
                });
                
                // Handle confirm
                modal.querySelector('.mode-switch-modal-btn-confirm').addEventListener('click', () => {
                    modal.style.opacity = '0';
                    setTimeout(() => {
                        modal.remove();
                        resolve(true);
                    }, 200);
                });
                
                // Handle backdrop click
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.style.opacity = '0';
                        setTimeout(() => {
                            modal.remove();
                            resolve(false);
                        }, 200);
                    }
                });
            });
        }

        // ✅ ENHANCED: setOptimizationMode function with skipAutoSelection parameter
        async function setOptimizationMode(mode, skipConfirmation = false, skipAutoSelection = false) {
            const previousMode = currentOptimizationMode;
            const hasExistingConversation = currentConversationId !== null || 
                                            Object.values(conversationHistory).some(history => history.length > 0);
            
            // Confirmation logic (unchanged)
            if (!skipConfirmation && previousMode !== mode && hasExistingConversation) {
                const confirmed = await showModeSwitchConfirmation(previousMode, mode);
                if (!confirmed) {
                    document.querySelectorAll('.optimization-mode-btn').forEach(btn => {
                        if (btn.dataset.mode === previousMode) {
                            btn.classList.add('active');
                        } else {
                            btn.classList.remove('active');
                        }
                    });
                    return;
                }
            }
            
            currentOptimizationMode = mode;
            localStorage.setItem('multi_compare_optimization_mode', mode);
            
            // Update button states
            document.querySelectorAll('.optimization-mode-btn').forEach(btn => {
                if (btn.dataset.mode === mode) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            
            // Clear conversation only when manually switching
            if (!skipConfirmation && hasExistingConversation) {
                currentConversationId = null;
                conversationHistory = {};
                selectedModels.forEach(model => {
                    conversationHistory[model.model] = [];
                });
            }
            
            const modelDropdownBtn = document.getElementById('model-dropdown-btn');
            const fixedDropdown = document.getElementById('model-dropdown-fixed');
            const smartDropdown = document.getElementById('model-dropdown-smart');
            
            if (mode === 'fixed') {
                modelDropdownBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                modelDropdownBtn.disabled = false;
                fixedDropdown.classList.remove('hidden');
                smartDropdown.classList.add('hidden');
                
                // ✅ FIXED: Only auto-select if NOT loading from history
                if (!skipAutoSelection) {
                    document.querySelectorAll('.model-checkbox').forEach(cb => cb.checked = false);
                    document.querySelectorAll('.provider-checkbox').forEach(cb => cb.checked = false);
                    
                    const cheapestModel = findCheapestModel();
                    if (cheapestModel) {
                        const checkbox = document.querySelector(`input.model-checkbox[value="${cheapestModel}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                            console.log('✅ Auto-selected cheapest model:', cheapestModel);
                        }
                    }
                }
                
                updateSelectedModels();
                
            } else if (mode === 'smart_same') {
                modelDropdownBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                modelDropdownBtn.disabled = false;
                fixedDropdown.classList.add('hidden');
                smartDropdown.classList.remove('hidden');
                
                // ✅ FIXED: Only auto-select if NOT loading from history
                if (!skipAutoSelection) {
                    document.querySelectorAll('.model-checkbox').forEach(cb => cb.checked = false);
                    document.querySelectorAll('.provider-checkbox').forEach(cb => {
                        cb.checked = true;
                    });
                    console.log('✅ Auto-selected all providers for Smart (Same) mode');
                }
                
                updateSelectedModels();
                
            } else if (mode === 'smart_all') {
                modelDropdownBtn.classList.add('opacity-50', 'cursor-not-allowed');
                modelDropdownBtn.disabled = true;
                fixedDropdown.classList.add('hidden');
                smartDropdown.classList.add('hidden');
                document.querySelectorAll('.model-checkbox').forEach(cb => cb.checked = false);
                document.querySelectorAll('.provider-checkbox').forEach(cb => cb.checked = false);
                
                const isLoadingConversation = skipConfirmation && selectedModels.length > 0 && selectedModels[0].model === 'smart_all_auto';
                
                if (!isLoadingConversation) {
                    selectedModels = [{
                        model: 'smart_all_auto',
                        provider: 'smart_all',
                        displayName: 'Smart Mode'
                    }];
                    conversationHistory['smart_all_auto'] = [];
                    
                    generateModelPanels();
                }
            }
            
            // Show notification only when manually switching
            if (!skipConfirmation) {
                const modeNames = {
                    'fixed': 'Fixed Mode',
                    'smart_same': 'Smart (Same Provider)',
                    'smart_all': 'Smart (All Providers)'
                };
                
                showNotification(`✓ Switched to ${modeNames[mode]} - New conversation started`, 'success');
            }
            
            console.log('Optimization mode set to:', mode, skipConfirmation ? '(loading/initial)' : '(manual switch)');
        }

        // ✅ FIXED: Helper function to find the cheapest model with proper numeric handling
        function findCheapestModel() {
            let cheapestModel = null;
            let lowestCost = Infinity;
            
            // Collect all models with their costs for debugging
            const modelCosts = [];
            
            console.log('🔍 Starting search for cheapest model...');
            console.log('Total checkboxes found:', document.querySelectorAll('input.model-checkbox').length);
            
            // Check all available model checkboxes and their cost data
            document.querySelectorAll('input.model-checkbox').forEach((checkbox, index) => {
                const modelId = checkbox.value;
                const costStr = checkbox.getAttribute('data-cost'); // Use getAttribute to be explicit
                const displayName = checkbox.getAttribute('data-display-name');
                
                // Parse cost with extra validation
                let cost = parseFloat(costStr);
                
                // Handle potential parsing issues
                if (costStr === null || costStr === undefined || costStr === '') {
                    console.warn(`  ⚠️ [${index}] ${displayName}: No cost attribute found`);
                    cost = 999999;
                }
                
                console.log(`  [${index}] ${displayName} (${modelId})`);
                console.log(`      Raw cost string: "${costStr}"`);
                console.log(`      Parsed cost: ${cost}`);
                console.log(`      Current lowest: ${lowestCost}`);
                
                // Log each model's cost
                modelCosts.push({
                    index: index,
                    modelId: modelId,
                    displayName: displayName,
                    costStr: costStr,
                    cost: cost,
                    isValid: !isNaN(cost) && cost !== null && cost !== undefined
                });
                
                // Only consider valid costs
                if (!isNaN(cost) && cost !== null && cost !== undefined && cost !== Infinity) {
                    if (cost < lowestCost) {
                        console.log(`      ✅ NEW CHEAPEST! ${cost} < ${lowestCost}`);
                        lowestCost = cost;
                        cheapestModel = modelId;
                    } else {
                        console.log(`      ❌ Not cheaper: ${cost} >= ${lowestCost}`);
                    }
                } else {
                    console.log(`      ⚠️ INVALID cost, skipping`);
                }
                console.log('---');
            });
            
            // Sort by cost for easy viewing
            modelCosts.sort((a, b) => {
                const costA = isNaN(a.cost) ? 999999 : a.cost;
                const costB = isNaN(b.cost) ? 999999 : b.cost;
                return costA - costB;
            });
            
            console.log('📊 All models sorted by cost (cheapest first):');
            console.table(modelCosts.map(m => ({
                'Model': m.displayName,
                'ID': m.modelId,
                'Cost': m.cost,
                'Valid': m.isValid
            })));
            
            console.log('✅ FINAL SELECTION:');
            console.log(`   Model: ${cheapestModel}`);
            console.log(`   Cost: ${lowestCost}`);
            
            // Fallback: if no cost data found, just pick the first model
            if (!cheapestModel || lowestCost === Infinity) {
                console.warn('⚠️ No valid cheapest model found, using fallback...');
                const firstCheckbox = document.querySelector('input.model-checkbox');
                if (firstCheckbox) {
                    cheapestModel = firstCheckbox.value;
                    console.warn(`   Using first model: ${cheapestModel}`);
                }
            }
            
            return cheapestModel;
        }
        // ✅ NEW: Transfer model selections to provider selections
        function transferSelectionsToProviders() {
            const selectedProviders = new Set();
            
            // Find which providers have selected models
            document.querySelectorAll('.model-checkbox:checked').forEach(checkbox => {
                selectedProviders.add(checkbox.dataset.provider);
            });
            
            // Check the corresponding provider checkboxes
            document.querySelectorAll('.provider-checkbox').forEach(checkbox => {
                checkbox.checked = selectedProviders.has(checkbox.dataset.provider);
            });
        }

        // Model dropdown functionality
        modelDropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            
            // ✅ Don't open if disabled (Smart All mode)
            if (modelDropdownBtn.disabled) {
                return;
            }
            
            // Toggle the appropriate dropdown based on mode
            if (currentOptimizationMode === 'fixed') {
                document.getElementById('model-dropdown-fixed').classList.toggle('show');
            } else if (currentOptimizationMode === 'smart_same') {
                document.getElementById('model-dropdown-smart').classList.toggle('show');
            }
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!optionsDropdownBtn.contains(e.target) && !optionsDropdown.contains(e.target)) {
                optionsDropdown.classList.add('hidden');
                optionsDropdownBtn.classList.remove('active');
            }
            
            if (!modelDropdownBtn.contains(e.target) && 
                !document.getElementById('model-dropdown-fixed').contains(e.target) &&
                !document.getElementById('model-dropdown-smart').contains(e.target)) {
                document.getElementById('model-dropdown-fixed').classList.remove('show');
                document.getElementById('model-dropdown-smart').classList.remove('show');
            }
        });

        async function toggleArchiveConversation(conversationId, currentlyArchived) {
            const action = currentlyArchived ? 'unarchive' : 'archive';
            
            try {
                const response = await fetch(`{{ url('/toggle-archive-multi-compare-conversation') }}/${conversationId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    }
                });
                
                if (!response.ok) {
                    throw new Error('Failed to ' + action + ' conversation');
                }
                
                const data = await response.json();
                showNotification(data.message, 'success');
                loadConversations();
                
            } catch (error) {
                console.error('Archive error:', error);
                showNotification('Failed to ' + action + ' conversation', 'error');
            }
        }

        // Sidebar functionality
        toggleSidebarButton.addEventListener('click', () => {
            sidebar.classList.toggle('sidebar-hidden');
            sidebar.classList.toggle('sidebar-visible');
        });

        closeSidebarButton.addEventListener('click', () => {
            sidebar.classList.add('sidebar-hidden');
            sidebar.classList.remove('sidebar-visible');
        });

        newConversationButton.addEventListener('click', () => {
            currentConversationId = null;
            selectedModels.forEach(model => {
                conversationHistory[model.model] = [];
            });
            regenerateModelPanels();
            
            sidebar.classList.add('sidebar-hidden');
            sidebar.classList.remove('sidebar-visible');
        });

        // Model and Provider selection handling
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('model-checkbox') || e.target.classList.contains('provider-checkbox')) {
                updateSelectedModels();
            }
        });

        /**
         * Get provider for a model from DOM data attributes
         * @param {string} modelId - The model identifier
         * @returns {string} Provider name or null
         */
        function getModelProviderFromDOM(modelId) {
            // Try model checkbox first (Fixed mode)
            const modelCheckbox = document.querySelector(`input.model-checkbox[value="${modelId}"]`);
            if (modelCheckbox && modelCheckbox.dataset.provider) {
                return modelCheckbox.dataset.provider;
            }
            
            // Try provider checkbox (Smart modes)
            const providerCheckbox = document.querySelector(`input.provider-checkbox[value="${modelId}"]`);
            if (providerCheckbox && providerCheckbox.dataset.provider) {
                return providerCheckbox.dataset.provider;
            }
            
            // Fallback: Try to extract from panel ID (for smart_same mode)
            if (modelId.includes('_smart_panel')) {
                return modelId.replace('_smart_panel', '');
            }
            
            return null;
        }

        function updateSelectedModels() {
            if (currentOptimizationMode === 'fixed') {
                // ✅ Fixed mode: use model checkboxes
                const checkboxes = document.querySelectorAll('.model-checkbox:checked');
                selectedModels = Array.from(checkboxes).map(cb => ({
                    model: cb.value,
                    provider: cb.dataset.provider,
                    displayName: cb.dataset.displayName
                }));
            } else {
                // ✅ Smart modes - use STABLE panel IDs with provider name
                const providerCheckboxes = document.querySelectorAll('.provider-checkbox:checked');
                selectedModels = Array.from(providerCheckboxes).map(cb => ({
                    model: `${cb.dataset.provider}_smart_panel`, // ✅ CHANGED: Use stable panel ID
                    provider: cb.dataset.provider,
                    displayName: `${cb.dataset.provider.charAt(0).toUpperCase() + cb.dataset.provider.slice(1)} (Smart)` // ✅ CHANGED: Show provider name
                }));
            }

            // Update count display
            const countText = selectedModels.length === 0 ? '0 Models' : 
                selectedModels.length === 1 ? '1 Model' : `${selectedModels.length} Models`;
            
            selectedCountSpan.textContent = currentOptimizationMode === 'fixed' 
                ? countText 
                : (selectedModels.length === 0 ? '0 Providers' : 
                selectedModels.length === 1 ? '1 Provider' : `${selectedModels.length} Providers`);

            // Update dropdown selection highlights
            if (currentOptimizationMode === 'fixed') {
                document.querySelectorAll('.dropdown-model').forEach(item => {
                    if (item.dataset.model) {
                        const isSelected = selectedModels.some(m => m.model === item.dataset.model);
                        item.classList.toggle('selected', isSelected);
                    }
                });
            } else {
                document.querySelectorAll('#model-dropdown-smart .dropdown-model').forEach(item => {
                    const isSelected = selectedModels.some(m => m.provider === item.dataset.provider);
                    item.classList.toggle('selected', isSelected);
                });
            }

            // ====== MODEL SEARCH FUNCTIONALITY (Fixed Mode Only) ======
            const modelSearchInput = document.getElementById('model-search-input');
            const clearModelSearchBtn = document.getElementById('clear-model-search');
            const noModelResults = document.getElementById('no-model-results');
            const modelSearchCount = document.getElementById('model-search-count');
            const modelMatchCount = document.getElementById('model-match-count');
            const modelsListContainer = document.getElementById('models-list-container');

            // Search models in Fixed mode dropdown
            if (modelSearchInput) {
                modelSearchInput.addEventListener('input', function(e) {
                    e.stopPropagation(); // Prevent dropdown from closing
                    const searchTerm = this.value.toLowerCase().trim();
                    
                    // Show/hide clear button
                    if (searchTerm) {
                        clearModelSearchBtn.classList.remove('hidden');
                    } else {
                        clearModelSearchBtn.classList.add('hidden');
                    }
                    
                    filterModels(searchTerm);
                });
                
                // Prevent dropdown from closing when clicking in search input
                modelSearchInput.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
                
                // Clear search
                clearModelSearchBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    modelSearchInput.value = '';
                    clearModelSearchBtn.classList.add('hidden');
                    filterModels('');
                    modelSearchInput.focus();
                });
            }

            // Filter models based on search term
            function filterModels(searchTerm) {
                const allModels = document.querySelectorAll('#model-dropdown-fixed .dropdown-model');
                const allProviders = document.querySelectorAll('#model-dropdown-fixed .dropdown-provider');
                let visibleCount = 0;
                let currentProvider = null;
                let providerHasVisibleModels = false;
                
                if (!searchTerm) {
                    // Show all models and providers
                    allModels.forEach(model => {
                        model.style.display = '';
                    });
                    allProviders.forEach(provider => {
                        provider.style.display = '';
                    });
                    noModelResults.classList.add('hidden');
                    modelsListContainer.classList.remove('hidden');
                    modelSearchCount.classList.add('hidden');
                    return;
                }
                
                // Hide all providers initially
                allProviders.forEach(provider => {
                    provider.style.display = 'none';
                });
                
                // Filter models
                allModels.forEach(model => {
                    const searchText = model.dataset.searchText || '';
                    const provider = model.dataset.provider;
                    
                    if (searchText.includes(searchTerm)) {
                        model.style.display = '';
                        visibleCount++;
                        
                        // Show the provider header for this model
                        const providerHeader = document.querySelector(`#model-dropdown-fixed .dropdown-provider[data-provider="${provider}"]`);
                        if (providerHeader) {
                            providerHeader.style.display = '';
                        }
                    } else {
                        model.style.display = 'none';
                    }
                });
                
                // Update UI based on results
                if (visibleCount === 0) {
                    noModelResults.classList.remove('hidden');
                    modelsListContainer.classList.add('hidden');
                    modelSearchCount.classList.add('hidden');
                } else {
                    noModelResults.classList.add('hidden');
                    modelsListContainer.classList.remove('hidden');
                    modelSearchCount.classList.remove('hidden');
                    modelMatchCount.textContent = visibleCount;
                }
            }

            // Reset search when dropdown closes
            const originalModelDropdownToggle = modelDropdownBtn.onclick;
            modelDropdownBtn.addEventListener('click', (e) => {
                const dropdown = document.getElementById('model-dropdown-fixed');
                
                // If dropdown is closing (will be hidden after toggle)
                if (dropdown.classList.contains('show')) {
                    // Reset search when closing
                    if (modelSearchInput) {
                        modelSearchInput.value = '';
                        clearModelSearchBtn.classList.add('hidden');
                        filterModels('');
                    }
                } else {
                    // Focus search input when opening
                    setTimeout(() => {
                        if (currentOptimizationMode === 'fixed' && modelSearchInput) {
                            modelSearchInput.focus();
                        }
                    }, 100);
                }
            });

            // Also reset search when clicking outside dropdown
            document.addEventListener('click', (e) => {
                const fixedDropdown = document.getElementById('model-dropdown-fixed');
                
                if (!modelDropdownBtn.contains(e.target) && 
                    !fixedDropdown.contains(e.target) &&
                    fixedDropdown.classList.contains('show')) {
                    
                    // Reset search
                    if (modelSearchInput) {
                        modelSearchInput.value = '';
                        clearModelSearchBtn.classList.add('hidden');
                        filterModels('');
                    }
                }
            });

            // Check image generation support - all providers except Claude
            const supportsImageGen = selectedModels.some(m => {
                const provider = getModelProviderFromDOM(m.model) || m.provider;
                return provider !== 'claude';
            });
            
            if (createImageLabel) {
                if (supportsImageGen) {
                    createImageLabel.classList.remove('opacity-50', 'cursor-not-allowed');
                    createImageLabel.title = 'Generate images (supported models will create images)';
                    createImageCheckbox.disabled = false;
                } else {
                    createImageLabel.classList.add('opacity-50', 'cursor-not-allowed');
                    createImageLabel.title = 'No selected models support image generation';
                    createImageCheckbox.disabled = true;
                    createImageCheckbox.checked = false;
                }
            }

            generateModelPanels();
        }

        function modelSupportsImageGen(model) {
            // Get provider from the model's data attribute
            const checkbox = document.querySelector(`input.model-checkbox[value="${model}"]`);
            
            if (checkbox && checkbox.dataset.provider) {
                const provider = checkbox.dataset.provider;
                // All providers support image generation except Claude
                return provider !== 'claude';
            }
            
            // Fallback: Check provider checkbox (for smart modes)
            const providerCheckbox = document.querySelector(`input.provider-checkbox[value="${model}"]`);
            if (providerCheckbox && providerCheckbox.dataset.provider) {
                const provider = providerCheckbox.dataset.provider;
                return provider !== 'claude';
            }
            
            // Last resort fallback: hardcoded check (backwards compatibility)
            const modelLower = model.toLowerCase();
            return !modelLower.includes('claude');
        }

        function generateModelPanels() {
            if (selectedModels.length === 0) {
                modelsContainer.innerHTML = `
                    <div class="flex items-center justify-center h-full">
                        <div class="text-center text-white/60">
                            <i class="las la-robot text-6xl mb-4"></i>
                            <p class="text-lg">Select models from the dropdown above to start comparing</p>
                        </div>
                    </div>
                `;
                modelsContainer.className = 'flex-1 grid gap-4 mb-4 overflow-auto';
                return;
            }

            let gridClass = 'models-grid-1';
            if (selectedModels.length === 2) gridClass = 'models-grid-2';
            else if (selectedModels.length === 3) gridClass = 'models-grid-3';
            else if (selectedModels.length >= 4) gridClass = 'models-grid-4';
            
            modelsContainer.className = `flex-1 grid gap-4 mb-4 overflow-auto ${gridClass}`;

            modelsContainer.innerHTML = selectedModels.map(model => `
                <div class="bg-white rounded-lg shadow-lg model-panel" data-model-id="${model.model}">
                    <div class="model-panel-header">
                        <span>${model.displayName}</span>
                        <div class="flex items-center space-x-2">
                            <span id="status-${model.model}" class="model-status">Ready</span>
                            <button class="maximize-model-btn text-white/80 hover:text-white transition-colors p-1" 
                                    data-model="${model.model}"
                                    title="Maximize">
                                <i class="las la-expand text-lg"></i>
                            </button>
                            ${currentOptimizationMode !== 'smart_all' ? `
                                <button class="close-model-btn text-white/80 hover:text-red-300 transition-colors p-1" 
                                        data-model="${model.model}"
                                        title="Close this model">
                                    <i class="las la-times text-lg"></i>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                    <div class="model-response">
                        <div id="conversation-${model.model}" class="model-conversation">
                            ${getConversationHTML(model.model)}
                        </div>
                    </div>
                    <div class="border-t border-gray-200 p-3">
                        <div class="flex space-x-2">
                            <button class="copy-response-btn text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded" 
                                    data-provider="${model.provider}" data-model="${model.model}">
                                📋 Copy
                            </button>
                            <button class="read-aloud-btn text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded" 
                                    data-provider="${model.provider}" data-model="${model.model}">
                                🔊 Read
                            </button>
                            <button class="clear-btn text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded" 
                                    data-provider="${model.provider}" data-model="${model.model}">
                                🗑 Clear
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');

            // ✅ CRITICAL: Initialize modelResponseElements for ALL models
            selectedModels.forEach(model => {
                if (!conversationHistory[model.model]) {
                    conversationHistory[model.model] = [];
                }
                // Initialize as null, will be set when message is added
                modelResponseElements[model.model] = null;
            });
            
            console.log('✅ Model panels generated', {
                models: selectedModels.map(m => m.model),
                responseElements: Object.keys(modelResponseElements)
            });
            
            // Process any content that needs formatting
            setTimeout(() => {
                processUnprocessedContent();
            }, 100);
        }

        function processUnprocessedContent() {
            // Find all message content that needs processing
            document.querySelectorAll('[data-needs-processing="true"]').forEach(element => {
                element.removeAttribute('data-needs-processing');
                
                // Check if it's an image URL
                const content = element.textContent;
                if (isImageURL(content)) {
                    element.innerHTML = '';
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
                    element.appendChild(imgContainer);
                } else {
                    // Process markdown and formatting
                    processMessageContent(element);
                }
            });
        }

        function regenerateModelPanels() {
            selectedModels.forEach(model => {
                const conversationDiv = document.getElementById(`conversation-${model.model}`);
                if (conversationDiv) {
                    conversationDiv.innerHTML = getConversationHTML(model.model);
                }
            });
        }

        function getConversationHTML(modelId) {
            const history = conversationHistory[modelId] || [];
            if (history.length === 0) {
                return getEmptyStateHTML();
            }

            return history.map((entry, index) => {
                const responseId = `processed-response-${modelId}-${index}`;
                const userMsgId = `user-msg-${modelId}-${index}`;
                const assistantMsgId = `assistant-msg-${modelId}-${index}`;
                
                return `
                    <div class="conversation-entry">
                        <div class="user-prompt" data-message-id="${userMsgId}">
                            ${formatUserMessage(entry.prompt)}
                        </div>
                        <div class="message-actions">
                            <button class="message-action-btn copy-msg-btn" data-message-id="${userMsgId}" title="Copy Prompt">
                                <i class="las la-copy"></i>
                            </button>
                            <button class="message-action-btn read-msg-btn" data-message-id="${userMsgId}" title="Read Aloud">
                                <i class="las la-volume-up"></i>
                            </button>
                            <button class="message-action-btn translate-msg-btn" data-message-id="${userMsgId}" title="Translate Prompt">
                                <i class="las la-language"></i>
                            </button>
                        </div>
                        <div class="assistant-response" data-message-id="${assistantMsgId}" data-model="${modelId}">
                            <div class="message-content" id="${responseId}">${
                                isImageURL(entry.response) 
                                    ? `<div class="my-4"><img src="${escapeHtml(entry.response)}" alt="Generated image" class="rounded-lg max-w-full h-auto shadow-md cursor-pointer" onclick="openImageModal('${escapeHtml(entry.response)}', 'Generated image')"></div>`
                                    : `<span data-needs-processing="true">${escapeHtml(entry.response)}</span>`
                            }</div>
                            <div class="message-actions">
                                <button class="message-action-btn copy-msg-btn" data-message-id="${assistantMsgId}" title="Copy Response">
                                    <i class="las la-copy"></i> 
                                </button>
                                <button class="message-action-btn read-msg-btn" data-message-id="${assistantMsgId}" title="Read Aloud">
                                    <i class="las la-volume-up"></i> 
                                </button>
                                <button class="message-action-btn regenerate-msg-btn" data-message-id="${assistantMsgId}" data-model="${modelId}" title="Regenerate Response">
                                    <i class="las la-redo-alt"></i>
                                </button>
                                <button class="message-action-btn translate-msg-btn" data-message-id="${assistantMsgId}" title="Translate Response">
                                    <i class="las la-language"></i> 
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // ✅ NEW: Message action handlers
        document.addEventListener('click', async (e) => {
            // Copy message
            if (e.target.closest('.copy-msg-btn')) {
                const btn = e.target.closest('.copy-msg-btn');
                const messageId = btn.dataset.messageId;
                const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
                
                if (messageElement) {
                    const content = messageElement.querySelector('.message-content')?.textContent || messageElement.textContent;
                    try {
                        await navigator.clipboard.writeText(content.trim());
                        const originalHTML = btn.innerHTML;
                        btn.innerHTML = '<i class="las la-check"></i> Copied!';
                        btn.classList.add('active');
                        setTimeout(() => {
                            btn.innerHTML = originalHTML;
                            btn.classList.remove('active');
                        }, 2000);
                    } catch (err) {
                        console.error('Failed to copy:', err);
                    }
                }
                return;
            }
            
            // Read aloud message
            if (e.target.closest('.read-msg-btn')) {
                const btn = e.target.closest('.read-msg-btn');
                const messageId = btn.dataset.messageId;
                const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
                
                if (messageElement) {
                    const content = messageElement.querySelector('.message-content')?.textContent || messageElement.textContent;
                    
                    if (window.speechSynthesis.speaking) {
                        window.speechSynthesis.cancel();
                        btn.innerHTML = '<i class="las la-volume-up"></i> Read';
                        btn.classList.remove('active');
                        return;
                    }
                    
                    const speech = new SpeechSynthesisUtterance(content.trim());
                    speech.rate = 1;
                    speech.pitch = 1;
                    speech.volume = 1;
                    
                    btn.innerHTML = '<i class="las la-stop"></i> Stop';
                    btn.classList.add('active');
                    
                    speech.onend = () => {
                        btn.innerHTML = '<i class="las la-volume-up"></i> Read';
                        btn.classList.remove('active');
                    };
                    
                    window.speechSynthesis.speak(speech);
                }
                return;
            }
            
            // Regenerate message
            if (e.target.closest('.regenerate-msg-btn')) {
                const btn = e.target.closest('.regenerate-msg-btn');
                const messageId = btn.dataset.messageId;
                const modelId = btn.dataset.model;
                
                await regenerateMessage(modelId, messageId, btn);
                return;
            }
            
            // Translate message
            if (e.target.closest('.translate-msg-btn')) {
                const btn = e.target.closest('.translate-msg-btn');
                const messageId = btn.dataset.messageId;
                
                showTranslateDropdown(btn, messageId);
                return;
            }
        });

        // ✅ FIXED: Regenerate function that handles both real-time and loaded messages
        async function regenerateMessage(modelId, messageId, btn) {
            const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
            if (!messageElement) {
                console.error('Message element not found:', messageId);
                alert('Unable to find message to regenerate');
                return;
            }
            
            console.log('🔍 Found message element:', messageElement);
            
            const conversationEntry = messageElement.closest('.conversation-entry');
            if (!conversationEntry) {
                console.error('Conversation entry not found');
                alert('Unable to find conversation entry');
                return;
            }
            
            console.log('🔍 Found conversation entry:', conversationEntry);
            
            let userPromptDiv = conversationEntry.querySelector('.user-prompt');
            
            if (!userPromptDiv) {
                console.log('⚠️ User prompt not in same entry, looking at previous entry...');
                const previousEntry = conversationEntry.previousElementSibling;
                if (previousEntry && previousEntry.classList.contains('conversation-entry')) {
                    userPromptDiv = previousEntry.querySelector('.user-prompt');
                    console.log('🔍 Found user prompt in previous entry:', userPromptDiv);
                }
            } else {
                console.log('✅ Found user prompt in same entry:', userPromptDiv);
            }
            
            if (!userPromptDiv) {
                console.error('User prompt not found');
                alert('Unable to find the original user message');
                return;
            }
            
            const clone = userPromptDiv.cloneNode(true);
            clone.querySelectorAll('.attachment-badge').forEach(el => el.remove());
            clone.querySelectorAll('.message-actions').forEach(el => el.remove());
            
            let userPrompt = clone.textContent.trim();
            
            if (!userPrompt) {
                console.warn('Could not extract text from DOM, trying conversation history...');
                const conversationDiv = document.getElementById(`conversation-${modelId}`);
                if (conversationDiv) {
                    const allEntries = Array.from(conversationDiv.querySelectorAll('.conversation-entry'));
                    const currentIndex = allEntries.indexOf(conversationEntry);
                    
                    if (currentIndex > 0 && conversationHistory[modelId] && conversationHistory[modelId][currentIndex]) {
                        userPrompt = conversationHistory[modelId][currentIndex].prompt;
                        console.log('✅ Retrieved from history:', userPrompt);
                    } else if (currentIndex === 0 && conversationHistory[modelId] && conversationHistory[modelId][0]) {
                        userPrompt = conversationHistory[modelId][0].prompt;
                        console.log('✅ Retrieved from history (first entry):', userPrompt);
                    }
                }
            }
            
            if (!userPrompt) {
                console.error('Could not extract user prompt text');
                alert('Unable to find the original user message text');
                return;
            }
            
            console.log('✅ Regenerating with prompt:', userPrompt);
            
            const assistantResponse = conversationEntry.querySelector('.assistant-response') || messageElement.closest('.assistant-response');
            if (!assistantResponse) {
                console.error('Assistant response not found');
                alert('Unable to find assistant response');
                return;
            }
            
            const messageContent = assistantResponse.querySelector('.message-content');
            if (!messageContent) {
                console.error('Message content not found');
                alert('Unable to find message content');
                return;
            }
            
            messageContent.innerHTML = `
                <div class="regenerating-indicator">
                    <div class="spinner-sm"></div>
                    <span>Regenerating response...</span>
                </div>
            `;
            
            btn.disabled = true;
            btn.style.opacity = '0.5';
            
            try {
                const formData = new FormData();
                formData.append('message', userPrompt);
                
                // ✅ Handle model selection based on optimization mode
                if (currentOptimizationMode === 'smart_all') {
                    // For Smart (All), send a special indicator to backend
                    formData.append('models', JSON.stringify(['smart_all_auto']));
                } else if (currentOptimizationMode === 'smart_same') {
                    // ✅ FIXED: For Smart (Same), extract providers and send their first models
                    const providerModels = selectedModels.map(m => {
                        const provider = m.provider;
                        const providerCheckbox = document.querySelector(`input.provider-checkbox[value="${provider}"]`);
                        return providerCheckbox ? providerCheckbox.dataset.firstModel : m.model;
                    });
                    formData.append('models', JSON.stringify(providerModels));
                } else {
                    // For Fixed, send selected models
                    formData.append('models', JSON.stringify(selectedModels.map(m => m.model)));
                }
                
                formData.append('web_search', '0');
                formData.append('create_image', '0');
                
                // ✅ FIX: Always pass optimization_mode
                formData.append('optimization_mode', currentOptimizationMode);
                
                if (currentConversationId) {
                    formData.append('conversation_id', currentConversationId);
                }
                
                const response = await fetch('{{ route("chat.multi-compare") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                let fullResponse = '';
                let actualModelIdForResponse = modelId; // Track the actual model ID to update
                
                messageContent.innerHTML = '';
                
                function readStream() {
                    return reader.read().then(({ done, value }) => {
                        if (done) {
                            const conversationDiv = document.getElementById(`conversation-${modelId}`);
                            if (conversationDiv) {
                                const allEntries = Array.from(conversationDiv.querySelectorAll('.conversation-entry'));
                                const entryIndex = allEntries.indexOf(conversationEntry);
                                
                                if (entryIndex !== -1 && conversationHistory[modelId] && conversationHistory[modelId][entryIndex]) {
                                    conversationHistory[modelId][entryIndex].response = fullResponse;
                                    console.log('✅ Updated conversation history at index:', entryIndex);
                                }
                            }
                            
                            processMessageContent(messageContent);
                            btn.disabled = false;
                            btn.style.opacity = '1';
                            
                            console.log('✅ Regeneration complete');
                            return;
                        }
                        
                        buffer += decoder.decode(value, { stream: true });
                        const lines = buffer.split('\n');
                        buffer = lines.pop();
                        
                        lines.forEach(line => {
                            if (line.trim() && line.startsWith('data: ')) {
                                const data = line.slice(6);
                                if (data === '[DONE]') {
                                    return;
                                }
                                
                                try {
                                    const parsed = JSON.parse(data);
                                    
                                    // ✅ FIX: Handle init message to set up model mapping
                                    if (parsed.type === 'init' && parsed.optimized_models) {
                                        console.log('Regenerate: Models optimized:', parsed.optimized_models);
                                        
                                        // Find which optimized model maps to our panel
                                        Object.keys(parsed.optimized_models).forEach(original => {
                                            const optimized = parsed.optimized_models[original];
                                            
                                            if (currentOptimizationMode === 'smart_all') {
                                                modelIdMapping[optimized] = 'smart_all_auto';
                                            } else if (currentOptimizationMode === 'smart_same') {
                                                // ✅ FIX: For smart_same, find the provider of the optimized model
                                                // Look through all model checkboxes (even unchecked ones) to find the provider
                                                let providerFound = null;
                                                document.querySelectorAll('input.model-checkbox').forEach(cb => {
                                                    if (cb.value === optimized) {
                                                        providerFound = cb.dataset.provider;
                                                    }
                                                });
                                                
                                                if (providerFound) {
                                                    modelIdMapping[optimized] = `${providerFound}_smart_panel`;
                                                    console.log(`Mapped ${optimized} to ${providerFound}_smart_panel`);
                                                } else {
                                                    // Fallback: extract provider from the current modelId
                                                    const provider = modelId.replace('_smart_panel', '');
                                                    modelIdMapping[optimized] = `${provider}_smart_panel`;
                                                    console.log(`Fallback mapped ${optimized} to ${provider}_smart_panel`);
                                                }
                                            } else {
                                                modelIdMapping[optimized] = original;
                                            }
                                        });
                                        
                                        console.log('Regenerate: Model ID mapping:', modelIdMapping);
                                    }
                                    
                                    // ✅ FIX: Use modelIdMapping to find the correct panel
                                    const mappedModelId = modelIdMapping[parsed.model] || parsed.model;
                                    
                                    if (parsed.type === 'chunk' && mappedModelId === modelId) {
                                        fullResponse = parsed.full_response || fullResponse;
                                        messageContent.textContent = fullResponse;
                                    } else if (parsed.type === 'complete' && mappedModelId === modelId) {
                                        fullResponse = parsed.final_response || parsed.full_response;
                                        messageContent.textContent = fullResponse;
                                    } else if (parsed.type === 'error' && mappedModelId === modelId) {
                                        messageContent.innerHTML = `<span class="text-red-600">Error: ${parsed.error}</span>`;
                                    }
                                } catch (e) {
                                    console.error('Error parsing JSON:', e);
                                }
                            }
                        });
                        
                        return readStream();
                    });
                }
                
                await readStream();
                
            } catch (error) {
                console.error('Regenerate error:', error);
                messageContent.innerHTML = `<span class="text-red-600">Failed to regenerate: ${error.message}</span>`;
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        }


        // ✅ UPDATED: Show translate dropdown with better language names
        function showTranslateDropdown(btn, messageId) {
            // Remove any existing dropdowns
            document.querySelectorAll('.translate-dropdown').forEach(d => d.remove());
            
            const languages = [
                { code: 'Spanish', name: 'Spanish' },
                { code: 'French', name: 'French' },
                { code: 'German', name: 'German' },
                { code: 'Italian', name: 'Italian' },
                { code: 'Portuguese', name: 'Portuguese' },
                { code: 'Russian', name: 'Russian' },
                { code: 'Japanese', name: 'Japanese' },
                { code: 'Korean', name: 'Korean' },
                { code: 'Chinese', name: 'Chinese' },
                { code: 'Arabic', name: 'Arabic' },
                { code: 'Hindi', name: 'Hindi' },
                { code: 'Bengali', name: 'Bengali' },
                { code: 'Dutch', name: 'Dutch' },
                { code: 'Turkish', name: 'Turkish' },
                { code: 'Vietnamese', name: 'Vietnamese' }
            ];
            
            const dropdown = document.createElement('div');
            dropdown.className = 'translate-dropdown show';
            dropdown.innerHTML = languages.map(lang => `
                <div class="translate-option" data-lang="${lang.code}" data-message-id="${messageId}">
                    ${lang.name}
                </div>
            `).join('');
            
            btn.parentElement.style.position = 'relative';
            btn.parentElement.appendChild(dropdown);
            
            // Position dropdown
            dropdown.style.position = 'absolute';
            dropdown.style.top = '100%';
            dropdown.style.left = '0';
            
            // Close dropdown when clicking outside
            setTimeout(() => {
                document.addEventListener('click', function closeDropdown(e) {
                    if (!dropdown.contains(e.target) && e.target !== btn) {
                        dropdown.remove();
                        document.removeEventListener('click', closeDropdown);
                    }
                });
            }, 10);
            
            // Handle language selection
            dropdown.querySelectorAll('.translate-option').forEach(option => {
                option.addEventListener('click', async () => {
                    const targetLang = option.dataset.lang;
                    const messageId = option.dataset.messageId;
                    dropdown.remove();
                    await translateMessage(messageId, targetLang, btn);
                });
            });
        }

        // ✅ FIXED: Translate message function - shows INLINE below the message
        async function translateMessage(messageId, targetLang, btn) {
            const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
            if (!messageElement) {
                alert('Message not found');
                return;
            }
            
            const messageContent = messageElement.querySelector('.message-content') || messageElement;
            const originalText = messageContent.textContent.trim();
            
            if (!originalText) {
                alert('No text to translate');
                return;
            }
            
            // Check if translation already exists and remove it
            const existingTranslation = messageElement.querySelector('.translation-inline');
            if (existingTranslation) {
                existingTranslation.remove();
            }
            
            btn.innerHTML = '<i class="las la-spinner la-spin"></i> Translating...';
            btn.disabled = true;
            
            try {
                console.log('Translating to:', targetLang);
                
                const response = await fetch('{{ route("translate.text") }}', {
                    method: 'POST',
                    body: JSON.stringify({
                        text: originalText,
                        target_lang: targetLang
                    }),
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                console.log('Translation response status:', response.status);
                
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({ error: 'Unknown error' }));
                    throw new Error(errorData.error || `HTTP ${response.status}`);
                }
                
                const data = await response.json();
                console.log('Translation successful');
                
                if (!data.translatedText) {
                    throw new Error('No translation returned');
                }
                
                // ✅ Show translation INLINE below the message
                showTranslationInline(messageElement, originalText, data.translatedText, targetLang);
                
            } catch (error) {
                console.error('Translation error:', error);
                alert('Translation failed: ' + error.message);
            } finally {
                btn.innerHTML = '<i class="las la-language"></i> Translate';
                btn.disabled = false;
            }
        }

        // ✅ NEW: Show translation inline below the message
        function showTranslationInline(messageElement, originalText, translatedText, targetLang) {
            // Remove any existing translation
            const existingTranslation = messageElement.querySelector('.translation-inline');
            if (existingTranslation) {
                existingTranslation.remove();
            }
            
            // Create translation container
            const translationDiv = document.createElement('div');
            translationDiv.className = 'translation-inline';
            translationDiv.innerHTML = `
                <div class="translation-inline-header">
                    <span class="translation-inline-label">
                        <i class="las la-language"></i> Translation (${targetLang})
                    </span>
                    <div class="translation-inline-actions">
                        <button class="translation-inline-btn copy-translation-btn">
                            <i class="las la-copy"></i> Copy
                        </button>
                        <button class="translation-inline-btn close-translation-btn">
                            <i class="las la-times"></i> Close
                        </button>
                    </div>
                </div>
                <div class="translation-inline-text">${escapeHtml(translatedText)}</div>
            `;
            
            // Find where to insert - after message content but inside the message element
            const messageContent = messageElement.querySelector('.message-content');
            if (messageContent) {
                messageContent.parentElement.insertBefore(translationDiv, messageContent.nextSibling);
            } else {
                messageElement.appendChild(translationDiv);
            }
            
            // Add event listeners for the inline actions
            const copyBtn = translationDiv.querySelector('.copy-translation-btn');
            copyBtn.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(translatedText);
                    const originalHTML = copyBtn.innerHTML;
                    copyBtn.innerHTML = '<i class="las la-check"></i> Copied!';
                    setTimeout(() => {
                        copyBtn.innerHTML = originalHTML;
                    }, 2000);
                } catch (err) {
                    console.error('Failed to copy:', err);
                }
            });
            
            const closeBtn = translationDiv.querySelector('.close-translation-btn');
            closeBtn.addEventListener('click', () => {
                translationDiv.remove();
            });
            
            // Scroll to show the translation
            translationDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // ✅ NEW: Show translation modal
        function showTranslationModal(originalText, translatedText, targetLang) {
            // Create modal
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50';
            modal.innerHTML = `
                <div class="bg-white rounded-lg shadow-2xl max-w-2xl w-full mx-4 max-h-[80vh] overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-4">
                        <h3 class="text-lg font-semibold">Translation</h3>
                    </div>
                    <div class="p-6 overflow-y-auto max-h-[60vh]">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Original:</label>
                            <div class="bg-gray-50 p-4 rounded-lg text-sm text-gray-800 whitespace-pre-wrap">${escapeHtml(originalText)}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Translated (${targetLang.toUpperCase()}):</label>
                            <div class="bg-purple-50 p-4 rounded-lg text-sm text-gray-800 whitespace-pre-wrap">${escapeHtml(translatedText)}</div>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 px-6 py-4 bg-gray-50 border-t">
                        <button class="copy-translation-btn px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="las la-copy"></i> Copy Translation
                        </button>
                        <button class="close-translation-modal px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Close modal handlers
            modal.querySelector('.close-translation-modal').addEventListener('click', () => {
                modal.remove();
            });
            
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.remove();
                }
            });
            
            // Copy translation
            modal.querySelector('.copy-translation-btn').addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(translatedText);
                    const btn = modal.querySelector('.copy-translation-btn');
                    const originalHTML = btn.innerHTML;
                    btn.innerHTML = '<i class="las la-check"></i> Copied!';
                    setTimeout(() => {
                        btn.innerHTML = originalHTML;
                    }, 2000);
                } catch (err) {
                    console.error('Failed to copy:', err);
                }
            });
        }

        function getEmptyStateHTML() {
            return `
                <div class="text-center text-gray-500 mt-8">
                    <i class="las la-comments text-4xl mb-2"></i>
                    <p>Start a conversation to see responses here</p>
                </div>
            `;
        }

        // ✅ File input change handler with better preview
        fileInput.addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (file) {
                currentAttachmentFile = file; // Store the file
                fileNameSpan.innerHTML = '';
                
                const fileType = file.type.toLowerCase();
                const fileName = file.name;
                const fileSize = (file.size / 1024).toFixed(2) + ' KB';
                
                if (fileType.startsWith('image/')) {
                    // For images, show thumbnail
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'max-h-16 max-w-32 rounded border-2 border-white/30';
                        fileNameSpan.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                } else {
                    // For non-image files, show file info
                    const fileInfo = document.createElement('div');
                    fileInfo.className = 'flex items-center space-x-2';
                    
                    let icon = 'la-file';
                    let iconColor = 'text-white';
                    if (fileType.includes('pdf')) {
                        icon = 'la-file-pdf';
                        iconColor = 'text-red-300';
                    } else if (fileType.includes('word') || fileType.includes('document')) {
                        icon = 'la-file-word';
                        iconColor = 'text-blue-300';
                    }
                    
                    // Truncate long file names
                    const displayName = fileName.length > 25 ? fileName.substring(0, 22) + '...' : fileName;
                    
                    fileInfo.innerHTML = `
                        <i class="las ${icon} ${iconColor} text-2xl"></i>
                        <div>
                            <div class="font-medium text-sm" title="${fileName}">${displayName}</div>
                            <div class="text-xs opacity-75">${fileSize}</div>
                        </div>
                    `;
                    fileNameSpan.appendChild(fileInfo);
                }
                attachmentPreview.classList.remove('hidden');
            }
        });

        removeFileButton.addEventListener('click', function() {
            fileInput.value = '';
            currentAttachmentFile = null;
            attachmentPreview.classList.add('hidden');
        });

        // ✅ NEW: Preview attachment button handler
        previewAttachmentBtn.addEventListener('click', async function() {
            if (currentAttachmentFile) {
                await openAttachmentPreview(currentAttachmentFile);
            }
        });

        // ✅ NEW: Close preview modal handlers
        closePreviewModalBtn.addEventListener('click', () => {
            previewModal.classList.add('hidden');
        });

        closePreviewModalFooterBtn.addEventListener('click', () => {
            previewModal.classList.add('hidden');
        });

        previewModal.addEventListener('click', (e) => {
            if (e.target.id === 'attachment-preview-modal') {
                previewModal.classList.add('hidden');
            }
        });

        // ✅ NEW: Download attachment handler
        downloadAttachmentBtn.addEventListener('click', () => {
            if (currentAttachmentFile) {
                const url = URL.createObjectURL(currentAttachmentFile);
                const a = document.createElement('a');
                a.href = url;
                a.download = currentAttachmentFile.name;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }
        });

        // ✅ NEW: Function to open attachment preview
        async function openAttachmentPreview(file, downloadUrl = null) {
            previewModal.classList.remove('hidden');
            previewModalTitle.textContent = 'File Preview';
            previewModalFilename.textContent = file.name || 'Unknown file';
            
            // Show loading state
            previewContent.innerHTML = `
                <div class="preview-loading">
                    <div class="spinner"></div>
                    <p class="mt-4">Loading preview...</p>
                </div>
            `;

            const fileType = (file.type || '').toLowerCase();
            
            try {
                if (fileType.startsWith('image/')) {
                    await previewImage(file);
                } else if (fileType.includes('pdf') || file.name.toLowerCase().endsWith('.pdf')) {
                    await previewPDF(file);
                } else if (fileType.includes('word') || fileType.includes('document') || 
                           file.name.toLowerCase().endsWith('.docx') || file.name.toLowerCase().endsWith('.doc')) {
                    await previewDOCX(file);
                } else {
                    showUnsupportedPreview(file);
                }
            } catch (error) {
                console.error('Preview error:', error);
                previewContent.innerHTML = `
                    <div class="text-center text-red-600 py-8">
                        <i class="las la-exclamation-circle text-4xl mb-2"></i>
                        <p>Failed to load preview</p>
                        <p class="text-sm text-gray-600 mt-2">${error.message}</p>
                    </div>
                `;
            }
            
            // Update download button
            if (downloadUrl) {
                downloadAttachmentBtn.onclick = () => {
                    const a = document.createElement('a');
                    a.href = downloadUrl;
                    a.download = file.name;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                };
            }
        }

        // ✅ NEW: Preview image
        async function previewImage(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewContent.innerHTML = `
                        <div class="flex items-center justify-center">
                            <img src="${e.target.result}" 
                                 alt="${file.name}" 
                                 class="max-w-full max-h-[70vh] rounded-lg shadow-lg">
                        </div>
                    `;
                    resolve();
                };
                reader.onerror = () => reject(new Error('Failed to read image file'));
                reader.readAsDataURL(file);
            });
        }

        // ✅ NEW: Preview PDF
        async function previewPDF(file) {
            if (typeof pdfjsLib === 'undefined') {
                throw new Error('PDF.js library not loaded');
            }

            return new Promise(async (resolve, reject) => {
                try {
                    const arrayBuffer = await file.arrayBuffer();
                    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
                    
                    previewContent.innerHTML = `
                        <div class="space-y-4">
                            <div class="text-center text-gray-600 mb-4">
                                <p class="font-semibold">Total Pages: ${pdf.numPages}</p>
                            </div>
                            <div id="pdf-pages-container"></div>
                        </div>
                    `;
                    
                    const pagesContainer = document.getElementById('pdf-pages-container');
                    
                    // Render first 10 pages (to avoid performance issues)
                    const maxPages = Math.min(pdf.numPages, 10);
                    
                    for (let pageNum = 1; pageNum <= maxPages; pageNum++) {
                        const page = await pdf.getPage(pageNum);
                        const viewport = page.getViewport({ scale: 1.5 });
                        
                        const canvas = document.createElement('canvas');
                        canvas.className = 'pdf-page-canvas w-full';
                        canvas.width = viewport.width;
                        canvas.height = viewport.height;
                        
                        const pageDiv = document.createElement('div');
                        pageDiv.className = 'mb-4';
                        pageDiv.innerHTML = `
                            <div class="text-sm text-gray-600 mb-2 font-semibold">Page ${pageNum}</div>
                        `;
                        pageDiv.appendChild(canvas);
                        pagesContainer.appendChild(pageDiv);
                        
                        const context = canvas.getContext('2d');
                        await page.render({
                            canvasContext: context,
                            viewport: viewport
                        }).promise;
                    }
                    
                    if (pdf.numPages > 10) {
                        pagesContainer.innerHTML += `
                            <div class="text-center text-gray-600 py-4">
                                <p>Showing first 10 pages of ${pdf.numPages}</p>
                                <p class="text-sm">Download the file to view all pages</p>
                            </div>
                        `;
                    }
                    
                    resolve();
                } catch (error) {
                    reject(error);
                }
            });
        }

        // ✅ NEW: Preview DOCX
        async function previewDOCX(file) {
            if (typeof mammoth === 'undefined') {
                throw new Error('Mammoth.js library not loaded');
            }

            return new Promise(async (resolve, reject) => {
                try {
                    const arrayBuffer = await file.arrayBuffer();
                    const result = await mammoth.convertToHtml({ arrayBuffer: arrayBuffer });
                    
                    previewContent.innerHTML = `
                        <div class="docx-preview">
                            ${result.value}
                        </div>
                    `;
                    
                    if (result.messages.length > 0) {
                        console.warn('DOCX conversion messages:', result.messages);
                    }
                    
                    resolve();
                } catch (error) {
                    reject(error);
                }
            });
        }

        // ✅ NEW: Show unsupported file type
        function showUnsupportedPreview(file) {
            const fileSize = (file.size / 1024).toFixed(2);
            const fileType = file.type || 'Unknown';
            
            previewContent.innerHTML = `
                <div class="text-center text-gray-600 py-8">
                    <i class="las la-file text-6xl mb-4"></i>
                    <p class="text-lg font-semibold mb-2">Preview not available</p>
                    <p class="text-sm">This file type cannot be previewed</p>
                    <div class="mt-6 inline-block bg-gray-100 rounded-lg p-4 text-left">
                        <div class="text-sm space-y-1">
                            <p><strong>File Name:</strong> ${file.name}</p>
                            <p><strong>File Type:</strong> ${fileType}</p>
                            <p><strong>File Size:</strong> ${fileSize} KB</p>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mt-4">Click download to save this file</p>
                </div>
            `;
        }

        compareForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (selectedModels.length === 0) {
                alert('Please select at least one model to compare.');
                return;
            }

            const message = messageInput.value.trim();
            if (!message) {
                alert('Please enter a message.');
                return;
            }

            // ✅ NEW: Auto-detect image generation intent
            const createImageCheckbox = document.getElementById('create-image');
            if (!createImageCheckbox.checked && detectImageGenerationIntent(message)) {
                const supportsImageGen = selectedModels.some(m => modelSupportsImageGen(m.model));
                if (supportsImageGen) {
                    createImageCheckbox.checked = true;
                    console.log('🎨 Auto-enabled image generation based on message content');
                }
            }

            startComparison();
        });

        // ✅ NEW: Intelligent image generation detection
        function detectImageGenerationIntent(message) {
            const imageTriggerKeywords = [
                'generate image',
                'generate an image',
                'create image',
                'create an image',
                'make image',
                'make an image',
                'draw image',
                'draw an image',
                'draw a picture',
                'create picture',
                'generate picture',
                'paint image',
                'paint picture',
                'design image',
                'design picture',
                'illustrate',
                'sketch',
                'render image',
                'produce image',
                'show me image',
                'show me picture',
                'visualize'
            ];
            
            const messageLower = message.toLowerCase();
            
            // Check if message contains any trigger keywords
            return imageTriggerKeywords.some(keyword => messageLower.includes(keyword));
        }

        // ✅ UPDATE: Modified startComparison() to include optimization_mode
        function startComparison() {
            const message = messageInput.value.trim();
            const formData = new FormData();
            
            formData.append('message', message);
            
            // ✅ Handle model selection based on optimization mode
            if (currentOptimizationMode === 'smart_all') {
                // For Smart (All), send a special indicator to backend
                formData.append('models', JSON.stringify(['smart_all_auto']));
            } else if (currentOptimizationMode === 'smart_same') {
                // ✅ FIXED: For Smart (Same), send the first model from each selected provider
                const providerModels = selectedModels.map(m => {
                    const provider = m.provider;
                    const providerCheckbox = document.querySelector(`input.provider-checkbox[value="${provider}"]`);
                    return providerCheckbox ? providerCheckbox.dataset.firstModel : m.model;
                });
                formData.append('models', JSON.stringify(providerModels));
            } else {
                // For Fixed, send selected models as-is
                formData.append('models', JSON.stringify(selectedModels.map(m => m.model)));
            }
            
            formData.append('web_search', document.getElementById('web-search').checked ? '1' : '0');
            formData.append('create_image', document.getElementById('create-image').checked ? '1' : '0');
            
            // Add optimization mode
            formData.append('optimization_mode', currentOptimizationMode);
            
            if (currentConversationId) {
                formData.append('conversation_id', currentConversationId);
            }
            
            if (fileInput.files[0]) {
                formData.append('pdf', fileInput.files[0]);
            }

            console.log('Starting comparison with mode:', currentOptimizationMode);
            
            // ... rest of the function remains the same

            sendButton.classList.add('hidden');
            stopButton.classList.remove('hidden');
            messageInput.disabled = true;
            optionsDropdownBtn.disabled = true;

            // ✅ For Smart (All), ensure we have the panel
            if (currentOptimizationMode === 'smart_all' && selectedModels.length === 0) {
                selectedModels = [{
                    model: 'smart_all_auto',
                    provider: 'smart_all',
                    displayName: 'Smart Mode'
                }];
                generateModelPanels();
            }

            selectedModels.forEach(model => {
                addMessageToConversation(model.model, 'user', message);
                updateModelStatus(model.model, 'waiting');
            });

            // Clear form
            messageInput.value = '';
            messageInput.style.height = 'auto';
            fileInput.value = '';
            currentAttachmentFile = null;
            attachmentPreview.classList.add('hidden');

            document.getElementById('create-image').checked = false;
            document.getElementById('web-search').checked = false;

            abortController = new AbortController();

            fetch('{{ route("chat.multi-compare") }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                signal: abortController.signal
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.body;
            })
            .then(body => {
                const reader = body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                function readStream() {
                    return reader.read().then(({ done, value }) => {
                        if (done) {
                            debugLog('Stream completed');
                            resetUI();
                            return;
                        }

                        buffer += decoder.decode(value, { stream: true });
                        const lines = buffer.split('\n');
                        buffer = lines.pop();

                        lines.forEach(line => {
                            if (line.trim() && line.startsWith('data: ')) {
                                const data = line.slice(6);
                                if (data === '[DONE]') {
                                    debugLog('Stream done');
                                    resetUI();
                                    return;
                                }

                                try {
                                    const parsed = JSON.parse(data);
                                    handleStreamMessage(parsed);
                                } catch (e) {
                                    console.error('Error parsing JSON:', e, data);
                                }
                            }
                        });

                        return readStream();
                    });
                }

                return readStream();
            })
            .catch(error => {
                if (error.name === 'AbortError') {
                    debugLog('Request aborted');
                } else {
                    console.error('Error:', error);
                    alert('An error occurred: ' + error.message);
                }
                resetUI();
            });
        }

        // ✅ NEW: Update handleStreamMessage to show model changes
        function handleStreamMessage(data) {
            switch (data.type) {
                case 'init':
                    debugLog('Initialized with models', data.models);
                    if (data.conversation_id) {
                        currentConversationId = data.conversation_id;
                    }
                    
                    // ✅ Build model ID mapping for optimized models (text chat)
                    if (data.optimized_models) {
                        console.log('Models optimized:', data.optimized_models);
                        modelIdMapping = {}; // Reset mapping
                        
                        Object.keys(data.optimized_models).forEach(original => {
                            const optimized = data.optimized_models[original];
                            
                            if (currentOptimizationMode === 'smart_all') {
                                modelIdMapping[optimized] = 'smart_all_auto';
                            } else if (currentOptimizationMode === 'smart_same') {
                                let providerFound = null;
                                
                                document.querySelectorAll('input.model-checkbox').forEach(cb => {
                                    if (cb.value === original) {
                                        providerFound = cb.dataset.provider;
                                    }
                                });
                                
                                if (providerFound) {
                                    modelIdMapping[optimized] = `${providerFound}_smart_panel`;
                                    console.log(`✅ Mapped ${optimized} → ${providerFound}_smart_panel`);
                                } else {
                                    if (optimized.includes('gemini') || optimized.includes('google')) {
                                        modelIdMapping[optimized] = 'gemini_smart_panel';
                                    } else if (optimized.includes('gpt') || optimized.includes('o3')) {
                                        modelIdMapping[optimized] = 'openai_smart_panel';
                                    } else if (optimized.includes('claude')) {
                                        modelIdMapping[optimized] = 'claude_smart_panel';
                                    } else if (optimized.includes('grok')) {
                                        modelIdMapping[optimized] = 'grok_smart_panel';
                                    } else {
                                        modelIdMapping[optimized] = original;
                                    }
                                    console.log(`⚠️ Fallback mapping: ${optimized} → ${modelIdMapping[optimized]}`);
                                }
                            } else {
                                modelIdMapping[optimized] = original;
                            }
                        });
                        
                        console.log('✅ Model ID mapping created:', modelIdMapping);
                    }
                    
                    // ✅ NEW: Handle image generation model mapping
                    if (data.model_mapping) {
                        imageGenModelMapping = data.model_mapping;
                        console.log('🖼️ Image generation model mapping:', imageGenModelMapping);
                    }
                    break;

      case 'model_start':
    // ✅ Use mapped model ID if available
    const startModelId = modelIdMapping[data.model] || imageGenModelMapping[data.actual_model] || data.model;
    console.log(`🟢 Model starting: ${data.model} → panel: ${startModelId}`, {
        hasTextMapping: !!modelIdMapping[data.model],
        hasImageMapping: !!imageGenModelMapping[data.actual_model],
        actualModel: data.actual_model
    });
    
    updateModelStatus(startModelId, 'running');
    
    // ✅ CRITICAL: This creates the response element!
    addMessageToConversation(startModelId, 'assistant', '', true);
    
    // Show optimization indicator (skip for Smart modes)
    if (data.was_optimized && currentOptimizationMode === 'fixed') {
        const modelPanel = document.querySelector(`[data-model-id="${startModelId}"]`);
        if (modelPanel) {
            const header = modelPanel.querySelector('.model-panel-header span');
            if (header && !header.querySelector('.model-optimization-indicator')) {
                const indicator = document.createElement('span');
                indicator.className = 'model-optimization-indicator';
                indicator.innerHTML = '<i class="las la-magic"></i> Optimized';
                indicator.title = `Changed from ${data.original_model || 'previous model'}`;
                header.appendChild(indicator);
            }
        }
    }
    break;

                case 'chunk':
                    // ✅ Use mapped model ID if available
                    const chunkModelId = modelIdMapping[data.model] || imageGenModelMapping[data.actual_model] || data.model;
                    console.log(`📝 Chunk received: ${data.model} → panel: ${chunkModelId}`);
                    updateModelResponse(chunkModelId, data.content, data.full_response);
                    break;

                case 'complete':
                    // ✅ Use mapped model ID if available - prioritize image mapping for image responses
                    let completeModelId;
                    if (data.image && data.actual_model && imageGenModelMapping[data.actual_model]) {
                        // For image responses, use the image generation mapping
                        completeModelId = imageGenModelMapping[data.actual_model];
                        console.log(`✅ Complete (IMAGE): ${data.actual_model} → panel: ${completeModelId}`, {
                            imageUrl: data.image ? data.image.substring(0, 50) + '...' : 'null',
                            usingImageMapping: true
                        });
                    } else {
                        // For text responses, use the text model mapping
                        completeModelId = modelIdMapping[data.model] || data.model;
                        console.log(`✅ Complete (TEXT): ${data.model} → panel: ${completeModelId}`);
                    }
                    
                    updateModelStatus(completeModelId, 'completed');
                    
                    if (data.image) {
                        console.log('🖼️ Image data received', {
                            model: data.model,
                            actualModel: data.actual_model,
                            mappedModel: completeModelId,
                            imageUrl: data.image ? data.image.substring(0, 50) + '...' : 'null',
                            prompt: data.prompt,
                            imageMapping: imageGenModelMapping
                        });
                        
                        finalizeModelImageResponse(completeModelId, data.image, data.prompt);
                    } else {
                        finalizeModelResponse(completeModelId, data.final_response || data.full_response);
                    }
                    break;

                case 'error':
                    // ✅ Use mapped model ID if available
                    const errorModelId = modelIdMapping[data.model] || imageGenModelMapping[data.actual_model] || data.model;
                    console.log(`❌ Error: ${data.model} → panel: ${errorModelId}`, {
                        error: data.error,
                        actualModel: data.actual_model
                    });
                    
                    updateModelStatus(errorModelId, 'error');
                    
                    // ✅ Display error message properly
                    const errorResponseElement = modelResponseElements[errorModelId];
                    if (errorResponseElement) {
                        errorResponseElement.innerHTML = `
                            <div class="text-red-600 p-4 border border-red-300 rounded-lg bg-red-50">
                                <div class="flex items-start gap-3">
                                    <i class="las la-exclamation-triangle text-2xl flex-shrink-0"></i>
                                    <div>
                                        <p class="font-semibold mb-1">Error</p>
                                        <p class="text-sm">${escapeHtml(data.error)}</p>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        // Try to find and create response element if missing
                        console.warn(`No response element for ${errorModelId}, attempting to create...`);
                        addMessageToConversation(errorModelId, 'assistant', '', false);
                        
                        setTimeout(() => {
                            const newErrorElement = modelResponseElements[errorModelId];
                            if (newErrorElement) {
                                newErrorElement.innerHTML = `
                                    <div class="text-red-600 p-4 border border-red-300 rounded-lg bg-red-50">
                                        <div class="flex items-start gap-3">
                                            <i class="las la-exclamation-triangle text-2xl flex-shrink-0"></i>
                                            <div>
                                                <p class="font-semibold mb-1">Error</p>
                                                <p class="text-sm">${escapeHtml(data.error)}</p>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            } else {
                                console.error('Failed to create error display element');
                            }
                        }, 100);
                    }
                    
                    // Also update conversation history with error
                    if (conversationHistory[errorModelId] && conversationHistory[errorModelId].length > 0) {
                        const lastEntry = conversationHistory[errorModelId][conversationHistory[errorModelId].length - 1];
                        lastEntry.response = data.error;
                    }
                    break;

                case 'all_complete':
                    debugLog('All models completed');
                    if (data.conversation_id) {
                        currentConversationId = data.conversation_id;
                        loadConversations();
                    }
                    updateUserStats();
                    // Clear mappings after completion
                    modelIdMapping = {};
                    imageGenModelMapping = {}; // ✅ Clear image mapping too
                    break;
            }
        }

        function finalizeModelImageResponse(model, imageUrl, prompt) {
            console.log('🖼️ Finalizing image response', {
                model: model,
                imageUrl: imageUrl ? imageUrl.substring(0, 50) + '...' : 'null',
                hasPrompt: !!prompt,
                currentMode: currentOptimizationMode
            });

            let responseElement = modelResponseElements[model];
            
            // ✅ FIX: If responseElement doesn't exist, try to find it
            if (!responseElement) {
                console.warn(`No response element in cache for ${model}, trying to find it...`);
                
                // Try to find the conversation div
                const conversationDiv = document.getElementById(`conversation-${model}`);
                if (conversationDiv) {
                    // Look for the last assistant response
                    const lastResponse = conversationDiv.querySelector('.assistant-response:last-child .message-content');
                    if (lastResponse) {
                        responseElement = lastResponse;
                        modelResponseElements[model] = responseElement;
                        console.log(`✅ Found response element for ${model}`);
                    }
                }
                
                // Still not found? Try to find the panel itself
                if (!responseElement) {
                    const panel = document.querySelector(`[data-model-id="${model}"]`);
                    if (panel) {
                        const conv = panel.querySelector('.model-conversation');
                        if (conv) {
                            console.log(`✅ Found panel for ${model}, creating response container...`);
                            addMessageToConversation(model, 'assistant', '', false);
                            responseElement = modelResponseElements[model];
                        }
                    }
                }
            }
            
            if (!responseElement) {
                console.error(`❌ Could not find or create response element for model: ${model}`);
                console.log('Available panels:', Array.from(document.querySelectorAll('.model-panel')).map(p => p.dataset.modelId));
                console.log('Model response elements:', Object.keys(modelResponseElements));
                
                // Last resort: show alert
                alert(`Failed to display image for ${model}. Check console for details.`);
                return;
            }

            console.log(`✅ Response element ready for ${model}`);

            // Clear any existing content
            responseElement.innerHTML = '';
            
            // Create image container
            const imgContainer = document.createElement('div');
            imgContainer.className = 'my-4';
            
            // Create image element
            const img = document.createElement('img');
            img.src = imageUrl;
            img.alt = prompt || 'Generated image';
            img.className = 'rounded-lg max-w-full h-auto shadow-md cursor-pointer';
            
            // Add click handler for modal
            img.addEventListener('click', () => {
                openImageModal(imageUrl, prompt || 'Generated image');
            });
            
            // Add error handler
            img.onerror = function() {
                console.error('❌ Failed to load image:', imageUrl);
                responseElement.innerHTML = `
                    <div class="text-red-600 p-4 border border-red-300 rounded-lg bg-red-50">
                        <div class="flex items-start gap-3">
                            <i class="las la-exclamation-triangle text-2xl flex-shrink-0"></i>
                            <div>
                                <p class="font-semibold mb-1">Failed to load image</p>
                                <p class="text-sm">The generated image could not be loaded.</p>
                                <p class="text-xs mt-2 break-all">URL: ${imageUrl.substring(0, 100)}...</p>
                            </div>
                        </div>
                    </div>
                `;
            };
            
            // Add load handler for confirmation
            img.onload = function() {
                console.log('✅ Image loaded successfully for', model);
            };
            
            imgContainer.appendChild(img);
            
            // Add prompt text if provided
            if (prompt) {
                const promptText = document.createElement('p');
                promptText.className = 'text-sm text-gray-600 mt-2 italic';
                promptText.textContent = `Prompt: ${prompt}`;
                imgContainer.appendChild(promptText);
            }
            
            responseElement.appendChild(imgContainer);

            // Update conversation history
            if (conversationHistory[model] && conversationHistory[model].length > 0) {
                const lastEntry = conversationHistory[model][conversationHistory[model].length - 1];
                lastEntry.response = imageUrl;
                console.log('✅ Updated conversation history for', model);
            } else {
                console.warn('⚠️ No conversation history entry to update for', model);
            }

            // Scroll to show the image
            const conversationDiv = document.getElementById(`conversation-${model}`);
            if (conversationDiv) {
                conversationDiv.scrollTop = conversationDiv.scrollHeight;
            }
            
            console.log('✅ Image finalized for', model);
        }


        function isImageURL(str) {
            if (!str || typeof str !== 'string') return false;
            
            try {
                const url = new URL(str);
                const pathname = url.pathname.toLowerCase();
                const imageExtensions = ['.png', '.jpg', '.jpeg', '.gif', '.webp', '.bmp'];
                
                return imageExtensions.some(ext => pathname.endsWith(ext));
            } catch (e) {
                return false;
            }
        }

        function openImageModal(src, alt = '') {
            let modal = document.getElementById('image-modal');
            
            if (!modal) {
                const modalHTML = `
                    <div id="image-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-80 hidden">
                        <div class="relative max-w-7xl max-h-screen p-4">
                            <img id="modal-image" class="max-h-screen max-w-full rounded-lg" />
                            <button id="modal-close" class="absolute top-2 right-2 text-white text-xl bg-black bg-opacity-50 px-3 py-1 rounded hover:bg-opacity-75">&times;</button>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHTML);
                
                modal = document.getElementById('image-modal');
                
                document.getElementById('modal-close').addEventListener('click', () => {
                    modal.classList.add('hidden');
                });
                
                modal.addEventListener('click', (e) => {
                    if (e.target.id === 'image-modal') {
                        modal.classList.add('hidden');
                    }
                });
            }
            
            const modalImg = document.getElementById('modal-image');
            modalImg.src = src;
            modalImg.alt = alt;
            modal.classList.remove('hidden');
        }

        function updateModelStatus(model, status) {
            const statusElement = document.getElementById(`status-${model}`);
            if (statusElement) {
                statusElement.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                statusElement.className = `model-status ${status}`;
            }
        }

        // ✅ NEW: Helper function to create consistent user message HTML
        function createUserMessageHTML(content, messageId, file = null, attachment = null) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'conversation-entry';
            
            const userDiv = document.createElement('div');
            userDiv.className = 'user-prompt';
            userDiv.dataset.messageId = messageId;
            userDiv.innerHTML = formatUserMessage(content);
            
            // Add attachment if present (for real-time with file)
            if (file) {
                const fileType = file.type.toLowerCase();
                const attachmentContainer = document.createElement('div');
                
                if (fileType.startsWith('image/')) {
                    // For images
                    attachmentContainer.className = 'mt-2 flex items-center gap-2';
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        attachmentContainer.innerHTML = `
                            <img src="${e.target.result}" 
                                alt="${file.name}" 
                                class="w-12 h-12 object-cover rounded cursor-pointer hover:opacity-80 transition border border-white/30"
                                onclick="openImageModal('${e.target.result}', '${file.name}')"
                                title="Click to view full size">
                            <a href="${e.target.result}" 
                                download="${file.name}"
                                class="text-xs text-white/70 hover:text-white flex-1 truncate underline decoration-dotted hover:decoration-solid transition-all" 
                                title="Click to download: ${file.name}"
                                onclick="event.stopPropagation()">
                                ${file.name}
                            </a>
                        `;
                    };
                    reader.readAsDataURL(file);
                    userDiv.appendChild(attachmentContainer);
                } else {
                    // For documents
                    attachmentContainer.className = 'mt-2 flex items-center gap-2 text-xs text-white/80';
                    
                    let icon = 'la-paperclip';
                    if (fileType.includes('pdf')) icon = 'la-file-pdf';
                    else if (fileType.includes('word') || fileType.includes('document')) icon = 'la-file-word';
                    
                    const displayName = file.name.length > 30 ? file.name.substring(0, 27) + '...' : file.name;
                    const fileUrl = URL.createObjectURL(file);
                    
                    attachmentContainer.innerHTML = `
                        <i class="las ${icon}"></i>
                        <a href="${fileUrl}" 
                            download="${file.name}"
                            class="truncate hover:text-white underline decoration-dotted hover:decoration-solid transition-all" 
                            title="Click to download: ${file.name}"
                            onclick="event.stopPropagation()">
                            ${displayName}
                        </a>
                    `;
                    userDiv.appendChild(attachmentContainer);
                }
            }
            
            // Add attachment if present (for history with attachment object)
            if (attachment) {
                const attachmentBadge = createAttachmentBadge(attachment);
                userDiv.appendChild(attachmentBadge);
            }
            
            messageDiv.appendChild(userDiv);
            messageDiv.appendChild(createMessageActions(messageId, false));
            
            return messageDiv;
        }

        function addMessageToConversation(model, role, content, isStreaming = false) {
            const conversationDiv = document.getElementById(`conversation-${model}`);
            if (!conversationDiv) {
                console.error('❌ Conversation div not found for model:', model);
                return;
            }

            const emptyState = conversationDiv.querySelector('.text-center');
            if (emptyState) {
                emptyState.remove();
            }

            if (role === 'user') {
                const messageId = `user-msg-${model}-${Date.now()}`;
                const fileInputEl = document.getElementById('file-input');
                const file = (fileInputEl && fileInputEl.files && fileInputEl.files[0]) ? fileInputEl.files[0] : null;
                
                // ✅ Use consistent helper function
                const messageDiv = createUserMessageHTML(content, messageId, file);
                conversationDiv.appendChild(messageDiv);
                
                if (!conversationHistory[model]) conversationHistory[model] = [];
                conversationHistory[model].push({
                    prompt: content,
                    response: ''
                });
            } else {
                // Assistant response code remains the same
                const messageDiv = document.createElement('div');
                messageDiv.className = 'conversation-entry';
                const responseId = `response-${model}-${Date.now()}`;
                const messageId = `assistant-msg-${model}-${Date.now()}`;
                
                messageDiv.innerHTML = `
                    <div class="assistant-response" data-message-id="${messageId}" data-model="${model}">
                        <div class="message-content" id="${responseId}">
                            ${isStreaming ? '<div class="thinking-indicator flex space-x-1"><div class="dot w-2 h-2 rounded-full"></div><div class="dot w-2 h-2 rounded-full"></div><div class="dot w-2 h-2 rounded-full"></div></div>' : ''}
                        </div>
                        <div class="message-actions">
                            <button class="message-action-btn copy-msg-btn" data-message-id="${messageId}" title="Copy Response">
                                <i class="las la-copy"></i>
                            </button>
                            <button class="message-action-btn read-msg-btn" data-message-id="${messageId}" title="Read Aloud">
                                <i class="las la-volume-up"></i>
                            </button>
                            <button class="message-action-btn regenerate-msg-btn" data-message-id="${messageId}" data-model="${model}" title="Regenerate Response">
                                <i class="las la-redo-alt"></i>
                            </button>
                            <button class="message-action-btn translate-msg-btn" data-message-id="${messageId}" title="Translate Response">
                                <i class="las la-language"></i>
                            </button>
                        </div>
                    </div>
                `;
                conversationDiv.appendChild(messageDiv);
                
                const responseElement = document.getElementById(responseId);
                modelResponseElements[model] = responseElement;
                
                if (!responseElement) {
                    console.error(`❌ FAILED to create response element for ${model}!`, {
                        responseId: responseId,
                        conversationDivExists: !!conversationDiv,
                        messageDivExists: !!messageDiv,
                        conversationDivHTML: conversationDiv ? conversationDiv.innerHTML.substring(0, 200) : 'null'
                    });
                } else {
                    console.log(`✅ Response element created for ${model}`, {
                        responseId: responseId,
                        elementExists: true,
                        isStreaming: isStreaming,
                        conversationDivId: conversationDiv.id
                    });
                }
            }

            conversationDiv.scrollTop = conversationDiv.scrollHeight;
        }

        function updateModelResponse(model, chunk, fullResponse) {
            const responseElement = modelResponseElements[model];
            if (!responseElement) return;

            if (fullResponse) {
                responseElement.innerHTML = '';
                responseElement.textContent = fullResponse;
                
                if (conversationHistory[model] && conversationHistory[model].length > 0) {
                    const lastEntry = conversationHistory[model][conversationHistory[model].length - 1];
                    lastEntry.response = fullResponse;
                }
            }

            const conversationDiv = document.getElementById(`conversation-${model}`);
            if (conversationDiv) {
                conversationDiv.scrollTop = conversationDiv.scrollHeight;
            }
        }

        function finalizeModelResponse(model, finalResponse) {
            debugLog(`Finalizing response for model: ${model}`, {
                responseLength: finalResponse.length,
                containsChart: finalResponse.includes('```chart')
            });

            const responseElement = modelResponseElements[model];
            if (!responseElement) {
                debugLog(`No response element found for model: ${model}`);
                return;
            }

            responseElement.innerHTML = '';
            responseElement.textContent = finalResponse;

            debugLog(`Processing charts for model: ${model}`);
            processMessageContent(responseElement);

            if (conversationHistory[model] && conversationHistory[model].length > 0) {
                const lastEntry = conversationHistory[model][conversationHistory[model].length - 1];
                lastEntry.response = finalResponse;
            }

            const conversationDiv = document.getElementById(`conversation-${model}`);
            if (conversationDiv) {
                conversationDiv.scrollTop = conversationDiv.scrollHeight;
            }
        }

        function resetUI() {
            sendButton.classList.remove('hidden');
            stopButton.classList.add('hidden');
            messageInput.disabled = false;
            optionsDropdownBtn.disabled = false;
            messageInput.focus();
            abortController = null;
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // ✅ NEW: Format user message with proper formatting
        function formatUserMessage(text) {
            if (!text) return '';
            
            // First escape HTML
            let formatted = escapeHtml(text);
            
            // Make URLs clickable
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            formatted = formatted.replace(urlRegex, (url) => {
                // Remove trailing punctuation that might be caught
                let cleanUrl = url;
                const trailingPunc = /[.,;:!?)]+$/.exec(url);
                let punctuation = '';
                if (trailingPunc) {
                    punctuation = trailingPunc[0];
                    cleanUrl = url.slice(0, -punctuation.length);
                }
                return `<a href="${cleanUrl}" target="_blank" rel="noopener noreferrer">${cleanUrl}</a>${punctuation}`;
            });
            
            // Make email addresses clickable
            const emailRegex = /([a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+\.[a-zA-Z0-9_-]+)/g;
            formatted = formatted.replace(emailRegex, (email) => {
                return `<a href="mailto:${email}">${email}</a>`;
            });
            
            return formatted;
        }

        // Action button handlers
        document.addEventListener('click', (e) => {
            // Archive/Unarchive conversation
            if (e.target.closest('.archive-conversation-btn')) {
                e.stopPropagation(); // Prevent conversation from opening
                const btn = e.target.closest('.archive-conversation-btn');
                const conversationId = btn.dataset.id;
                const isArchived = btn.dataset.archived === 'true';
                
                toggleArchiveConversation(conversationId, isArchived);
                return;
            }
            
            // Edit conversation title
            if (e.target.closest('.edit-conversation-btn')) {
                e.stopPropagation(); // Prevent conversation from opening
                const btn = e.target.closest('.edit-conversation-btn');
                const conversationId = btn.dataset.id;
                const currentTitle = btn.closest('.conversation-item').querySelector('.conversation-title').textContent;
                const newTitle = prompt('Enter new title:', currentTitle);
                if (newTitle && newTitle.trim()) {
                    updateConversationTitle(conversationId, newTitle.trim());
                }
                return;
            }

            // Delete conversation
            if (e.target.closest('.delete-conversation-btn')) {
                e.stopPropagation(); // Prevent conversation from opening
                const btn = e.target.closest('.delete-conversation-btn');
                const conversationId = btn.dataset.id;
                if (confirm('Are you sure you want to delete this conversation?')) {
                    deleteConversation(conversationId);
                }
                return;
            }

            // Conversation item click (load conversation)
            if (e.target.closest('.conversation-item')) {
                // Don't load conversation if we're clicking on action buttons or checkboxes
                if (e.target.closest('.archive-conversation-btn') || 
                    e.target.closest('.edit-conversation-btn') || 
                    e.target.closest('.delete-conversation-btn') ||
                    e.target.closest('.conversation-menu-button') ||
                    e.target.closest('.conversation-checkbox')) {
                    return;
                }
                
                const conversationItem = e.target.closest('.conversation-item');
                const conversationId = conversationItem.dataset.id;
                if (conversationId) {
                    loadConversation(conversationId);
                }
                return;
            }

            // Close model button
            if (e.target.closest('.close-model-btn')) {
                const btn = e.target.closest('.close-model-btn');
                const modelId = btn.dataset.model;
                const modelData = selectedModels.find(m => m.model === modelId);
                
                if (modelData) {
                    const hasHistory = conversationHistory[modelId] && conversationHistory[modelId].length > 0;
                    const confirmMessage = hasHistory 
                        ? `Close ${modelData.displayName}? This will clear its conversation history.`
                        : `Close ${modelData.displayName}?`;
                    
                    if (confirm(confirmMessage)) {
                        let checkbox = null;
                        
                        // ✅ FIX: Handle different optimization modes
                        if (currentOptimizationMode === 'smart_same') {
                            // Extract provider name from panel ID (e.g., "openai_smart_panel" → "openai")
                            const provider = modelId.replace('_smart_panel', '');
                            checkbox = document.querySelector(`input.provider-checkbox[value="${provider}"]`);
                            console.log('Smart Same mode: Looking for provider checkbox:', provider, 'Found:', !!checkbox);
                        } else if (currentOptimizationMode === 'smart_all') {
                            // In Smart All mode, don't allow closing the single panel
                            alert('Cannot close the Smart Mode panel. Switch to Fixed or Smart (Same) mode to select specific models/providers.');
                            return;
                        } else {
                            // Fixed mode - look for model checkbox
                            checkbox = document.querySelector(`input.model-checkbox[value="${modelId}"]`);
                            console.log('Fixed mode: Looking for model checkbox:', modelId, 'Found:', !!checkbox);
                        }
                        
                        if (checkbox) {
                            checkbox.checked = false;
                            delete conversationHistory[modelId];
                            updateSelectedModels();
                            console.log('✅ Successfully closed panel:', modelId);
                        } else {
                            console.error('❌ Could not find checkbox for:', modelId, 'Mode:', currentOptimizationMode);
                            alert('Could not close this panel. Please try using the dropdown menu.');
                        }
                    }
                }
                return;
            }

            // Maximize model button
            if (e.target.closest('.maximize-model-btn')) {
                const btn = e.target.closest('.maximize-model-btn');
                const modelId = btn.dataset.model;
                const modelPanel = document.querySelector(`[data-model-id="${modelId}"]`);
                
                if (modelPanel.classList.contains('maximized')) {
                    minimizeModelPanel(modelPanel, btn);
                } else {
                    maximizeModelPanel(modelPanel, btn);
                }
                return;
            }
            
            // Copy response button
            if (e.target.classList.contains('copy-response-btn')) {
                const model = e.target.dataset.model;
                const history = conversationHistory[model];
                if (history && history.length > 0) {
                    const lastResponse = history[history.length - 1].response;
                    navigator.clipboard.writeText(lastResponse).then(() => {
                        const originalText = e.target.innerHTML;
                        e.target.innerHTML = '✓ Copied!';
                        setTimeout(() => {
                            e.target.innerHTML = originalText;
                        }, 2000);
                    });
                }
                return;
            }
            
            // Read aloud button
            if (e.target.classList.contains('read-aloud-btn')) {
                const model = e.target.dataset.model;
                const history = conversationHistory[model];
                if (history && history.length > 0) {
                    const lastResponse = history[history.length - 1].response;
                    
                    if (window.speechSynthesis.speaking) {
                        window.speechSynthesis.cancel();
                        e.target.innerHTML = '🔊 Read';
                        return;
                    }
                    
                    const speech = new SpeechSynthesisUtterance(lastResponse);
                    speech.rate = 1;
                    speech.pitch = 1;
                    speech.volume = 1;
                    
                    e.target.innerHTML = '⏹ Stop';
                    
                    speech.onend = () => {
                        e.target.innerHTML = '🔊 Read';
                    };
                    
                    window.speechSynthesis.speak(speech);
                }
                return;
            }

            // Clear button
            if (e.target.classList.contains('clear-btn')) {
                const model = e.target.dataset.model;
                const modelData = selectedModels.find(m => m.model === model);
                if (modelData && confirm(`Clear conversation history for ${modelData.displayName}?`)) {
                    conversationHistory[model] = [];
                    const conversationDiv = document.getElementById(`conversation-${model}`);
                    if (conversationDiv) {
                        conversationDiv.innerHTML = getEmptyStateHTML();
                    }
                }
                return;
            }
        });

       // ✅ ENHANCED: Maximize model panel function with full-screen chat
        function maximizeModelPanel(panel, btn) {
            const modelId = btn.dataset.model;
            const modelData = selectedModels.find(m => m.model === modelId);
            
            if (!modelData) return;
            
            // Hide all other panels
            const allPanels = document.querySelectorAll('.model-panel');
            allPanels.forEach(p => {
                if (p !== panel) {
                    p.classList.add('hidden-panel');
                }
            });
            
            // Maximize this panel
            panel.classList.add('maximized');
            modelsContainer.classList.add('has-maximized');
            
            // Hide main chat input
            const mainChatForm = document.getElementById('compare-form');
            mainChatForm.classList.add('hidden-on-maximize');
            
            // Create maximized header overlay
            const headerOverlay = document.createElement('div');
            headerOverlay.className = 'maximized-header-overlay';
            headerOverlay.id = 'maximized-header-overlay';
            headerOverlay.innerHTML = `
                <div class="model-name">${modelData.displayName}</div>
                <div class="close-maximize-btn" onclick="minimizeFromOverlay('${modelId}')">
                    <i class="las la-compress"></i>
                    <span>Exit Full Screen (ESC)</span>
                </div>
            `;
            document.body.appendChild(headerOverlay);
            
            // Create maximized chat input (clone the main form)
            const maximizedInput = document.createElement('div');
            maximizedInput.className = 'maximized-chat-input';
            maximizedInput.id = 'maximized-chat-input';
            maximizedInput.innerHTML = `
                <form id="maximized-compare-form" class="max-w-6xl mx-auto">
                    <!-- File Upload Preview -->
                    <div id="maximized-attachment-preview" class="hidden bg-gray-100 p-3 rounded-lg mb-3 inline-block max-w-max">
                        <div class="flex items-center space-x-3">
                            <i class="las la-paperclip text-gray-700 text-xl"></i>
                            <div id="maximized-file-name" class="text-gray-700 text-sm"></div>
                            <div class="flex items-center space-x-2 ml-auto">
                                <button type="button" id="maximized-preview-attachment-btn" class="text-gray-600 hover:text-gray-800 bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded transition-colors text-sm">
                                    <i class="las la-eye mr-1"></i>Preview
                                </button>
                                <button type="button" id="maximized-remove-file" class="text-red-600 hover:text-red-700 bg-gray-200 hover:bg-gray-300 px-2 py-1 rounded transition-colors">
                                    <i class="las la-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Main Input Area -->
                    <div class="flex items-end space-x-3">
                        <!-- Left Side: Input with inline controls -->
                        <div class="flex-1 relative">
                            <div class="flex items-end bg-white border-2 border-purple-200 rounded-lg focus-within:ring-2 focus-within:ring-purple-500">
                                <!-- Textarea -->
                                <textarea 
                                    id="maximized-message-input" 
                                    name="message" 
                                    placeholder="Type your message here..." 
                                    class="flex-1 p-3 bg-transparent text-gray-800 placeholder-gray-400 focus:outline-none resize-none min-h-[52px] max-h-[200px]"
                                    rows="1"
                                    required></textarea>
                                
                                <!-- Inline Controls -->
                                <div class="flex items-center space-x-2 p-2 pb-3">
                                    <!-- Attachment Button -->
                                    <label for="maximized-file-input" class="cursor-pointer text-gray-500 hover:text-purple-600 transition-colors" title="Attach file">
                                        <i class="las la-paperclip text-xl"></i>
                                    </label>
                                    <input type="file" id="maximized-file-input" name="pdf" class="hidden" 
                                        accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.webp,.gif">
                                    
                                    <!-- Options Dropdown Button -->
                                    <div class="relative">
                                        <button type="button" id="maximized-options-dropdown-btn" class="text-gray-500 hover:text-purple-600 transition-colors" title="More options">
                                            <i class="las la-sliders-h text-xl"></i>
                                        </button>
                                        
                                        <!-- Dropdown Menu -->
                                        <div id="maximized-options-dropdown" class="hidden absolute bottom-full right-0 mb-2 bg-white rounded-lg shadow-lg py-2 min-w-[200px] z-50">
                                            <label class="flex items-center space-x-3 px-4 py-2 hover:bg-gray-100 cursor-pointer transition-colors">
                                                <input type="checkbox" id="maximized-web-search" name="web_search" class="text-purple-600 focus:ring-purple-500 rounded">
                                                <div class="flex items-center space-x-2">
                                                    <i class="las la-search text-gray-700"></i>
                                                    <span class="text-gray-700 text-sm">Web Search</span>
                                                </div>
                                            </label>
                                            
                                            <label class="flex items-center space-x-3 px-4 py-2 hover:bg-gray-100 cursor-pointer transition-colors" id="maximized-create-image-label">
                                                <input type="checkbox" id="maximized-create-image" name="create_image" class="text-purple-600 focus:ring-purple-500 rounded">
                                                <div class="flex items-center space-x-2">
                                                    <i class="las la-image text-gray-700"></i>
                                                    <span class="text-gray-700 text-sm">Generate Image</span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Side: Action Buttons -->
                        <div class="flex flex-col space-y-2">
                            <button type="submit" id="maximized-send-button" 
                                    class="bg-purple-600 hover:bg-purple-700 text-white p-3 rounded-lg font-medium transition-colors flex items-center justify-center min-w-[52px] min-h-[52px]">
                                <i class="las la-paper-plane text-xl"></i>
                            </button>
                            
                            <button type="button" id="maximized-stop-button" 
                                    class="hidden bg-red-600 hover:bg-red-700 text-white p-3 rounded-lg font-medium transition-colors flex items-center justify-center min-w-[52px] min-h-[52px]">
                                <i class="las la-stop text-xl"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Helper Text -->
                    <div class="text-gray-500 text-xs mt-1 px-1">
                        Press Enter to send, Shift+Enter for new line
                    </div>
                </form>
            `;
            document.body.appendChild(maximizedInput);
            
            // Initialize maximized input handlers
            initializeMaximizedInputHandlers(modelId);
            
            // Change icon to minimize
            const icon = btn.querySelector('i');
            icon.classList.remove('la-expand');
            icon.classList.add('la-compress');
            btn.title = 'Exit Full Screen';
            
            // Scroll to bottom of conversation
            const conversationDiv = panel.querySelector('.model-response');
            if (conversationDiv) {
                setTimeout(() => {
                    conversationDiv.scrollTop = conversationDiv.scrollHeight;
                }, 100);
            }
            
            // Focus on input
            setTimeout(() => {
                const input = document.getElementById('maximized-message-input');
                if (input) input.focus();
            }, 100);
            
            // Add backdrop click handler
            setTimeout(() => {
                modelsContainer.addEventListener('click', handleBackdropClick);
            }, 100);
        }

        // ✅ ENHANCED: Minimize model panel function
        function minimizeModelPanel(panel, btn) {
            // Show all panels
            const allPanels = document.querySelectorAll('.model-panel');
            allPanels.forEach(p => {
                p.classList.remove('hidden-panel');
            });
            
            // Minimize this panel
            panel.classList.remove('maximized');
            modelsContainer.classList.remove('has-maximized');
            
            // Show main chat input
            const mainChatForm = document.getElementById('compare-form');
            mainChatForm.classList.remove('hidden-on-maximize');
            
            // Remove maximized header overlay
            const headerOverlay = document.getElementById('maximized-header-overlay');
            if (headerOverlay) {
                headerOverlay.remove();
            }
            
            // Remove maximized chat input
            const maximizedInput = document.getElementById('maximized-chat-input');
            if (maximizedInput) {
                maximizedInput.remove();
            }
            
            // Change icon back to maximize
            const icon = btn.querySelector('i');
            icon.classList.remove('la-compress');
            icon.classList.add('la-expand');
            btn.title = 'Maximize';
            
            // Remove backdrop click handler
            modelsContainer.removeEventListener('click', handleBackdropClick);
        }

        // ✅ NEW: Global function to minimize from overlay
        window.minimizeFromOverlay = function(modelId) {
            const panel = document.querySelector(`[data-model-id="${modelId}"]`);
            const btn = panel?.querySelector('.maximize-model-btn');
            if (panel && btn) {
                minimizeModelPanel(panel, btn);
            }
        };

        // ✅ NEW: Initialize maximized input handlers
        function initializeMaximizedInputHandlers(currentModelId) {
            const input = document.getElementById('maximized-message-input');
            const fileInput = document.getElementById('maximized-file-input');
            const attachmentPreview = document.getElementById('maximized-attachment-preview');
            const fileNameSpan = document.getElementById('maximized-file-name');
            const removeFileButton = document.getElementById('maximized-remove-file');
            const previewAttachmentBtn = document.getElementById('maximized-preview-attachment-btn');
            const form = document.getElementById('maximized-compare-form');
            const sendButton = document.getElementById('maximized-send-button');
            const stopButton = document.getElementById('maximized-stop-button');
            const optionsDropdownBtn = document.getElementById('maximized-options-dropdown-btn');
            const optionsDropdown = document.getElementById('maximized-options-dropdown');
            const createImageCheckbox = document.getElementById('maximized-create-image');
            const createImageLabel = document.getElementById('maximized-create-image-label');
            
            let maximizedAttachmentFile = null;
            
            // ✅ Check if current model supports image generation
            const supportsImageGen = modelSupportsImageGen(currentModelId);
            if (createImageLabel) {
                if (supportsImageGen) {
                    createImageLabel.classList.remove('opacity-50', 'cursor-not-allowed');
                    createImageLabel.title = 'Generate images with this model';
                    createImageCheckbox.disabled = false;
                } else {
                    createImageLabel.classList.add('opacity-50', 'cursor-not-allowed');
                    createImageLabel.title = 'This model does not support image generation';
                    createImageCheckbox.disabled = true;
                    createImageCheckbox.checked = false;
                }
            }
            
            // Auto-resize textarea
            input.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 200) + 'px';
            });
            
            // Options dropdown toggle
            optionsDropdownBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                optionsDropdown.classList.toggle('hidden');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!optionsDropdownBtn.contains(e.target) && !optionsDropdown.contains(e.target)) {
                    optionsDropdown.classList.add('hidden');
                }
            });
            
            // File input change
            fileInput.addEventListener('change', async function(e) {
                const file = e.target.files[0];
                if (file) {
                    maximizedAttachmentFile = file;
                    fileNameSpan.innerHTML = '';
                    
                    const fileType = file.type.toLowerCase();
                    const fileName = file.name;
                    const fileSize = (file.size / 1024).toFixed(2) + ' KB';
                    
                    if (fileType.startsWith('image/')) {
                        const img = document.createElement('img');
                        img.src = URL.createObjectURL(file);
                        img.onload = () => URL.revokeObjectURL(img.src);
                        img.className = 'max-h-20 max-w-32 rounded border';
                        fileNameSpan.appendChild(img);
                    } else {
                        const fileInfo = document.createElement('div');
                        fileInfo.className = 'flex items-center space-x-2';
                        
                        let icon = 'la-file';
                        if (fileType.includes('pdf')) icon = 'la-file-pdf';
                        else if (fileType.includes('word') || fileType.includes('document')) icon = 'la-file-word';
                        
                        const displayName = fileName.length > 30 ? fileName.substring(0, 27) + '...' : fileName;
                        
                        fileInfo.innerHTML = `
                            <i class="las ${icon} text-xl"></i>
                            <div>
                                <div class="font-medium text-sm" title="${fileName}">${displayName}</div>
                                <div class="text-xs opacity-75">${fileSize}</div>
                            </div>
                        `;
                        fileNameSpan.appendChild(fileInfo);
                    }
                    attachmentPreview.classList.remove('hidden');
                }
            });
            
            // Remove file
            removeFileButton.addEventListener('click', function() {
                fileInput.value = '';
                maximizedAttachmentFile = null;
                attachmentPreview.classList.add('hidden');
            });
            
            // Preview attachment
            previewAttachmentBtn.addEventListener('click', async function() {
                if (maximizedAttachmentFile) {
                    await openAttachmentPreview(maximizedAttachmentFile);
                }
            });
            
            // Form submission
            // Inside initializeMaximizedInputHandlers, find the form submission handler:
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                    
                const message = input.value.trim();
                if (!message) {
                    alert('Please enter a message.');
                    return;
                }

                const webSearchChecked = document.getElementById('maximized-web-search').checked;
                const createImageChecked = document.getElementById('maximized-create-image').checked;
                
                // Create FormData
                const formData = new FormData();
                formData.append('message', message);
                
                // ✅ Handle model selection based on optimization mode
                if (currentOptimizationMode === 'smart_all') {
                    formData.append('models', JSON.stringify(['smart_all_auto']));
                } else if (currentOptimizationMode === 'smart_same') {
                    // ✅ FIXED: For Smart (Same), send the first model from the provider
                    const provider = currentModelId.replace('_smart_panel', '');
                    const providerCheckbox = document.querySelector(`input.provider-checkbox[value="${provider}"]`);
                    const firstModel = providerCheckbox ? providerCheckbox.dataset.firstModel : currentModelId;
                    formData.append('models', JSON.stringify([firstModel]));
                } else {
                    formData.append('models', JSON.stringify([currentModelId]));
                }
                
                formData.append('web_search', webSearchChecked ? '1' : '0');
                formData.append('create_image', createImageChecked ? '1' : '0');
                formData.append('optimization_mode', currentOptimizationMode);
                
                if (currentConversationId) {
                    formData.append('conversation_id', currentConversationId);
                }
                
                if (maximizedAttachmentFile) {
                    formData.append('pdf', maximizedAttachmentFile);
                }
                
                // ... rest remains the same
                        
                // Disable inputs
                sendButton.classList.add('hidden');
                stopButton.classList.remove('hidden');
                input.disabled = true;
                optionsDropdownBtn.disabled = true;
                
                // Add message to conversation
                addMessageToConversation(currentModelId, 'user', message);
                updateModelStatus(currentModelId, 'waiting');
                
                // Clear form
                input.value = '';
                input.style.height = 'auto';
                fileInput.value = '';
                maximizedAttachmentFile = null;
                attachmentPreview.classList.add('hidden');

                // ✅ NEW: Auto-uncheck create image checkbox after submission
                document.getElementById('maximized-create-image').checked = false;
                document.getElementById('maximized-web-search').checked = false; // Optional: also uncheck web search
                
                // Send request
                try {
                    abortController = new AbortController();
                    
                    const response = await fetch('{{ route("chat.multi-compare") }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        signal: abortController.signal
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();
                    let buffer = '';
                    
                    function readStream() {
                        return reader.read().then(({ done, value }) => {
                            if (done) {
                                resetMaximizedUI();
                                return;
                            }
                            
                            buffer += decoder.decode(value, { stream: true });
                            const lines = buffer.split('\n');
                            buffer = lines.pop();
                            
                            lines.forEach(line => {
                                if (line.trim() && line.startsWith('data: ')) {
                                    const data = line.slice(6);
                                    if (data === '[DONE]') {
                                        resetMaximizedUI();
                                        return;
                                    }
                                    
                                    try {
                                        const parsed = JSON.parse(data);
                                        handleStreamMessage(parsed);
                                    } catch (e) {
                                        console.error('Error parsing JSON:', e, data);
                                    }
                                }
                            });
                            
                            return readStream();
                        });
                    }
                    
                    readStream();
                    
                } catch (error) {
                    if (error.name === 'AbortError') {
                        console.log('Request aborted');
                    } else {
                        console.error('Error:', error);
                        alert('An error occurred: ' + error.message);
                    }
                    resetMaximizedUI();
                }
            });
            
            // Stop button
            stopButton.addEventListener('click', () => {
                if (abortController) {
                    abortController.abort();
                }
            });
            
            // Enter key handling
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    if (input.value.trim()) {
                        form.dispatchEvent(new Event('submit'));
                    }
                }
            });
            
            function resetMaximizedUI() {
                sendButton.classList.remove('hidden');
                stopButton.classList.add('hidden');
                input.disabled = false;
                optionsDropdownBtn.disabled = false;
                input.focus();
                abortController = null;
            }
        }

        // ✅ NEW: Handle backdrop click to minimize
        function handleBackdropClick(e) {
            // Only minimize if clicking on the backdrop (models-container itself), not on any child
            if (e.target === modelsContainer) {
                const maximizedPanel = document.querySelector('.model-panel.maximized');
                if (maximizedPanel) {
                    const btn = maximizedPanel.querySelector('.maximize-model-btn');
                    if (btn) {
                        minimizeModelPanel(maximizedPanel, btn);
                    }
                }
            }
        }

        // Stop button handler
        stopButton.addEventListener('click', () => {
            if (abortController) {
                abortController.abort();
            }
        });

        // Enter key handling
        messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (messageInput.value.trim()) {
                    compareForm.dispatchEvent(new Event('submit'));
                }
            }
        });

        // ✅ NEW: ESC key to minimize maximized panel
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const maximizedPanel = document.querySelector('.model-panel.maximized');
                if (maximizedPanel) {
                    const btn = maximizedPanel.querySelector('.maximize-model-btn');
                    if (btn) {
                        minimizeModelPanel(maximizedPanel, btn);
                    }
                }
            }
        });

// ====== SELECTION MODE & BULK ACTIONS ======
let selectionMode = false;
let selectedConversations = new Set();

// Toggle selection mode
document.getElementById('toggle-select-mode').addEventListener('click', () => {
    selectionMode = !selectionMode;
    
    if (selectionMode) {
        enableSelectionMode();
    } else {
        disableSelectionMode();
    }
});

function enableSelectionMode() {
    selectionMode = true;
    selectedConversations.clear();
    
    // Update UI
    document.getElementById('bulk-actions-bar').classList.remove('hidden');
    document.getElementById('toggle-select-mode').classList.add('bg-purple-600', 'text-white', 'border-purple-600');
    
    // Reset select all checkbox
    document.getElementById('select-all-checkbox').checked = false;
    
    // Add checkboxes to all conversation items
    document.querySelectorAll('.conversation-item').forEach(item => {
        if (!item.querySelector('.conversation-checkbox')) {
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'conversation-checkbox mr-3 h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded cursor-pointer';
            checkbox.dataset.id = item.dataset.id;
            
            checkbox.addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent conversation from opening
            });
            
            checkbox.addEventListener('change', (e) => {
                handleCheckboxChange(item.dataset.id, checkbox.checked);
            });
            
            // Insert checkbox at the beginning
            const firstChild = item.querySelector('.flex');
            firstChild.insertBefore(checkbox, firstChild.firstChild);
        }
    });
    
    updateSelectedCount();
}

function disableSelectionMode() {
    selectionMode = false;
    selectedConversations.clear();
    
    // Update UI
    document.getElementById('bulk-actions-bar').classList.add('hidden');
    document.getElementById('toggle-select-mode').classList.remove('bg-purple-600', 'text-white', 'border-purple-600');
    
    // Remove all checkboxes
    document.querySelectorAll('.conversation-checkbox').forEach(checkbox => {
        checkbox.remove();
    });
    
    // Reset select all checkbox
    document.getElementById('select-all-checkbox').checked = false;
    
    updateSelectedCount();
}

function handleCheckboxChange(conversationId, isChecked) {
    if (isChecked) {
        selectedConversations.add(conversationId);
    } else {
        selectedConversations.delete(conversationId);
    }
    updateSelectedCount();
    updateSelectAllCheckbox();
}

function updateSelectedCount() {
    const count = selectedConversations.size;
    document.getElementById('bulk-selected-count').textContent = count; // ✅ CHANGED
    
    // Enable/disable bulk action buttons
    const bulkArchiveBtn = document.getElementById('bulk-archive-btn');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    
    if (count > 0) {
        bulkArchiveBtn.disabled = false;
        bulkDeleteBtn.disabled = false;
    } else {
        bulkArchiveBtn.disabled = true;
        bulkDeleteBtn.disabled = true;
    }
}

// Update select all checkbox state based on individual selections
function updateSelectAllCheckbox() {
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const allCheckboxes = document.querySelectorAll('.conversation-checkbox');
    const totalConversations = allCheckboxes.length;
    const selectedCount = selectedConversations.size;
    
    if (selectedCount === 0) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    } else if (selectedCount === totalConversations) {
        selectAllCheckbox.checked = true;
        selectAllCheckbox.indeterminate = false;
    } else {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = true;
    }
}

// Select All functionality
document.getElementById('select-all-checkbox').addEventListener('change', function(e) {
    e.stopPropagation();
    const isChecked = this.checked;
    
    // Select or deselect all conversation checkboxes
    document.querySelectorAll('.conversation-checkbox').forEach(checkbox => {
        checkbox.checked = isChecked;
        handleCheckboxChange(checkbox.dataset.id, isChecked);
    });
});

// Prevent select all label from bubbling
document.querySelector('label[for="select-all-checkbox"]')?.addEventListener('click', (e) => {
    e.stopPropagation();
});

// Cancel selection
document.getElementById('cancel-select-btn').addEventListener('click', () => {
    disableSelectionMode();
});

// Bulk delete
document.getElementById('bulk-delete-btn').addEventListener('click', async () => {
    if (selectedConversations.size === 0) return;
    
    const count = selectedConversations.size;
    if (!confirm(`Are you sure you want to delete ${count} conversation(s)? This action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch('{{ route("bulk-delete-multi-compare-conversations") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({
                conversation_ids: Array.from(selectedConversations)
            })
        });
        
        if (!response.ok) {
            throw new Error('Failed to delete conversations');
        }
        
        const data = await response.json();
        showNotification(data.message, 'success');
        
        // Reload conversations
        loadConversations();
        disableSelectionMode();
        
    } catch (error) {
        console.error('Bulk delete error:', error);
        showNotification('Failed to delete conversations', 'error');
    }
});

// Bulk archive
document.getElementById('bulk-archive-btn').addEventListener('click', async () => {
    if (selectedConversations.size === 0) return;
    
    const count = selectedConversations.size;
    const currentFilter = document.getElementById('archive-filter').value;
    const isArchiving = currentFilter !== 'archived';
    
    const action = isArchiving ? 'archive' : 'unarchive';
    if (!confirm(`Are you sure you want to ${action} ${count} conversation(s)?`)) {
        return;
    }
    
    try {
        const response = await fetch('{{ route("bulk-archive-multi-compare-conversations") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({
                conversation_ids: Array.from(selectedConversations),
                archive: isArchiving
            })
        });
        
        if (!response.ok) {
            throw new Error('Failed to archive conversations');
        }
        
        const data = await response.json();
        showNotification(data.message, 'success');
        
        // Reload conversations
        loadConversations();
        disableSelectionMode();
        
    } catch (error) {
        console.error('Bulk archive error:', error);
        showNotification('Failed to archive conversations', 'error');
    }
});

// ====== ARCHIVE FILTER ======
document.getElementById('archive-filter').addEventListener('change', function() {
    loadConversations();
});

// ====== UPDATE loadConversations FUNCTION ======
async function loadConversations() {
    try {
        const archiveFilter = document.getElementById('archive-filter').value;
        let showArchived = 'false';
        
        if (archiveFilter === 'archived') {
            showArchived = 'only';
        } else if (archiveFilter === 'all') {
            showArchived = 'all';
        }
        
        const response = await fetch(`{{ route("get-multi-compare-chats") }}?show_archived=${showArchived}`);
        const conversations = await response.json();
        
        if (conversations.length === 0) {
            conversationsList.innerHTML = `
                <div class="text-center text-gray-500 py-8">
                    <i class="las la-comments text-4xl mb-2"></i>
                    <p>No conversations yet</p>
                </div>
            `;
            return;
        }

        conversationsList.innerHTML = conversations.map(conv => {
            const mode = conv.optimization_mode || 'fixed';
            const modeLabels = {
                'fixed': 'Fixed',
                'smart_same': 'Smart (Same)',
                'smart_all': 'Smart (All)'
            };
            const modeLabel = modeLabels[mode] || 'Fixed';
            
            const modeColors = {
                'fixed': 'bg-gray-100 text-gray-700',
                'smart_same': 'bg-blue-100 text-blue-700',
                'smart_all': 'bg-purple-100 text-purple-700'
            };
            const modeColor = modeColors[mode] || 'bg-gray-100 text-gray-700';
            
            const isArchived = conv.archived || false;
            
            return `
                <div class="conversation-item bg-gray-50 hover:bg-gray-100 p-3 rounded-lg cursor-pointer transition-colors ${isArchived ? 'opacity-75' : ''}" 
                    data-id="${conv.id}">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="conversation-title font-medium text-gray-900 truncate" title="${escapeHtml(conv.title)}"> ${escapeHtml(conv.title)}
                                </h3>
                                <span class="text-xs ${modeColor} px-2 py-0.5 rounded font-semibold whitespace-nowrap">
                                    ${modeLabel}
                                </span>
                                ${isArchived ? '<span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded font-semibold whitespace-nowrap"><i class="las la-archive"></i> Archived</span>' : ''}
                            </div>
                            <p class="text-xs text-gray-500 mt-1">${new Date(conv.updated_at).toLocaleDateString()}</p>
                            <div class="flex flex-wrap gap-1 mt-2">
                                ${conv.selected_models.map(model => `
                                    <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded">${model.split('-')[0]}</span>
                                `).join('')}
                            </div>
                        </div>
                        <div class="conversation-actions-menu ml-2">
                            <button class="conversation-menu-button text-gray-400 hover:text-gray-600" 
                                    data-id="${conv.id}"
                                    title="Actions">
                                <i class="las la-ellipsis-v text-lg"></i>
                            </button>
                            <div class="conversation-actions-dropdown" data-id="${conv.id}">
                                <div class="conversation-action-item archive-action archive-conversation-btn" 
                                    data-id="${conv.id}"
                                    data-archived="${isArchived}">
                                    <i class="las ${isArchived ? 'la-box-open' : 'la-archive'}"></i>
                                    <span>${isArchived ? 'Unarchive' : 'Archive'}</span>
                                </div>
                                <div class="conversation-action-item edit-action edit-conversation-btn" 
                                    data-id="${conv.id}">
                                    <i class="las la-edit"></i>
                                    <span>Edit Title</span>
                                </div>
                                <div class="conversation-action-item delete-action delete-conversation-btn" 
                                    data-id="${conv.id}">
                                    <i class="las la-trash"></i>
                                    <span>Delete</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        // Re-enable selection mode if it was active
        if (selectionMode) {
            enableSelectionMode();
        }
    } catch (error) {
        console.error('Error loading conversations:', error);
    }
}

        async function loadConversation(conversationId) {
            try {
                const response = await fetch(`{{ url('/get-multi-compare-conversation') }}/${conversationId}`);
                const conversation = await response.json();

                if (!conversation || !conversation.messages || conversation.messages.length === 0) {
                    console.error('No conversation data or empty conversation');
                    alert('No conversation data available');
                    return;
                }

                currentConversationId = conversationId;
                const savedMode = conversation.optimization_mode || 'fixed';
                
                console.log('Loading conversation with mode:', savedMode);

                if (savedMode === 'fixed') {
                    // Find all models used
                    const modelsUsed = new Set();
                    conversation.messages.forEach(msg => {
                        if (msg.role === 'assistant' && msg.all_responses) {
                            Object.keys(msg.all_responses).forEach(modelId => modelsUsed.add(modelId));
                        }
                    });

                    // Check boxes for models used
                    document.querySelectorAll('.model-checkbox').forEach(cb => cb.checked = false);
                    modelsUsed.forEach(modelId => {
                        const cb = document.querySelector(`input.model-checkbox[value="${modelId}"]`);
                        if (cb) {
                            cb.checked = true;
                            console.log('✅ Checked model:', modelId);
                        }
                    });
                    
                    // ✅ CRITICAL: Pass skipAutoSelection=true to prevent unchecking
                    setOptimizationMode(savedMode, true, true);  // skipConfirmation=true, skipAutoSelection=true
                    
                    // ✅ CRITICAL: Explicitly call updateSelectedModels after setting mode
                    updateSelectedModels();
                    
                    // ✅ CRITICAL: Wait for DOM to update
                    await new Promise(r => setTimeout(r, 100));
                    
                    console.log('✅ Fixed mode setup complete', {
                        modelsUsed: Array.from(modelsUsed),
                        selectedModels: selectedModels.map(m => m.model)
                    });

                    // Initialize conversation history
                    conversationHistory = {};
                    selectedModels.forEach(modelPanel => {
                        conversationHistory[modelPanel.model] = [];
                    });

                    // Group messages into exchanges
                    const exchanges = [];
                    let currentExchange = null;
                    conversation.messages.forEach(msg => {
                        if (msg.role === 'user') {
                            if (currentExchange) exchanges.push(currentExchange);
                            currentExchange = { userMessage: msg.content, assistantResponses: {}, attachment: msg.attachment };
                        }
                        else if (msg.role === 'assistant' && currentExchange) {
                            currentExchange.assistantResponses = msg.all_responses || {};
                        }
                    });
                    if (currentExchange) exchanges.push(currentExchange);

                    // Display exchanges in each model's panel
                    selectedModels.forEach(panelModel => {
                        const convDiv = document.getElementById(`conversation-${panelModel.model}`);
                        if (!convDiv) return;
                        convDiv.innerHTML = '';

                        exchanges.forEach((exchange, exchangeIndex) => {
                            const resp = exchange.assistantResponses[panelModel.model];
                            if (!resp) return;

                            const entryDiv = document.createElement('div');
                            entryDiv.className = 'conversation-entry';
                            const userMsgId = `user-msg-${panelModel.model}-${exchangeIndex}`;
                            
                            // ✅ User message - use consistent helper
                            const userMessageDiv = createUserMessageHTML(exchange.userMessage, userMsgId, null, exchange.attachment);
                            // Extract the user-prompt and actions from the helper's output
                            const userPromptFromHelper = userMessageDiv.querySelector('.user-prompt');
                            const actionsFromHelper = userMessageDiv.querySelector('.message-actions');
                            entryDiv.appendChild(userPromptFromHelper);
                            entryDiv.appendChild(actionsFromHelper);

                            // Assistant response
                            const assistantMsgId = `assistant-msg-${panelModel.model}-${exchangeIndex}`;
                            const assistantDiv = document.createElement('div');
                            assistantDiv.className = 'assistant-response';
                            assistantDiv.dataset.messageId = assistantMsgId;
                            assistantDiv.dataset.model = panelModel.model;
                            
                            const respDiv = document.createElement('div');
                            respDiv.className = 'message-content';
                            if (isImageURL(resp)) {
                                const imgContainer = document.createElement('div');
                                imgContainer.className = 'my-4';
                                const img = document.createElement('img');
                                img.src = resp;
                                img.alt = 'Generated image';
                                img.className = 'rounded-lg max-w-full h-auto shadow-md cursor-pointer';
                                img.onclick = () => openImageModal(resp, 'Generated image');
                                imgContainer.appendChild(img);
                                respDiv.appendChild(imgContainer);
                            } else {
                                respDiv.setAttribute('data-needs-processing', 'true');
                                respDiv.textContent = resp;
                            }
                            assistantDiv.appendChild(respDiv);
                            assistantDiv.appendChild(createMessageActions(assistantMsgId, true, panelModel.model));
                            
                            entryDiv.appendChild(assistantDiv);
                            convDiv.appendChild(entryDiv);

                            conversationHistory[panelModel.model].push({ 
                                prompt: exchange.userMessage, 
                                response: resp 
                            });
                        });

                        convDiv.querySelectorAll('[data-needs-processing="true"]').forEach(processMessageContent);
                        convDiv.scrollTop = convDiv.scrollHeight;
                    });

                } else if (savedMode === 'smart_same') {
                    // ========== SMART(SAME) MODE: One panel per provider, multiple models per provider ==========
                    
                    // 1. Find all providers and models used
                    const providerModelsMap = {}; // { provider: Set of models }
                    conversation.messages.forEach(msg => {
                        if (msg.role === 'assistant' && msg.all_responses) {
                            Object.keys(msg.all_responses).forEach(modelId => {
                                const cb = document.querySelector(`input.model-checkbox[value="${modelId}"]`);
                                if (cb) {
                                    const provider = cb.dataset.provider;
                                    if (!providerModelsMap[provider]) {
                                        providerModelsMap[provider] = new Set();
                                    }
                                    providerModelsMap[provider].add(modelId);
                                }
                            });
                        }
                    });

                    console.log('Providers and models used:', providerModelsMap);

                    // Check provider checkboxes
                    const providersUsed = Object.keys(providerModelsMap);
                    document.querySelectorAll('.provider-checkbox').forEach(cb => cb.checked = false);
                    providersUsed.forEach(provider => {
                        const cb = document.querySelector(`input.provider-checkbox[value="${provider}"]`);
                        if (cb) {
                            cb.checked = true;
                            console.log('✅ Checked provider:', provider);
                        }
                    });
                    
                    // ✅ CRITICAL: Pass skipAutoSelection=true
                    setOptimizationMode(savedMode, true, true);  // skipConfirmation=true, skipAutoSelection=true
                    
                    // ✅ CRITICAL: Explicitly update
                    updateSelectedModels();
                    
                    // ✅ CRITICAL: Wait for DOM
                    await new Promise(r => setTimeout(r, 100));

                    // 3. Create ONE panel per provider (using a stable panel ID)
                    selectedModels = providersUsed.map(provider => {
                        const providerCb = document.querySelector(`input.provider-checkbox[value="${provider}"]`);
                        return {
                            model: `${provider}_smart_panel`, // Stable panel ID
                            provider: provider,
                            displayName: `${provider.charAt(0).toUpperCase() + provider.slice(1)} (Smart Mode)`
                        };
                    });

                    generateModelPanels();
                    await new Promise(r => setTimeout(r, 100));

                    // 4. Initialize conversation history for each provider panel
                    conversationHistory = {};
                    selectedModels.forEach(panel => {
                        conversationHistory[panel.model] = [];
                    });

                    // 5. Group messages into exchanges
                    const exchanges = [];
                    let currentExchange = null;
                    conversation.messages.forEach(msg => {
                        if (msg.role === 'user') {
                            if (currentExchange) exchanges.push(currentExchange);
                            currentExchange = { userMessage: msg.content, assistantResponses: {}, attachment: msg.attachment };
                        }
                        else if (msg.role === 'assistant' && currentExchange) {
                            currentExchange.assistantResponses = msg.all_responses || {};
                        }
                    });
                    if (currentExchange) exchanges.push(currentExchange);

                    // 6. Display exchanges in each provider's panel
                    selectedModels.forEach(panelData => {
                        const convDiv = document.getElementById(`conversation-${panelData.model}`);
                        if (!convDiv) {
                            console.error('Panel not found for:', panelData.model);
                            return;
                        }
                        convDiv.innerHTML = '';

                        const provider = panelData.provider;
                        const modelsForProvider = Array.from(providerModelsMap[provider] || []);

                        console.log(`Loading ${provider} panel with models:`, modelsForProvider);

                        exchanges.forEach((exchange, exchangeIndex) => {
                            // Find the response from ANY model in this provider
                            let responseText = null;
                            let modelUsed = null;
                            
                            for (const modelId of modelsForProvider) {
                                if (exchange.assistantResponses[modelId]) {
                                    responseText = exchange.assistantResponses[modelId];
                                    modelUsed = modelId;
                                    break;
                                }
                            }

                            if (!responseText) return; // No response from this provider for this exchange

                            const entryDiv = document.createElement('div');
                            entryDiv.className = 'conversation-entry';
                            const userMsgId = `user-msg-${panelData.model}-${exchangeIndex}`;
                            
                            // ✅ User message - use consistent helper
                            const userMessageDiv = createUserMessageHTML(exchange.userMessage, userMsgId, null, exchange.attachment);
                            // Extract the user-prompt and actions from the helper's output
                            const userPromptFromHelper = userMessageDiv.querySelector('.user-prompt');
                            const actionsFromHelper = userMessageDiv.querySelector('.message-actions');
                            entryDiv.appendChild(userPromptFromHelper);
                            entryDiv.appendChild(actionsFromHelper);

                            // ✅ Assistant response WITHOUT model indicator
                            const assistantMsgId = `assistant-msg-${panelData.model}-${exchangeIndex}`;
                            const assistantDiv = document.createElement('div');
                            assistantDiv.className = 'assistant-response';
                            assistantDiv.dataset.messageId = assistantMsgId;
                            assistantDiv.dataset.model = panelData.model;

                            const respDiv = document.createElement('div');
                            respDiv.className = 'message-content';
                            if (isImageURL(responseText)) {
                                const imgContainer = document.createElement('div');
                                imgContainer.className = 'my-4';
                                const img = document.createElement('img');
                                img.src = responseText;
                                img.alt = 'Generated image';
                                img.className = 'rounded-lg max-w-full h-auto shadow-md cursor-pointer';
                                img.onclick = () => openImageModal(responseText, 'Generated image');
                                imgContainer.appendChild(img);
                                respDiv.appendChild(imgContainer);
                            } else {
                                respDiv.setAttribute('data-needs-processing', 'true');
                                respDiv.textContent = responseText;
                            }

                            assistantDiv.appendChild(respDiv);
                            assistantDiv.appendChild(createMessageActions(assistantMsgId, true, panelData.model));
                            
                            entryDiv.appendChild(assistantDiv);
                            convDiv.appendChild(entryDiv);

                            conversationHistory[panelData.model].push({ 
                                prompt: exchange.userMessage, 
                                response: responseText // ✅ Clean response without model prefix
                            });
                        });

                        convDiv.querySelectorAll('[data-needs-processing="true"]').forEach(processMessageContent);
                        convDiv.scrollTop = convDiv.scrollHeight;
                    });

                } else if (savedMode === 'smart_all') {
                    // ========== SMART(ALL) MODE: Single panel with best model per message ==========
                    
                    setOptimizationMode('smart_all', true); // ✅ Skip confirmation when loading
                    
                    selectedModels = [{
                        model: 'smart_all_auto',
                        provider: 'smart_all',
                        displayName: 'Smart Mode'
                    }];

                    generateModelPanels();
                    await new Promise(r => setTimeout(r, 100));

                    conversationHistory = { 'smart_all_auto': [] };

                    const convDiv = document.querySelector('[data-model-id="smart_all_auto"] .model-conversation');
                    if (!convDiv) {
                        console.error('Smart Mode conversation container not found');
                        return;
                    }

                    convDiv.innerHTML = '';

                    const exchanges = [];
                    let currentExchange = null;
                    conversation.messages.forEach(msg => {
                        if (msg.role === 'user') {
                            if (currentExchange) exchanges.push(currentExchange);
                            currentExchange = { userMessage: msg.content, assistantResponses: {}, attachment: msg.attachment };
                        }
                        else if (msg.role === 'assistant' && currentExchange) {
                            currentExchange.assistantResponses = msg.all_responses || {};
                        }
                    });
                    if (currentExchange) exchanges.push(currentExchange);

                    exchanges.forEach((exchange, exchangeIndex) => {
                        const entryDiv = document.createElement('div');
                        entryDiv.className = 'conversation-entry';
                        const userMsgId = `user-msg-smart_all_auto-${exchangeIndex}`;

                        // ✅ User message - use consistent helper
                        const userMessageDiv = createUserMessageHTML(exchange.userMessage, userMsgId, null, exchange.attachment);
                        // Extract the user-prompt and actions from the helper's output
                        const userPromptFromHelper = userMessageDiv.querySelector('.user-prompt');
                        const actionsFromHelper = userMessageDiv.querySelector('.message-actions');
                        entryDiv.appendChild(userPromptFromHelper);
                        entryDiv.appendChild(actionsFromHelper);

                        const responses = Object.entries(exchange.assistantResponses);
                        if (responses.length > 0) {
                            const [modelUsed, responseText] = responses[0];
                            
                            const assistantMsgId = `assistant-msg-smart_all_auto-${exchangeIndex}`;
                            const assistantDiv = document.createElement('div');
                            assistantDiv.className = 'assistant-response';
                            assistantDiv.dataset.messageId = assistantMsgId;
                            assistantDiv.dataset.model = 'smart_all_auto';

                            const respDiv = document.createElement('div');
                            respDiv.className = 'message-content';
                            if (isImageURL(responseText)) {
                                const imgContainer = document.createElement('div');
                                imgContainer.className = 'my-4';
                                const img = document.createElement('img');
                                img.src = responseText;
                                img.alt = 'Generated image';
                                img.className = 'rounded-lg max-w-full h-auto shadow-md cursor-pointer';
                                img.onclick = () => openImageModal(responseText, 'Generated image');
                                imgContainer.appendChild(img);
                                respDiv.appendChild(imgContainer);
                            } else {
                                respDiv.setAttribute('data-needs-processing', 'true');
                                respDiv.textContent = responseText;
                            }

                            assistantDiv.appendChild(respDiv);
                            assistantDiv.appendChild(createMessageActions(assistantMsgId, true, 'smart_all_auto'));
                            
                            entryDiv.appendChild(assistantDiv);

                            conversationHistory['smart_all_auto'].push({ 
                                prompt: exchange.userMessage, 
                                response: responseText // ✅ Clean response without model prefix
                            });
                        }

                        convDiv.appendChild(entryDiv);
                    });

                    convDiv.querySelectorAll('[data-needs-processing="true"]').forEach(processMessageContent);
                    convDiv.scrollTop = convDiv.scrollHeight;
                }

                sidebar.classList.add('sidebar-hidden');
                sidebar.classList.remove('sidebar-visible');

            } catch (err) {
                console.error('Error loading conversation:', err);
                alert('Failed to load conversation');
            }
        }

        // ✅ Helper function to create attachment badge - ULTRA COMPACT WITH IMAGE THUMBNAILS AND DOWNLOADABLE NAMES
        function createAttachmentBadge(attachment) {
            const attachmentBadge = document.createElement('div');
            
            // Check if it's an image type
            const imageTypes = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'];
            const isImage = imageTypes.includes(attachment.type?.toLowerCase());
            
            if (isImage) {
                // For images - show thumbnail with filename and preview button
                attachmentBadge.className = 'mt-2 flex items-center gap-2';
                attachmentBadge.innerHTML = `
                    <img src="${attachment.url}" 
                        alt="${attachment.name}" 
                        class="w-12 h-12 object-cover rounded cursor-pointer hover:opacity-80 transition border border-white/30"
                        onclick="openImageModal('${attachment.url}', '${attachment.name}')"
                        title="Click to view full size">
                    <a href="${attachment.url}" 
                    download="${attachment.name}"
                    class="text-xs text-dark/70 hover:text-dark flex-1 truncate underline decoration-dotted hover:decoration-solid transition-all" 
                    title="Click to download: ${attachment.name}"
                    onclick="event.stopPropagation()">
                        ${attachment.name}
                    </a>
                    <button class="text-dark/70 hover:text-dark transition-colors flex items-center justify-center p-0 m-0 w-5 h-5" 
                            onclick="openImageModal('${attachment.url}', '${attachment.name}')"
                            title="View image">
                        <i class="las la-eye text-sm"></i>
                    </button>
                `;
            } else {
                // For documents - icon with filename and preview button
                attachmentBadge.className = 'mt-2 flex items-center gap-2 text-xs text-dark/80';
                
                let icon = 'la-paperclip';
                if (attachment.type === 'pdf') icon = 'la-file-pdf';
                else if (['doc', 'docx'].includes(attachment.type)) icon = 'la-file-word';
                
                const displayName = attachment.name.length > 30 
                    ? attachment.name.substring(0, 27) + '...' 
                    : attachment.name;
                
                attachmentBadge.innerHTML = `
                    <i class="las ${icon}"></i>
                    <a href="${attachment.url}" 
                    download="${attachment.name}"
                    class="flex-1 truncate hover:text-dark underline decoration-dotted hover:decoration-solid transition-all" 
                    title="Click to download: ${attachment.name}"
                    onclick="event.stopPropagation()">
                        ${displayName}
                    </a>
                    <button class="text-dark/60 hover:text-dark transition-colors flex items-center justify-center p-0 m-0 w-5 h-5" 
                            onclick="previewAttachmentFromUrl('${attachment.url}', '${attachment.name}', '${attachment.type}')"
                            title="Preview">
                        <i class="las la-eye text-sm"></i>
                    </button>
                `;
            }
            
            return attachmentBadge;
        }

        // Helper function to create message actions
        function createMessageActions(messageId, isAssistant, modelId = null) {
            const actionsDiv = document.createElement('div');
            actionsDiv.className = 'message-actions';
            
            let actionsHTML = `
                <button class="message-action-btn copy-msg-btn" data-message-id="${messageId}" title="Copy ${isAssistant ? 'Response' : 'Message'}">
                    <i class="las la-copy"></i>
                </button>
                <button class="message-action-btn read-msg-btn" data-message-id="${messageId}" title="Read Aloud">
                    <i class="las la-volume-up"></i>
                </button>
            `;
            
            if (isAssistant && modelId) {
                actionsHTML += `
                    <button class="message-action-btn regenerate-msg-btn" data-message-id="${messageId}" data-model="${modelId}" title="Regenerate Response">
                        <i class="las la-redo-alt"></i>
                    </button>
                `;
            }
            
            actionsHTML += `
                <button class="message-action-btn translate-msg-btn" data-message-id="${messageId}" title="Translate ${isAssistant ? 'Response' : 'Message'}">
                    <i class="las la-language"></i>
                </button>
            `;
            
            actionsDiv.innerHTML = actionsHTML;
            return actionsDiv;
        }

        // ✅ NEW: Global function to preview attachments from URL (for loaded conversations)
        window.previewAttachmentFromUrl = async function(url, name, type) {
            try {
                const response = await fetch(url);
                const blob = await response.blob();
                const file = new File([blob], name, { type: `application/${type}` });
                await openAttachmentPreview(file, url);
            } catch (error) {
                console.error('Error previewing attachment:', error);
                alert('Failed to load attachment preview');
            }
        };

        async function deleteConversation(conversationId) {
            try {
                await fetch(`{{ url('/delete-multi-compare-conversation') }}/${conversationId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    }
                });
                
                if (currentConversationId == conversationId) {
                    currentConversationId = null;
                    regenerateModelPanels();
                }
                
                loadConversations();
            } catch (error) {
                console.error('Error deleting conversation:', error);
                alert('Error deleting conversation');
            }
        }

        async function updateConversationTitle(conversationId, newTitle) {
            try {
                await fetch(`{{ url('/update-multi-compare-conversation-title') }}/${conversationId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({ title: newTitle })
                });
                loadConversations();
            } catch (error) {
                console.error('Error updating conversation title:', error);
                alert('Error updating conversation title');
            }
        }

        // Utility functions
        function addChatGPTLinkStyles(element) {
            const links = element.querySelectorAll('a');
            links.forEach(link => {
                link.classList.add('text-blue-600', 'hover:text-blue-800', 'hover:underline', 'transition-colors', 'duration-200');
                
                if (link.href && !link.href.startsWith(window.location.origin)) {
                    const icon = document.createElement('span');
                    icon.innerHTML = '&nbsp;↗';
                    icon.classList.add('inline-block', 'text-xs', 'align-super');
                    link.appendChild(icon);
                }
            });

            element.querySelectorAll('ul').forEach(ul => ul.classList.add('list-disc', 'pl-6', 'my-2', 'space-y-1'));
            element.querySelectorAll('ol').forEach(ol => ol.classList.add('list-decimal', 'pl-6', 'my-2', 'space-y-1'));
            element.querySelectorAll('li').forEach(li => li.classList.add('mb-1'));
            element.querySelectorAll('pre').forEach(pre => pre.classList.add('bg-gray-100', 'p-3', 'rounded', 'overflow-x-auto', 'my-2'));
            element.querySelectorAll('code:not(pre code)').forEach(code => code.classList.add('bg-gray-100', 'px-1', 'py-0.5', 'rounded', 'text-sm'));
            element.querySelectorAll('blockquote').forEach(blockquote => blockquote.classList.add('border-l-4', 'border-gray-300', 'pl-4', 'my-2', 'text-gray-600'));
        }

        function addCopyButtonsToCodeBlocks(container) {
            container.querySelectorAll('pre').forEach((preElement) => {
                if (preElement.querySelector('.copy-code-button')) return;
                
                const containerDiv = document.createElement('div');
                containerDiv.className = 'code-block-container relative';
                
                preElement.parentNode.insertBefore(containerDiv, preElement);
                containerDiv.appendChild(preElement);
                
                const copyButton = document.createElement('button');
                copyButton.className = 'copy-code-button';
                copyButton.textContent = 'Copy';
                copyButton.title = 'Copy to clipboard';
                containerDiv.appendChild(copyButton);
                
                const code = preElement.querySelector('code')?.innerText || preElement.innerText;
                
                copyButton.addEventListener('click', () => {
                    navigator.clipboard.writeText(code).then(() => {
                        copyButton.textContent = 'Copied!';
                        copyButton.classList.add('copied');
                        setTimeout(() => {
                            copyButton.textContent = 'Copy';
                            copyButton.classList.remove('copied');
                        }, 2000);
                    });
                });
            });
        }

        function processMessageContent(element) {
            let content = element.textContent || element.innerHTML;
            
            debugLog('Processing message content', {
                contentLength: content.length,
                contentPreview: content.substring(0, 100) + '...'
            });
            
            const chartRegex = /```chart\n([\s\S]*?)\n```/g;
            const chartMatches = [...content.matchAll(chartRegex)];
            
            debugLog('Chart detection', {
                chartBlocksFound: chartMatches.length
            });
            
            if (chartMatches.length > 0) {
                try {
                    let textContent = content;
                    chartMatches.forEach(match => {
                        textContent = textContent.replace(match[0], '');
                    });
                    textContent = textContent.trim();
                    
                    element.innerHTML = '';
                    
                    if (textContent) {
                        const textDiv = document.createElement('div');
                        textDiv.innerHTML = marked.parse(textContent, {
                            gfm: true,
                            breaks: true,
                            headerIds: false,
                            mangle: false
                        });
                        element.appendChild(textDiv);
                    }
                    
                    chartMatches.forEach((match, index) => {
                        try {
                            debugLog(`Processing chart ${index + 1}`);
                            
                            const chartData = JSON.parse(match[1]);
                            
                            debugLog(`Chart data parsed successfully for chart ${index + 1}`, chartData);
                            
                            const chartContainer = document.createElement('div');
                            chartContainer.className = 'chart-container';
                            
                            const canvas = document.createElement('canvas');
                            canvas.className = 'chart-canvas';
                            canvas.id = `chart-${Date.now()}-${Math.random().toString(36).substr(2, 9)}-${index}`;
                            
                            chartContainer.appendChild(canvas);
                            element.appendChild(chartContainer);
                            
                            debugLog(`Created chart container and canvas: ${canvas.id}`);
                            
                            setTimeout(() => {
                                renderChart(canvas, chartData);
                            }, 200);
                            
                        } catch (e) {
                            debugLog(`Error processing chart ${index + 1}`, {
                                error: e.message,
                                stack: e.stack
                            });
                            
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'debug-info';
                            errorDiv.innerHTML = `<strong>Chart Error:</strong> ${e.message}<br><pre style="white-space: pre-wrap;">${match[1]}</pre>`;
                            element.appendChild(errorDiv);
                        }
                    });
                    
                } catch (e) {
                    debugLog('Error in chart processing', {
                        error: e.message,
                        stack: e.stack
                    });
                    
                    element.innerHTML = marked.parse(content, {
                        gfm: true,
                        breaks: true,
                        headerIds: false,
                        mangle: false
                    });
                }
            } else {
                element.innerHTML = marked.parse(content, {
                    gfm: true,
                    breaks: true,
                    headerIds: false,
                    mangle: false
                });
            }

            addChatGPTLinkStyles(element);
            addCopyButtonsToCodeBlocks(element);
            
            if (window.MathJax && window.MathJax.typesetPromise) {
                window.MathJax.typesetPromise([element]).catch((err) => {
                    console.error('MathJax rendering error:', err);
                });
            }
        }

        function renderChart(canvas, chartData) {
            debugLog(`Starting chart render for canvas: ${canvas.id}`, {
                canvasExists: !!canvas,
                canvasId: canvas.id,
                chartData: chartData,
                chartType: chartData.type
            });
            
            if (!Chart) {
                debugLog('Chart.js not available!');
                return;
            }
            
            try {
                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    debugLog('Could not get canvas context');
                    return;
                }
                
                const existingChart = Chart.getChart(canvas);
                if (existingChart) {
                    debugLog('Destroying existing chart');
                    existingChart.destroy();
                }
                
                const config = {
                    type: chartData.type || 'bar',
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
                
                if (chartData.options) {
                    config.options = { ...config.options, ...chartData.options };
                }
                
                debugLog(`Creating chart with config`, config);
                
                const chart = new Chart(ctx, config);
                
                debugLog(`Chart created successfully!`, {
                    chartId: chart.id,
                    canvasId: canvas.id,
                    chartType: chart.config.type
                });
                
                return chart;
                
            } catch (error) {
                debugLog('Chart creation failed!', {
                    error: error.message,
                    stack: error.stack,
                    canvasId: canvas.id,
                    chartData: chartData
                });
                
                const container = canvas.parentElement;
                if (container) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'debug-info';
                    errorDiv.innerHTML = `<strong>Chart Render Error:</strong> ${error.message}`;
                    container.appendChild(errorDiv);
                }
            }
        }

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

        // Notification helper function
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-20 right-4 z-50 px-4 py-3 rounded-lg shadow-lg transition-all duration-300 ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 
                type === 'warning' ? 'bg-yellow-500' : 
                'bg-blue-500'
            } text-white`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Fade in
            setTimeout(() => {
                notification.style.opacity = '1';
            }, 10);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        // ✅ ENHANCED: Conversation Search Functionality with Backend Search
        const conversationSearchInput = document.getElementById('conversation-search');
        const clearSearchBtn = document.getElementById('clear-search');
        const noSearchResults = document.getElementById('no-search-results');
        let searchTimeout = null;

        // Helper function to escape regex special characters
        function escapeRegex(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        // Search conversations with debouncing
        conversationSearchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            
            // Show/hide clear button
            if (searchTerm) {
                clearSearchBtn.classList.remove('hidden');
            } else {
                clearSearchBtn.classList.add('hidden');
            }
            
            // Clear previous timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            // Debounce search (wait 300ms after user stops typing)
            searchTimeout = setTimeout(() => {
                searchConversations(searchTerm);
            }, 300);
        });

        // Clear search
        clearSearchBtn.addEventListener('click', function() {
            conversationSearchInput.value = '';
            clearSearchBtn.classList.add('hidden');
            searchConversations('');
        });

        // Search conversations (backend + frontend)
        async function searchConversations(searchTerm) {
            try {
                // Show loading state
                conversationsList.innerHTML = `
                    <div class="text-center text-gray-500 py-8">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
                        <p class="mt-2">Searching...</p>
                    </div>
                `;
                
                const response = await fetch('{{ route("search-multi-compare-conversations") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({ search: searchTerm })
                });
                
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Search failed:', response.status, errorText);
                    throw new Error(`Search failed: ${response.status}`);
                }
                
                const conversations = await response.json();
                console.log('Search results:', conversations.length, 'conversations found');
                
                // Display results
                displaySearchResults(conversations, searchTerm);
                
            } catch (error) {
                console.error('Search error:', error);
                conversationsList.innerHTML = `
                    <div class="text-center text-red-500 py-8">
                        <i class="las la-exclamation-circle text-4xl mb-2"></i>
                        <p>Search failed. Please try again.</p>
                        <p class="text-xs text-gray-600 mt-2">${error.message}</p>
                    </div>
                `;
            }
        }

        // Display search results
        function displaySearchResults(conversations, searchTerm) {
            console.log('Displaying results:', conversations.length, 'conversations');
            
            if (conversations.length === 0) {
                noSearchResults.classList.remove('hidden');
                conversationsList.classList.add('hidden');
                conversationsList.innerHTML = '';
                return;
            }
            
            noSearchResults.classList.add('hidden');
            conversationsList.classList.remove('hidden');
            
            conversationsList.innerHTML = conversations.map(conv => {
                // Get mode display name
                const mode = conv.optimization_mode || 'fixed';
                const modeLabels = {
                    'fixed': 'Fixed',
                    'smart_same': 'Smart (Same)',
                    'smart_all': 'Smart (All)'
                };
                const modeLabel = modeLabels[mode] || 'Fixed';
                
                // Mode color styling
                const modeColors = {
                    'fixed': 'bg-gray-100 text-gray-700',
                    'smart_same': 'bg-blue-100 text-blue-700',
                    'smart_all': 'bg-purple-100 text-purple-700'
                };
                const modeColor = modeColors[mode] || 'bg-gray-100 text-gray-700';
                
                // Highlight search term in title if present
                let displayTitle = escapeHtml(conv.title);
                if (searchTerm) {
                    try {
                        // ✅ FIXED: Properly escape regex special characters
                        const escapedSearchTerm = escapeRegex(escapeHtml(searchTerm));
                        const regex = new RegExp(`(${escapedSearchTerm})`, 'gi');
                        displayTitle = displayTitle.replace(regex, '<mark class="bg-yellow-200 px-1 rounded">$1</mark>');
                    } catch (e) {
                        console.error('Error highlighting search term:', e);
                        // If regex fails, just use the title as-is
                    }
                }
                
                return `
                    <div class="conversation-item bg-gray-50 hover:bg-gray-100 p-3 rounded-lg cursor-pointer transition-colors" 
                        data-id="${conv.id}">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="conversation-title font-medium text-gray-900 truncate">${displayTitle}</h3>
                                    <span class="text-xs ${modeColor} px-2 py-0.5 rounded font-semibold whitespace-nowrap">
                                        ${modeLabel}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">${new Date(conv.updated_at).toLocaleDateString()}</p>
                                <div class="flex flex-wrap gap-1 mt-2">
                                    ${conv.selected_models.map(model => `
                                        <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded">${model.split('-')[0]}</span>
                                    `).join('')}
                                </div>
                                ${searchTerm ? `
                                    <div class="mt-2 text-xs text-gray-600 italic flex items-center gap-1">
                                        <i class="las la-search"></i>
                                        <span>Match found in conversation</span>
                                    </div>
                                ` : ''}
                            </div>
                            <div class="conversation-actions-menu ml-2">
                                <button class="conversation-menu-button text-gray-400 hover:text-gray-600" 
                                        data-id="${conv.id}"
                                        title="Actions">
                                    <i class="las la-ellipsis-v text-lg"></i>
                                </button>
                                <div class="conversation-actions-dropdown" data-id="${conv.id}">
                                    <div class="conversation-action-item archive-action archive-conversation-btn" 
                                        data-id="${conv.id}"
                                        data-archived="${isArchived}">
                                        <i class="las ${isArchived ? 'la-box-open' : 'la-archive'}"></i>
                                        <span>${isArchived ? 'Unarchive' : 'Archive'}</span>
                                    </div>
                                    <div class="conversation-action-item edit-action edit-conversation-btn" 
                                        data-id="${conv.id}">
                                        <i class="las la-edit"></i>
                                        <span>Edit Title</span>
                                    </div>
                                    <div class="conversation-action-item delete-action delete-conversation-btn" 
                                        data-id="${conv.id}">
                                        <i class="las la-trash"></i>
                                        <span>Delete</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // ✅ Dashboard Navigation
        const gotoDashboardBtn = document.getElementById('goto-dashboard');
        gotoDashboardBtn.addEventListener('click', () => {
            window.location.href = '{{ route("dashboard") }}';
        });

    </script>

    <script>
        // ====== THREE-DOT MENU FUNCTIONALITY ======
        document.addEventListener('click', function(e) {
            // Toggle dropdown when three-dot button is clicked
            if (e.target.closest('.conversation-menu-button')) {
                e.stopPropagation();
                const button = e.target.closest('.conversation-menu-button');
                const dropdown = button.nextElementSibling;
                
                // Close all other dropdowns
                document.querySelectorAll('.conversation-actions-dropdown.show').forEach(d => {
                    if (d !== dropdown) {
                        d.classList.remove('show');
                    }
                });
                
                // Toggle current dropdown
                dropdown.classList.toggle('show');
                return;
            }
            
            // Close dropdown when clicking outside
            if (!e.target.closest('.conversation-actions-dropdown')) {
                document.querySelectorAll('.conversation-actions-dropdown.show').forEach(d => {
                    d.classList.remove('show');
                });
            }
            
            // Handle action clicks within dropdown
            if (e.target.closest('.conversation-action-item')) {
                const dropdown = e.target.closest('.conversation-actions-dropdown');
                if (dropdown) {
                    dropdown.classList.remove('show');
                }
            }
        });

        // Close dropdown when conversation is loaded
        document.addEventListener('DOMContentLoaded', function() {
            const originalLoadConversation = loadConversation;
            loadConversation = function(conversationId) {
                document.querySelectorAll('.conversation-actions-dropdown.show').forEach(d => {
                    d.classList.remove('show');
                });
                return originalLoadConversation(conversationId);
            };
        });
    </script>
</body>
</html>