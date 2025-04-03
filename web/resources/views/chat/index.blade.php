@extends('layouts.app')

@section('content')
<div class="container-fluid h-100">
    <div class="row justify-content-center h-100">
        <div class="col-md-3 col-lg-2 d-md-block d-none">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Cuộc trò chuyện</h5>
                </div>
                <div class="card-body p-2">
                    <ul class="nav flex-column nav-pills" id="conversation-list">
                        <li class="nav-item">
                            <a class="nav-link active d-flex align-items-center" href="#">
                                <i class="fas fa-plus-circle me-2"></i>
                                <span>Cuộc trò chuyện mới</span>
                            </a>
                        </li>
                        <!-- Các cuộc trò chuyện trước đây sẽ được thêm vào đây bằng JavaScript -->
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-9 col-lg-10">
            <div class="card h-100 d-flex flex-column">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-robot me-2"></i>Chatbot Kinh Tế</h5>
                        <button class="btn btn-sm btn-outline-light d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#mobile-conversations">
                            <i class="fas fa-history"></i> Lịch sử
                        </button>
                    </div>
                    <div class="collapse mt-2 d-md-none" id="mobile-conversations">
                        <ul class="nav nav-pills nav-fill" id="mobile-conversation-list">
                            <li class="nav-item">
                                <a class="nav-link active bg-white text-primary" href="#">
                                    <i class="fas fa-plus-circle"></i> Mới
                                </a>
                            </li>
                            <!-- Các cuộc trò chuyện trước đây sẽ được thêm vào đây bằng JavaScript -->
                        </ul>
                    </div>
                </div>
                <div class="card-body overflow-auto" id="chat-messages">
                    <div class="welcome-message text-center my-5">
                        <div class="mb-4">
                            <i class="fas fa-robot fa-4x text-primary"></i>
                        </div>
                        <h2>Chào mừng đến với Chatbot Kinh Tế!</h2>
                        <p class="lead">Tôi có thể trả lời các câu hỏi về kinh tế, tài chính và các vấn đề liên quan.</p>
                        <div class="row justify-content-center mt-4">
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100 suggestion-card">
                                            <div class="card-body">
                                                <h5 class="card-title"><i class="fas fa-chart-line me-2"></i>Dự báo kinh tế</h5>
                                                <p class="card-text">Hỏi về các dự báo kinh tế mới nhất và xu hướng thị trường.</p>
                                                <button class="btn btn-sm btn-outline-primary suggestion-btn" data-message="Dự báo kinh tế Việt Nam năm nay như thế nào?">Hỏi ngay</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100 suggestion-card">
                                            <div class="card-body">
                                                <h5 class="card-title"><i class="fas fa-money-bill-wave me-2"></i>Đầu tư</h5>
                                                <p class="card-text">Tìm hiểu về chiến lược đầu tư và cơ hội trong các thị trường khác nhau.</p>
                                                <button class="btn btn-sm btn-outline-primary suggestion-btn" data-message="Các kênh đầu tư an toàn hiện nay là gì?">Hỏi ngay</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100 suggestion-card">
                                            <div class="card-body">
                                                <h5 class="card-title"><i class="fas fa-university me-2"></i>Ngân hàng</h5>
                                                <p class="card-text">Tìm hiểu về lãi suất, vay vốn và các dịch vụ ngân hàng.</p>
                                                <button class="btn btn-sm btn-outline-primary suggestion-btn" data-message="Lãi suất ngân hàng hiện nay như thế nào?">Hỏi ngay</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100 suggestion-card">
                                            <div class="card-body">
                                                <h5 class="card-title"><i class="fas fa-rocket me-2"></i>Khởi nghiệp</h5>
                                                <p class="card-text">Tư vấn về khởi nghiệp, phát triển doanh nghiệp và chiến lược kinh doanh.</p>
                                                <button class="btn btn-sm btn-outline-primary suggestion-btn" data-message="Các bước khởi nghiệp cho người mới bắt đầu?">Hỏi ngay</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Các tin nhắn sẽ được thêm vào đây bằng JavaScript -->
                </div>
                <div class="card-footer p-0">
                    <div id="typing-indicator" class="px-3 py-2 d-none">
                        <div class="d-flex align-items-center text-muted">
                            <small><i class="fas fa-robot me-2"></i>Chatbot đang trả lời...</small>
                            <div class="typing-dots ms-2">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                    </div>
                    <form id="chat-form" class="d-flex align-items-end m-0 p-2 bg-light">
                        <div class="flex-grow-1 me-2">
                            <textarea id="message-input" class="form-control" rows="2" placeholder="Nhập câu hỏi của bạn..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" id="send-button">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Đảm bảo giao diện chat chiếm toàn bộ màn hình có sẵn */
    html, body {
        height: 100%;
    }
    
    .container-fluid {
        padding-top: 15px;
        padding-bottom: 15px;
        min-height: calc(100vh - 100px);
    }
    
    .card {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    /* Hiệu ứng cho các tin nhắn */
    .chat-message {
        margin-bottom: 20px;
        opacity: 0;
        transform: translateY(20px);
        animation: fadeIn 0.3s forwards;
    }
    
    @keyframes fadeIn {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Tin nhắn của người dùng */
    .chat-message.user {
        display: flex;
        justify-content: flex-end;
    }
    
    .chat-message.user .message-content {
        background-color: #007bff;
        color: white;
        border-radius: 18px 18px 0 18px;
        padding: 10px 15px;
        max-width: 80%;
    }
    
    /* Tin nhắn của bot */
    .chat-message.bot {
        display: flex;
    }
    
    .chat-message.bot .message-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: #f8f9fa;
        display: flex;
        justify-content: center;
        align-items: center;
        margin-right: 10px;
        color: #007bff;
    }
    
    .chat-message.bot .message-content {
        background-color: #f8f9fa;
        border-radius: 18px 18px 18px 0;
        padding: 10px 15px;
        max-width: 80%;
    }
    
    /* Markdown formatting in bot messages */
    .chat-message.bot .message-content p {
        margin-bottom: 0.5rem;
    }
    
    .chat-message.bot .message-content ul, 
    .chat-message.bot .message-content ol {
        margin-bottom: 0.5rem;
        padding-left: 1.5rem;
    }
    
    .chat-message.bot .message-content code {
        background-color: rgba(0,0,0,0.05);
        padding: 2px 4px;
        border-radius: 3px;
    }
    
    .chat-message.bot .message-content pre {
        background-color: #2d333b;
        color: #e6edf3;
        padding: 10px;
        border-radius: 6px;
        overflow-x: auto;
        margin-bottom: 0.5rem;
    }
    
    /* Animated typing indicator */
    .typing-dots {
        display: inline-flex;
    }
    
    .typing-dots span {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background-color: #adb5bd;
        margin: 0 2px;
        animation: typingAnimation 1.5s infinite ease-in-out;
    }
    
    .typing-dots span:nth-child(2) {
        animation-delay: 0.2s;
    }
    
    .typing-dots span:nth-child(3) {
        animation-delay: 0.4s;
    }
    
    @keyframes typingAnimation {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-4px);
        }
    }
    
    /* Gợi ý câu hỏi */
    .suggestion-card {
        transition: all 0.2s;
        cursor: pointer;
    }
    
    .suggestion-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    /* Chat input area */
    #message-input {
        resize: none;
        border-radius: 20px;
        padding: 10px 15px;
        transition: all 0.2s ease;
    }
    
    #message-input:focus {
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.2);
    }
    
    #send-button {
        border-radius: 50%;
        width: 40px;
        height: 40px;
        padding: 0;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    /* Conversation list */
    #conversation-list .nav-link,
    #mobile-conversation-list .nav-link {
        border-radius: 5px;
        margin-bottom: 5px;
        padding: 10px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: all 0.2s;
    }
    
    #conversation-list .nav-link:hover,
    #mobile-conversation-list .nav-link:hover {
        background-color: rgba(13, 110, 253, 0.1);
    }
    
    /* Responsive adjustments */
    @media (max-width: 767.98px) {
        .container-fluid {
            padding: 0;
            min-height: 100vh;
        }
        
        .card {
            border-radius: 0;
            height: 100vh !important;
        }
        
        #chat-messages {
            height: calc(100vh - 170px);
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
    const typingIndicator = document.getElementById('typing-indicator');
    const suggestionBtns = document.querySelectorAll('.suggestion-btn');
    
    let conversationId = Date.now(); // Mã cuộc trò chuyện
    let isProcessing = false; // Biến trạng thái đang xử lý
    
    // Khởi tạo bộ xử lý markdown
    marked.setOptions({
        breaks: true,
        gfm: true,
        headerIds: false,
    });
    
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
    
    // Xử lý nút gợi ý
    suggestionBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const message = this.getAttribute('data-message');
            messageInput.value = message;
            chatForm.dispatchEvent(new Event('submit'));
        });
    });
    
    // Xử lý khi nhấn Enter (gửi tin nhắn)
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event('submit'));
        }
    });
    
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
        
        // Hiển thị biểu tượng đang gõ
        typingIndicator.classList.remove('d-none');
        isProcessing = true;
        
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
            // Ẩn biểu tượng đang gõ
            typingIndicator.classList.add('d-none');
            isProcessing = false;
            
            // Hiển thị tin nhắn từ bot
            appendMessage(data.message, 'bot');
            
            // Cuộn xuống dưới
            scrollToBottom();
        })
        .catch(error => {
            console.error('Error:', error);
            typingIndicator.classList.add('d-none');
            isProcessing = false;
            
            // Hiển thị lỗi
            appendMessage('Xin lỗi, có lỗi xảy ra. Vui lòng thử lại sau.', 'bot');
        });
    }
    
    // Hàm thêm tin nhắn vào giao diện
    function appendMessage(message, sender) {
        const messageElement = document.createElement('div');
        messageElement.classList.add('chat-message', sender);
        
        if (sender === 'user') {
            messageElement.innerHTML = `
                <div class="message-content">${message}</div>
            `;
        } else {
            // Xử lý markdown cho tin nhắn của bot
            const formattedMessage = marked.parse(message);
            
            messageElement.innerHTML = `
                <div class="message-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">${formattedMessage}</div>
            `;
        }
        
        chatMessages.appendChild(messageElement);
        scrollToBottom();
    }
    
    // Hàm cuộn xuống tin nhắn mới nhất
    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});
</script>
@endpush 