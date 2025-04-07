@extends('layouts.app')

@section('content')
<div class="chat-wrapper">
    <!-- Sidebar -->
    <div class="chat-sidebar">
        <div class="sidebar-header">
            <button class="toggle-sidebar-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="new-chat-btn">
            <a href="#" class="btn-new-chat">
                <i class="fas fa-sync-alt"></i> New chat
            </a>
        </div>
        <div class="conversations">
            <!-- Danh sách cuộc trò chuyện sẽ được thêm vào đây -->
        </div>
    </div>

    <!-- Main chat area -->
    <div class="chat-main">
        <!-- Chat messages -->
        <div class="chat-messages" id="chat-messages">
            <div class="welcome-message">
                <h1>Tôi có thể giúp gì cho bạn?</h1>
            </div>
        </div>

        <!-- Chat input -->
        <div class="chat-input-container">
            <form id="chat-form">
                <div class="chat-input-wrapper">
                    <textarea id="message-input" placeholder="Nhập câu hỏi của bạn..." rows="1"></textarea>
                    <button type="submit" id="send-button">
                        <i class="fas fa-arrow-up"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Reset */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    /* Layout */
    .chat-wrapper {
        display: flex;
        height: calc(100vh - 64px); /* Trừ chiều cao của navbar */
        width: 100%;
        overflow: hidden;
    }

    /* Sidebar */
    .chat-sidebar {
        width: 260px;
        background-color: #f9f9f9;
        border-right: 1px solid #eaeaea;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .sidebar-header {
        padding: 16px;
        border-bottom: 1px solid #eaeaea;
    }

    .toggle-sidebar-btn {
        background: none;
        border: none;
        color: #666;
        cursor: pointer;
        font-size: 1.2rem;
    }

    .new-chat-btn {
        padding: 16px;
    }

    .btn-new-chat {
        display: flex;
        align-items: center;
        gap: 8px;
        background-color: #e6f7ff;
        color: #1890ff;
        text-decoration: none;
        padding: 10px 16px;
        border-radius: 4px;
        font-weight: 500;
        transition: background-color 0.2s;
    }

    .btn-new-chat:hover {
        background-color: #cceeff;
    }

    .conversations {
        flex: 1;
        overflow-y: auto;
        padding: 8px;
    }

    /* Main chat area */
    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        height: 100%;
        background-color: #fff;
    }

    /* Chat messages */
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
    }

    .welcome-message {
        margin: auto;
        text-align: center;
        max-width: 600px;
    }

    .welcome-message h1 {
        font-size: 24px;
        font-weight: 500;
        color: #333;
        margin-bottom: 16px;
    }

    /* Chat input */
    .chat-input-container {
        padding: 16px;
        border-top: 1px solid #eaeaea;
    }

    .chat-input-wrapper {
        display: flex;
        align-items: center;
        border: 1px solid #eaeaea;
        border-radius: 24px;
        padding: 8px 16px;
        background-color: #fff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    #message-input {
        flex: 1;
        border: none;
        outline: none;
        padding: 8px 0;
        resize: none;
        background: transparent;
        font-size: 15px;
    }
    
    #message-input:focus {
    outline: none !important;
    box-shadow: none !important;
    border: none !important;
    }

    #send-button {
        border: none;
        background-color: #1890ff;
        color: #fff;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        margin-left: 8px;
    }

    /* Chat messages */
    .chat-message {
        margin-bottom: 20px;
        max-width: 80%;
    }

    .chat-message.user {
        align-self: flex-end;
    }

    .chat-message.bot {
        align-self: flex-start;
    }

    .message-content {
        padding: 12px 16px;
        border-radius: 16px;
        background-color: #f0f0f0;
        display: inline-block;
    }

    .chat-message.user .message-content {
        background-color: #e6f7ff;
        color: #333;
    }

    /* Typing indicator */
    #typing-indicator {
        align-self: flex-start;
        margin-bottom: 20px;
        display: none;
    }

    .typing-dots {
        display: flex;
        align-items: center;
    }

    .typing-dots span {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: #999;
        margin: 0 2px;
        opacity: 0.6;
        animation: typing 1.2s infinite ease-in-out;
    }

    .typing-dots span:nth-child(1) {
        animation-delay: 0s;
    }

    .typing-dots span:nth-child(2) {
        animation-delay: 0.2s;
    }

    .typing-dots span:nth-child(3) {
        animation-delay: 0.4s;
    }

    @keyframes typing {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-5px); opacity: 1; }
    }

    /* Responsive */
    @media (max-width: 768px) {
        .chat-sidebar {
            position: fixed;
            left: -260px;
            transition: left 0.3s;
            z-index: 100;
        }

        .chat-sidebar.active {
            left: 0;
        }

        .sidebar-toggle-mobile {
            display: block;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 101;
        }
    }

    /* Additional styling for chat messages */
    .chat-message.user .message-content {
        background-color: #1890ff;
        color: white;
        border-radius: 18px 18px 0 18px;
        padding: 12px 16px;
        display: inline-block;
        max-width: 90%;
        word-wrap: break-word;
        margin-left: auto;
    }
    
    .chat-message.bot .message-content {
        background-color: #f1f1f1;
        color: #333;
        border-radius: 18px 18px 18px 0;
        padding: 12px 16px;
        display: inline-block;
        max-width: 90%;
        word-wrap: break-word;
    }
    
    .chat-message.bot.error .message-content {
        background-color: #fff2f0;
        border: 1px solid #ffccc7;
        color: #ff4d4f;
    }
    
    /* Typing indicator */
    .typing-indicator .message-content {
        display: flex;
        align-items: center;
        padding: 12px 16px;
    }
    
    .typing-indicator span {
        height: 8px;
        width: 8px;
        background-color: #888;
        border-radius: 50%;
        display: inline-block;
        margin: 0 2px;
        animation: typing-animation 1.4s infinite ease-in-out;
    }
    
    .typing-indicator span:nth-child(1) {
        animation-delay: 0s;
    }
    
    .typing-indicator span:nth-child(2) {
        animation-delay: 0.2s;
    }
    
    .typing-indicator span:nth-child(3) {
        animation-delay: 0.4s;
    }
    
    @keyframes typing-animation {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-5px);
        }
    }
    
    /* Disabled button styling */
    #send-button.disabled {
        background-color: #bfbfbf;
        cursor: not-allowed;
    }
    
    /* Code blocks in bot responses */
    .chat-message.bot pre {
        background-color: #f6f8fa;
        border-radius: 6px;
        padding: 12px;
        overflow-x: auto;
        margin: 8px 0;
    }
    
    .chat-message.bot code {
        font-family: monospace;
        font-size: 14px;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .chat-sidebar {
            position: fixed;
            left: -280px;
            top: 64px;
            bottom: 0;
            transition: left 0.3s ease;
            z-index: 10;
        }
        
        .chat-sidebar.open {
            left: 0;
        }
        
        .chat-wrapper {
            flex-direction: column;
        }
        
        .chat-main {
            width: 100%;
        }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chatForm = document.getElementById('chat-form');
        const messageInput = document.getElementById('message-input');
        const chatMessages = document.getElementById('chat-messages');
        const sendButton = document.getElementById('send-button');
        let isWaitingForResponse = false;
        
        // Auto-resize text area as user types
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Handle form submission
        chatForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const message = messageInput.value.trim();
            
            // Don't send empty messages or when already waiting for a response
            if (!message || isWaitingForResponse) {
                return;
            }
            
            // Display user message
            appendMessage(message, 'user');
            
            // Clear input and reset height
            messageInput.value = '';
            messageInput.style.height = 'auto';
            
            // Set loading state
            isWaitingForResponse = true;
            toggleSendButtonState(true);
            
            // Show typing indicator
            appendTypingIndicator();
            
            try {
                // Send message to server
                const response = await fetch('{{ route("chat.send") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ message })
                });
                
                // Remove typing indicator
                removeTypingIndicator();
                
                if (!response.ok) {
                    // Handle HTTP errors
                    const errorData = await response.json().catch(() => ({ message: 'Có lỗi trong quá trình kết nối với máy chủ' }));
                    appendErrorMessage(errorData.message || 'Có lỗi trong quá trình kết nối với máy chủ');
                    return;
                }
                
                const data = await response.json();
                
                if (data.success === false) {
                    // Handle application errors
                    appendErrorMessage(data.message || 'Có lỗi xảy ra khi xử lý tin nhắn của bạn');
                } else {
                    // Display bot response
                    appendMessage(data.message, 'bot');
                }
                
            } catch (error) {
                console.error('Error:', error);
                removeTypingIndicator();
                appendErrorMessage('Không thể kết nối với máy chủ. Vui lòng kiểm tra kết nối internet của bạn.');
            } finally {
                // Reset state
                isWaitingForResponse = false;
                toggleSendButtonState(false);
                messageInput.focus();
            }
        });
        
        // Add a message to the chat window
        function appendMessage(message, sender) {
            const messageElement = document.createElement('div');
            messageElement.className = `chat-message ${sender}`;
            
            const contentElement = document.createElement('div');
            contentElement.className = 'message-content';
            
            if (sender === 'bot') {
                // Process markdown for bot responses
                contentElement.innerHTML = marked.parse(message);
            } else {
                contentElement.textContent = message;
            }
            
            messageElement.appendChild(contentElement);
            chatMessages.appendChild(messageElement);
            
            // Scroll to bottom
            scrollToBottom();
        }
        
        // Add error message
        function appendErrorMessage(message) {
            const messageElement = document.createElement('div');
            messageElement.className = 'chat-message bot error';
            
            const contentElement = document.createElement('div');
            contentElement.className = 'message-content';
            contentElement.textContent = message;
            
            messageElement.appendChild(contentElement);
            chatMessages.appendChild(messageElement);
            
            // Scroll to bottom
            scrollToBottom();
        }
        
        // Add typing indicator
        function appendTypingIndicator() {
            const indicatorElement = document.createElement('div');
            indicatorElement.className = 'chat-message bot typing-indicator';
            indicatorElement.id = 'typing-indicator';
            
            const contentElement = document.createElement('div');
            contentElement.className = 'message-content';
            contentElement.innerHTML = '<span></span><span></span><span></span>';
            
            indicatorElement.appendChild(contentElement);
            chatMessages.appendChild(indicatorElement);
            
            // Scroll to bottom
            scrollToBottom();
        }
        
        // Remove typing indicator
        function removeTypingIndicator() {
            const indicator = document.getElementById('typing-indicator');
            if (indicator) {
                indicator.remove();
            }
        }
        
        // Toggle send button state
        function toggleSendButtonState(disabled) {
            sendButton.disabled = disabled;
            if (disabled) {
                sendButton.classList.add('disabled');
            } else {
                sendButton.classList.remove('disabled');
            }
        }
        
        // Scroll chat to bottom
        function scrollToBottom() {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Start with focus in the input
        messageInput.focus();
        
        // Handle "New chat" button clicks
        document.querySelector('.btn-new-chat').addEventListener('click', function(e) {
            e.preventDefault();
            
            // Clear chat messages except welcome message
            while (chatMessages.children.length > 1) {
                chatMessages.removeChild(chatMessages.lastChild);
            }
            
            // Focus on input
            messageInput.focus();
        });
    });
</script>
@endpush