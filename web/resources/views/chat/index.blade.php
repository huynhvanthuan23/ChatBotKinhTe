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
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const messageInput = document.getElementById('message-input');
    const chatForm = document.getElementById('chat-form');
    const chatMessages = document.getElementById('chat-messages');
    
    // Đảm bảo conversationId là chuỗi
    let conversationId = "session-" + Date.now().toString(); 
    let isProcessing = false;
    
    // Khởi tạo bộ xử lý markdown
    marked.setOptions({
        breaks: true,
        gfm: true,
        headerIds: false,
    });
    
    // Kiểm tra kết nối với API chatbot khi trang được tải
    testChatbotConnection();
    
    // Xử lý gửi tin nhắn
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (isProcessing) return; // Ngăn gửi khi đang xử lý
        
        const message = messageInput.value.trim();
        if (message) {
            sendMessage(message);
            messageInput.value = '';
        }
    });
    
    // Xử lý khi nhấn Enter (gửi tin nhắn)
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event('submit'));
        }
    });
    
    // Auto resize cho textarea
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
    
    // Hàm kiểm tra kết nối với chatbot API
    function testChatbotConnection() {
        fetch('/chat/test-connection')
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    // Hiện thông báo lỗi nếu không kết nối được
                    const errorMessage = document.createElement('div');
                    errorMessage.classList.add('error-message');
                    errorMessage.innerHTML = '<p>⚠️ ' + data.message + '</p>';
                    chatMessages.appendChild(errorMessage);
                }
            })
            .catch(error => {
                console.error('Error testing connection:', error);
            });
    }
    
    // Hàm gửi tin nhắn
    function sendMessage(message) {
        // Hiển thị tin nhắn người dùng
        appendMessage(message, 'user');
        
        // Hiển thị chỉ báo đang nhập
        showTypingIndicator();
        
        // Đánh dấu đang xử lý
        isProcessing = true;
        
        console.log('Sending to:', '/chat/send');
        console.log('Message payload:', JSON.stringify({
            message: message
        }));
        
        // Gọi API để lấy phản hồi
        fetch('/chat/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                message: message
            })
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            // Kiểm tra nếu response không okay
            if (!response.ok) {
                return response.json().then(errorData => {
                    throw new Error(errorData.message || 'Lỗi từ server');
                });
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            
            // Ẩn chỉ báo đang nhập
            hideTypingIndicator();
            
            // Kiểm tra phản hồi không rỗng
            if (data && data.message && data.message.trim() !== '') {
                // Hiển thị phản hồi từ chatbot
                appendMessage(data.message, 'bot');
            } else {
                // Phản hồi mặc định nếu không có nội dung
                appendMessage('Xin lỗi, tôi không thể trả lời câu hỏi này lúc này. Vui lòng thử lại sau.', 'bot');
            }
            
            // Đánh dấu đã xử lý xong
            isProcessing = false;
        })
        .catch(error => {
            console.error('Error details:', error);
            
            // Ẩn chỉ báo đang nhập
            hideTypingIndicator();
            
            // Hiển thị thông báo lỗi
            appendMessage('Đã xảy ra lỗi: ' + error.message, 'bot');
            
            // Đánh dấu đã xử lý xong
            isProcessing = false;
        });
    }
    
    // Hàm hiển thị tin nhắn
    function appendMessage(message, sender) {
        // Xóa welcome message nếu có tin nhắn đầu tiên
        if (chatMessages.querySelector('.welcome-message')) {
            chatMessages.querySelector('.welcome-message').remove();
        }
        
        // Tạo phần tử tin nhắn
        const messageElement = document.createElement('div');
        messageElement.classList.add('chat-message', sender);
        
        // Phân tích markdown nếu là tin nhắn từ bot
        if (sender === 'bot') {
            messageElement.innerHTML = `
                <div class="message-content">
                    ${marked.parse(message)}
                </div>
            `;
        } else {
            messageElement.innerHTML = `
                <div class="message-content">${message}</div>
            `;
        }
        
        // Thêm tin nhắn vào khung chat
        chatMessages.appendChild(messageElement);
        
        // Cuộn xuống tin nhắn mới nhất
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Hàm hiển thị chỉ báo đang nhập
    function showTypingIndicator() {
        let typingIndicator = document.getElementById('typing-indicator');
        
        if (!typingIndicator) {
            typingIndicator = document.createElement('div');
            typingIndicator.id = 'typing-indicator';
            typingIndicator.classList.add('chat-message', 'bot');
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
        }
        
        typingIndicator.style.display = 'block';
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Hàm ẩn chỉ báo đang nhập
    function hideTypingIndicator() {
        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) {
            typingIndicator.style.display = 'none';
        }
    }
    
    // Xử lý toggle sidebar trên mobile
    const toggleSidebarBtn = document.querySelector('.toggle-sidebar-btn');
    if (toggleSidebarBtn) {
        toggleSidebarBtn.addEventListener('click', function() {
            document.querySelector('.chat-sidebar').classList.toggle('active');
        });
    }
});
</script>
@endpush