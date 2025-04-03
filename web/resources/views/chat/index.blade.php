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
        <!-- Header with avatar -->
        <div class="chat-header">
            <div class="logo">
                <img src="{{ asset('images/chatbot-logo.png') }}" alt="ChatBot" class="logo-img">
                <span class="logo-text">ChatBot</span>
            </div>
            <div class="user-avatar-container">
                <div class="user-avatar" id="user-avatar">
                    @if(Auth::user()->profile_photo_path)
                        <img src="{{ asset(Auth::user()->profile_photo_path) }}" alt="{{ Auth::user()->name }}">
                    @else
                        <div class="avatar-placeholder">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
                    @endif
                </div>
                <div class="avatar-dropdown" id="avatar-dropdown">
                    <div class="dropdown-header">
                        <div class="user-info">
                            <span class="user-name">{{ Auth::user()->name }}</span>
                            <span class="user-email">{{ Auth::user()->email }}</span>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="{{ route('profile.edit') }}" class="dropdown-item">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Log Out
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
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

    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        background-color: #fff;
        color: #333;
        line-height: 1.5;
    }

    /* Layout */
    .chat-wrapper {
        display: flex;
        height: 100vh;
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

    /* Header with avatar */
    .chat-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 20px;
        border-bottom: 1px solid #eaeaea;
        background-color: white;
    }

    .logo {
        display: flex;
        align-items: center;
    }

    .logo-img {
        height: 28px;
        width: auto;
        margin-right: 8px;
    }

    .logo-text {
        font-size: 18px;
        font-weight: 500;
        color: #333;
    }

    .user-avatar-container {
        position: relative;
    }

    .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: #f0f0f0;
        cursor: pointer;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #eaeaea;
    }

    .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .avatar-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #1890ff;
        color: white;
        font-weight: 500;
    }

    .avatar-dropdown {
        position: absolute;
        top: 45px;
        right: 0;
        background-color: white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
        width: 220px;
        z-index: 1000;
        display: none;
    }

    .avatar-dropdown.show {
        display: block;
    }

    .dropdown-header {
        padding: 12px 16px;
    }

    .user-info {
        display: flex;
        flex-direction: column;
    }

    .user-name {
        font-weight: 500;
        color: #333;
    }

    .user-email {
        font-size: 13px;
        color: #666;
        margin-top: 4px;
    }

    .dropdown-divider {
        height: 1px;
        background-color: #eaeaea;
        margin: 4px 0;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        padding: 10px 16px;
        color: #333;
        text-decoration: none;
        transition: background-color 0.2s;
        cursor: pointer;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
    }

    .dropdown-item i {
        margin-right: 10px;
        color: #666;
    }

    .dropdown-item:hover {
        background-color: #f5f5f5;
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
        max-width: 60%;
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
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const messageInput = document.getElementById('message-input');
    const chatForm = document.getElementById('chat-form');
    const chatMessages = document.getElementById('chat-messages');
    const typingIndicator = document.createElement('div');
    typingIndicator.id = 'typing-indicator';
    typingIndicator.className = 'chat-message bot';
    typingIndicator.innerHTML = `
        <div class="message-content">
            <div class="typing-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    `;
    chatMessages.appendChild(typingIndicator);
    
    // User avatar dropdown
    const userAvatar = document.getElementById('user-avatar');
    const avatarDropdown = document.getElementById('avatar-dropdown');
    
    userAvatar.addEventListener('click', function(e) {
        e.stopPropagation();
        avatarDropdown.classList.toggle('show');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!userAvatar.contains(e.target) && !avatarDropdown.contains(e.target)) {
            avatarDropdown.classList.remove('show');
        }
    });
    
    let conversationId = Date.now();
    let isProcessing = false;
    
    // Auto resize textarea
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    
    // Xử lý gửi tin nhắn
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (isProcessing) return;
        
        const message = messageInput.value.trim();
        if (message) {
            sendMessage(message);
            messageInput.value = '';
            messageInput.style.height = 'auto';
        }
    });
    
    // Xử lý khi nhấn Enter
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event('submit'));
        }
    });
    
    // Focus input khi mở trang
    messageInput.focus();
    
    // Hàm gửi tin nhắn
    function sendMessage(message) {
        if (isProcessing) return;
        
        // Xóa welcome message nếu còn
        const welcomeMessage = document.querySelector('.welcome-message');
        if (welcomeMessage) {
            welcomeMessage.remove();
        }
        
        // Hiển thị tin nhắn của người dùng
        appendMessage(message, 'user');
        
        // Hiển thị đang gõ
        typingIndicator.style.display = 'block';
        isProcessing = true;
        
        // Cuộn xuống dưới
        scrollToBottom();
        
        // Gửi request đến server
        fetch('{{ route("chat.send") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                message: message,
                conversation_id: conversationId
            })
        })
        .then(response => response.json())
        .then(data => {
            // Ẩn typing indicator
            typingIndicator.style.display = 'none';
            isProcessing = false;
            
            // Hiển thị tin nhắn từ bot
            appendMessage(data.message, 'bot');
            
            // Cuộn xuống dưới
            scrollToBottom();
        })
        .catch(error => {
            console.error('Error:', error);
            typingIndicator.style.display = 'none';
            isProcessing = false;
            appendMessage('Xin lỗi, có lỗi xảy ra. Vui lòng thử lại sau.', 'bot');
        });
    }
    
    // Hàm thêm tin nhắn vào giao diện
    function appendMessage(message, sender) {
        const messageElement = document.createElement('div');
        messageElement.classList.add('chat-message', sender);
        
        messageElement.innerHTML = `
            <div class="message-content">${message}</div>
        `;
        
        chatMessages.insertBefore(messageElement, typingIndicator);
        scrollToBottom();
    }
    
    // Hàm cuộn xuống tin nhắn mới nhất
    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Toggle sidebar on mobile
    const toggleSidebarBtn = document.querySelector('.toggle-sidebar-btn');
    const chatSidebar = document.querySelector('.chat-sidebar');
    
    if (toggleSidebarBtn && chatSidebar) {
        toggleSidebarBtn.addEventListener('click', function() {
            chatSidebar.classList.toggle('active');
        });
    }
});
</script>
@endpush 