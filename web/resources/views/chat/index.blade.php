@extends('layouts.app')

@section('content')
<div class="chat-wrapper">
    <!-- Sidebar -->
    <div class="chat-sidebar">
        <div class="sidebar-header">
            <button class="toggle-sidebar-btn">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        <div class="new-chat-btn">
            <a href="#" class="btn-new-chat">
                <i class="fas fa-plus"></i> <span class="btn-text">New chat</span>
            </a>
        </div>
        <div class="my-documents-btn">
            <a href="{{ route('documents.index') }}" class="btn-my-documents">
                <i class="fas fa-file-alt"></i> <span class="btn-text">Tài liệu của tôi</span>
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
                @if(isset($selectedDocumentIds) && !empty($selectedDocumentIds))
                <div class="selected-documents-info">
                    <div class="alert alert-info mt-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-info-circle me-2"></i> Bạn đang chat với {{ count($selectedDocumentIds) }} tài liệu đã chọn</span>
                            <a href="{{ route('chat') }}?clear=1" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-times me-1"></i> Bỏ chọn
                            </a>
                        </div>
                    </div>
                    <div class="mb-3 text-center">
                        <div class="p-3 bg-light border rounded">
                            <h5 class="text-primary"><i class="fas fa-lightbulb me-2"></i> Chế độ hỏi đáp tài liệu</h5>
                            <p>Hãy đặt câu hỏi liên quan đến nội dung của các tài liệu đã chọn.</p>
                            <p><strong>Các câu trả lời sẽ dựa trên thông tin từ tài liệu bạn đã chọn.</strong></p>
                            <p class="small mb-0 text-muted">Hệ thống sẽ tìm kiếm thông tin từ các tài liệu đã chọn để trả lời câu hỏi của bạn.</p>
                        </div>
                    </div>
                </div>
                @endif
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
        position: relative;
        transition: all 0.3s ease;
    }
    
    /* Sidebar collapsed state */
    .chat-wrapper.sidebar-collapsed .chat-sidebar {
        width: 60px;
        overflow: hidden;
    }
    
    .chat-wrapper.sidebar-collapsed .chat-main {
        width: calc(100% - 60px);
    }

    /* Hide elements in collapsed sidebar */
    .chat-wrapper.sidebar-collapsed .conversation-item,
    .chat-wrapper.sidebar-collapsed .conversation-title,
    .chat-wrapper.sidebar-collapsed .conversation-date,
    .chat-wrapper.sidebar-collapsed .delete-chat-btn,
    .chat-wrapper.sidebar-collapsed .conversations {
        display: none;
    }
    
    /* Keep visible and adjust styling */
    .chat-wrapper.sidebar-collapsed .btn-new-chat,
    .chat-wrapper.sidebar-collapsed .btn-my-documents {
        width: 40px;
        height: 40px;
        overflow: hidden;
        padding: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 0 auto;
    }
    
    .chat-wrapper.sidebar-collapsed .btn-new-chat i,
    .chat-wrapper.sidebar-collapsed .btn-my-documents i {
        margin-right: 0;
    }
    
    .chat-wrapper.sidebar-collapsed .new-chat-btn,
    .chat-wrapper.sidebar-collapsed .my-documents-btn {
        padding: 10px;
        display: flex;
        justify-content: center;
    }

    /* Sidebar */
    .chat-sidebar {
        width: 260px;
        background-color: #f9f9f9;
        border-right: 1px solid #eaeaea;
        display: flex;
        flex-direction: column;
        height: 100%;
        transition: all 0.3s ease;
    }

    .sidebar-header {
        padding: 16px;
        border-bottom: 1px solid #eaeaea;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .toggle-sidebar-btn {
        background: none;
        border: none;
        color: #666;
        cursor: pointer;
        font-size: 1.2rem;
        padding: 8px;
        border-radius: 4px;
        transition: all 0.2s;
        display: flex;
        justify-content: center;
        align-items: center;
        width: 40px;
        height: 40px;
    }
    
    .toggle-sidebar-btn:hover {
        background-color: #e6e6e6;
        color: #333;
    }

    .new-chat-btn, .my-documents-btn {
        padding: 16px;
    }

    .my-documents-btn {
        padding-top: 0;
    }

    .btn-new-chat, .btn-my-documents {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        text-decoration: none;
        padding: 10px 16px;
        border-radius: 4px;
        font-weight: 500;
        transition: background-color 0.2s;
    }

    .btn-new-chat {
        background-color: #e6f7ff;
        color: #1890ff;
    }

    .btn-my-documents {
        background-color: #f0f5ff;
        color: #2f54eb;
    }

    .btn-new-chat:hover {
        background-color: #cceeff;
    }

    .btn-my-documents:hover {
        background-color: #d6e4ff;
    }

    .conversations {
        flex: 1;
        overflow-y: auto;
        padding: 8px;
    }
    
    /* Conversation items in sidebar */
    .conversation-item {
        padding: 12px;
        border-radius: 4px;
        margin-bottom: 8px;
        cursor: pointer;
        position: relative;
        background-color: #fff;
        border: 1px solid #eaeaea;
        transition: all 0.2s;
    }
    
    .conversation-item:hover {
        background-color: #f5f5f5;
    }
    
    .conversation-item.active {
        background-color: #e6f7ff;
        border-color: #91d5ff;
    }
    
    .conversation-title {
        font-weight: 500;
        color: #333;
        margin-bottom: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        padding-right: 24px; /* Space for delete button */
    }
    
    .conversation-date {
        font-size: 12px;
        color: #888;
    }
    
    .delete-chat-btn {
        position: absolute;
        top: 8px;
        right: 8px;
        background: none;
        border: none;
        color: #ff4d4f;
        cursor: pointer;
        opacity: 0;
        transition: opacity 0.2s;
        font-size: 14px;
        padding: 4px;
        border-radius: 4px;
    }
    
    .conversation-item:hover .delete-chat-btn {
        opacity: 0.7;
    }
    
    .conversation-item:hover .delete-chat-btn:hover {
        opacity: 1;
        background-color: rgba(255, 77, 79, 0.1);
    }

    /* Main chat area */
    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        height: 100%;
        background-color: #fff;
        transition: all 0.3s ease;
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
        align-self: flex-start;
        display: flex;
    }

    .chat-message.user {
        align-self: flex-end;
        justify-content: flex-end;
    }

    .message-content {
        padding: 12px 16px;
        border-radius: 16px;
        background-color: #f0f0f0;
        display: inline-block;
        word-break: break-word;
        max-width: 100%;
    }

    /* Additional styling for chat messages */
    .chat-message.user .message-content {
        background-color: #1890ff;
        color: white;
        border-radius: 18px 18px 0 18px;
        padding: 12px 16px;
        display: inline-block;
        max-width: 100%;
        word-wrap: break-word;
    }
    
    .chat-message.bot .message-content {
        background-color: #f1f1f1;
        color: #333;
        border-radius: 18px 18px 18px 0;
        padding: 12px 16px;
        display: inline-block;
        max-width: 100%;
        word-wrap: break-word;
    }
    
    .chat-message.bot.error .message-content {
        background-color: #fff2f0;
        border: 1px solid #ffccc7;
        color: #ff4d4f;
    }

    /* Typing indicator */
    .typing-indicator {
        align-self: flex-start;
        margin-bottom: 20px;
        display: flex;
    }

    .typing-indicator .message-content {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        gap: 4px;
        min-width: 60px;
    }

    .typing-indicator span {
        height: 8px;
        width: 8px;
        background-color: #888;
        border-radius: 50%;
        display: inline-block;
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
            width: 280px;
            transition: left 0.3s ease;
            z-index: 1030;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .chat-sidebar.mobile-visible {
            left: 0;
        }
        
        .chat-wrapper {
            flex-direction: column;
        }

        .chat-main {
            width: 100%;
        }
        
        .mobile-toggle-btn {
            position: fixed;
            top: 74px;
            left: 10px;
            z-index: 1031;
            background-color: #fff;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            font-size: 16px;
            color: #333;
        }
        
        .mobile-toggle-btn:focus {
            outline: none;
        }
        
        .mobile-sidebar-overlay {
            display: none;
            position: fixed;
            top: 64px;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1025;
        }
        
        .mobile-sidebar-overlay.visible {
            display: block;
        }
        
        /* Make sure conversation items in sidebar are more touch friendly */
        .conversation-item {
            padding: 15px;
            margin-bottom: 10px;
        }
        
        .conversation-title {
            font-size: 15px;
        }
        
        .delete-chat-btn {
            opacity: 0.7;
            padding: 8px;
            font-size: 16px;
        }
    }

    .chat-wrapper.sidebar-collapsed .sidebar-header {
        padding: 10px 0;
        justify-content: center;
    }
    
    .chat-wrapper.sidebar-collapsed .toggle-sidebar-btn {
        transform: rotate(180deg);
    }

    /* Hide text in collapsed sidebar */
    .chat-wrapper.sidebar-collapsed .btn-text {
        display: none;
    }
    
    .btn-new-chat i {
        font-size: 14px;
        margin-right: 8px;
    }

    /* Markdown content in bot messages */
    .chat-message.bot .message-content p:first-child {
        margin-top: 0;
    }
    
    .chat-message.bot .message-content p:last-child {
        margin-bottom: 0;
    }
    
    .chat-message.bot .message-content ul,
    .chat-message.bot .message-content ol {
        margin-top: 8px;
        margin-bottom: 8px;
        padding-left: 20px;
    }
    
    .chat-message.bot .message-content a {
        color: #1890ff;
        text-decoration: none;
    }
    
    .chat-message.bot .message-content a:hover {
        text-decoration: underline;
    }

    /* Styles for citations */
    .message-citations {
        margin-top: 15px;
        padding-top: 10px;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
    }
    
    .citations-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: #555;
        margin-bottom: 8px;
    }
    
    .citations-list {
        padding-left: 20px;
        margin-bottom: 0;
    }
    
    .citations-list li {
        margin-bottom: 5px;
    }
    
    .citation-link {
        color: #0d6efd;
        text-decoration: none;
        font-size: 0.85rem;
        display: inline-block;
        padding: 3px 0;
        transition: all 0.2s ease;
    }
    
    .citation-link:hover {
        color: #0a58ca;
        text-decoration: underline;
    }
    
    /* Modal styles for citation preview */
    .citation-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1050;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    .citation-modal-content {
        background-color: #fff;
        border-radius: 8px;
        width: 90%;
        max-width: 900px;
        height: 85vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    }
    
    .citation-modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .citation-modal-actions {
        display: flex;
        align-items: center;
    }
    
    .citation-modal-body {
        flex: 1;
        position: relative;
        overflow: hidden;
    }
    
    .citation-loading {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        background-color: #fff;
    }
    
    .citation-iframe {
        width: 100%;
        height: 100%;
        border: none;
    }
    
    /* Styles for Word document content */
    .word-content-container {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        overflow-y: auto;
        background-color: #fff;
    }
    
    .word-content {
        max-width: 800px;
        margin: 0 auto;
        font-family: 'Segoe UI', Arial, sans-serif;
    }
    
    .word-header {
        color: #333;
    }
    
    .word-text {
        line-height: 1.6;
        font-size: 16px;
        color: #333;
        overflow-y: auto;
        max-height: 65vh;
    }
    
    .word-content-text {
        padding: 15px;
    }
    
    .highlighted-text {
        background-color: #ffff90;
        padding: 10px;
        border-radius: 4px;
        border-left: 4px solid #ffd700;
        margin: 10px 0;
        transition: background-color 0.3s ease;
        animation: highlight-pulse 2s infinite;
    }
    
    @keyframes highlight-pulse {
        0% { background-color: #ffff90; }
        50% { background-color: #ffffb8; }
        100% { background-color: #ffff90; }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="{{ asset('js/chat-utils.js') }}"></script>
<script>
    // Hàm chuẩn hóa văn bản tiếng Việt
    function normalizeVietnameseText(text) {
        // Đảm bảo text là string
        if (typeof text !== 'string') return '';
        
        // Chuyển chuỗi thành mảng byte UTF-8
        const encoder = new TextEncoder();
        const decoder = new TextDecoder('utf-8', {fatal: false});
        
        // Mã hóa và giải mã lại để đảm bảo UTF-8 hợp lệ
        const bytes = encoder.encode(text);
        return decoder.decode(bytes);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const chatForm = document.getElementById('chat-form');
        const messageInput = document.getElementById('message-input');
        const chatMessages = document.getElementById('chat-messages');
        const sendButton = document.getElementById('send-button');
        const sidebar = document.querySelector('.chat-sidebar');
        const toggleBtn = document.querySelector('.toggle-sidebar-btn');
        const newChatBtn = document.querySelector('.btn-new-chat');
        const conversationsContainer = document.querySelector('.conversations');
        
        let isWaitingForResponse = false;
        let currentConversationId = null;
        let conversationsLoaded = false;
        let docIdsFromUrl = null;
        let isMobile = window.innerWidth <= 768;
        
        // Create mobile sidebar toggle button and overlay
        function setupMobileElements() {
            if (isMobile && !document.querySelector('.mobile-toggle-btn')) {
                // Create toggle button for mobile
                const mobileToggleBtn = document.createElement('button');
                mobileToggleBtn.className = 'mobile-toggle-btn';
                mobileToggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
                document.body.appendChild(mobileToggleBtn);
                
                // Create overlay for mobile
                const overlay = document.createElement('div');
                overlay.className = 'mobile-sidebar-overlay';
                document.body.appendChild(overlay);
                
                // Add event listeners
                mobileToggleBtn.addEventListener('click', toggleMobileSidebar);
                overlay.addEventListener('click', toggleMobileSidebar);
            }
        }
        
        // Toggle mobile sidebar
        function toggleMobileSidebar() {
            sidebar.classList.toggle('mobile-visible');
            document.querySelector('.mobile-sidebar-overlay').classList.toggle('visible');
        }
        
        // Handle resize events
        function handleResize() {
            const wasMobile = isMobile;
            isMobile = window.innerWidth <= 768;
            
            // If switching between mobile/desktop views
            if (wasMobile !== isMobile) {
                if (isMobile) {
                    // Switched to mobile
                    setupMobileElements();
                    // Reset desktop sidebar state
                    document.querySelector('.chat-wrapper').classList.remove('sidebar-collapsed');
                } else {
                    // Switched to desktop
                    sidebar.classList.remove('mobile-visible');
                    const overlay = document.querySelector('.mobile-sidebar-overlay');
                    if (overlay) overlay.classList.remove('visible');
                }
            }
        }
        
        // Initial setup
        setupMobileElements();
        window.addEventListener('resize', handleResize);
        
        // Existing sidebar toggle for desktop
        toggleBtn.addEventListener('click', function() {
            if (isMobile) {
                toggleMobileSidebar();
            } else {
                document.querySelector('.chat-wrapper').classList.toggle('sidebar-collapsed');
            }
        });
        
        // Close mobile sidebar when a conversation is selected
        function closeMobileSidebar() {
            if (isMobile) {
                sidebar.classList.remove('mobile-visible');
                const overlay = document.querySelector('.mobile-sidebar-overlay');
                if (overlay) overlay.classList.remove('visible');
            }
        }
        
        // Kiểm tra xem có tham số doc_ids trong URL không
        function getDocIdsFromUrl() {
            const urlParams = new URLSearchParams(window.location.search);
            const docIdsParam = urlParams.get('doc_ids');
            if (docIdsParam) {
                return docIdsParam.split(',').map(id => parseInt(id.trim(), 10)).filter(id => !isNaN(id));
            }
            return null;
        }
        
        // Hàm làm mới URL - xóa tham số doc_ids
        function resetUrlParams() {
            if (window.location.search && window.location.search.includes('doc_ids')) {
                // Tạo URL mới không có tham số doc_ids
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.delete('doc_ids');
                
                // Cập nhật URL trong thanh địa chỉ mà không làm mới trang
                window.history.replaceState({}, '', currentUrl.toString());
                console.log('Reset URL parameters, removed doc_ids');
                
                // Cập nhật lại biến trạng thái và giao diện
                docIdsFromUrl = null;
                localStorage.removeItem('hadDocIds');
                
                // Xóa session trên server
                fetch('{{ route("chat") }}?clear=1', {
                    method: 'GET'
                }).then(() => {
                    console.log('Session cleared');
                    // Cập nhật giao diện
                    updateDocIdsInfo();
                });
            }
        }
        
        // Khi trang được tải mà không có tham số doc_ids, xóa cache và session
        if (!getDocIdsFromUrl() && localStorage.getItem('hadDocIds') === 'true') {
            // Đánh dấu đã xóa cache
            localStorage.setItem('hadDocIds', 'false');
            
            // Gọi API để xóa session trên server
            fetch('{{ route("chat") }}?clear=1', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(response => {
                console.log('Session cleared on server');
            }).catch(err => {
                console.error('Error clearing session:', err);
            });
        } else if (getDocIdsFromUrl()) {
            // Đánh dấu đã có doc_ids
            localStorage.setItem('hadDocIds', 'true');
        }
        
        // Lưu doc_ids từ URL hiện tại
        docIdsFromUrl = getDocIdsFromUrl();
        
        // Cập nhật thông tin doc_ids ban đầu
        updateDocIdsInfo();
        
        // Thêm sự kiện lắng nghe thay đổi URL
        window.addEventListener('popstate', function() {
            // Reset docIdsFromUrl khi URL thay đổi
            docIdsFromUrl = getDocIdsFromUrl();
            
            // Cập nhật giao diện nếu cần
            updateDocIdsInfo();
        });
        
        // Hàm cập nhật thông tin doc_ids trên giao diện
        function updateDocIdsInfo() {
            const welcomeMessage = document.querySelector('.welcome-message');
            if (welcomeMessage) {
                // Xóa thông báo doc_ids cũ nếu có
                const oldInfo = welcomeMessage.querySelector('.alert-info');
                if (oldInfo) {
                    oldInfo.remove();
                }
                
                // Thêm thông báo mới nếu có doc_ids
                if (docIdsFromUrl && docIdsFromUrl.length > 0) {
                    const docIdsInfo = document.createElement('div');
                    docIdsInfo.className = 'alert alert-info mt-3';
                    docIdsInfo.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-info-circle me-2"></i> Bạn đang chat với tài liệu ID: ${docIdsFromUrl.join(', ')}</span>
                            <button class="btn btn-sm btn-outline-danger clear-docs-btn">
                                <i class="fas fa-times me-1"></i> Hủy chọn
                            </button>
                        </div>
                        <p class="mt-2 mb-0">Hãy đặt câu hỏi liên quan đến nội dung của tài liệu.</p>
                    `;
                    welcomeMessage.appendChild(docIdsInfo);
                    
                    // Thêm sự kiện click cho nút hủy chọn
                    const clearBtn = docIdsInfo.querySelector('.clear-docs-btn');
                    if (clearBtn) {
                        clearBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            resetUrlParams();
                            window.location.href = '{{ route("chat") }}';
                        });
                    }
                }
            }
        }
        
        // Auto-resize text area as user types
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Handle keyboard shortcuts - Enter to send, Shift+Enter for new line
        messageInput.addEventListener('keydown', function(e) {
            // Check if Enter was pressed without Shift
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault(); // Prevent default Enter behavior (new line)
                
                // Only submit if not waiting for response and message isn't empty
                if (!isWaitingForResponse && messageInput.value.trim()) {
                    chatForm.dispatchEvent(new Event('submit'));
                }
            }
            // Shift+Enter will allow default behavior (new line)
        });
        
        // Load conversations from server
        loadConversations();
        
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
                let chatEndpoint = '{{ route("chat.send") }}';
                
                // Kiểm tra lại tham số doc_ids chỉ từ URL hiện tại
                const currentDocIds = getDocIdsFromUrl();
                
                // Thêm tham số doc_ids vào URL nếu có trong URL hiện tại
                if (currentDocIds && currentDocIds.length > 0) {
                    // Kiểm tra xem URL đã có tham số chưa
                    if (chatEndpoint.includes('?')) {
                        chatEndpoint += '&doc_ids=' + currentDocIds.join(',');
                    } else {
                        chatEndpoint += '?doc_ids=' + currentDocIds.join(',');
                    }
                    console.log('Adding document IDs to request: ' + currentDocIds.join(','));
                } else {
                    console.log('No document IDs in current URL, sending standard chat request');
                    // Đảm bảo session được xóa bằng cách chủ động gọi API clear
                    fetch('{{ route("chat") }}?clear=1', {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                }
                
                console.log('Sending request to:', chatEndpoint);
                
                // Kiểm tra hợp lệ của JSON trước khi gửi
                try {
                    const normalizedMessage = normalizeVietnameseText(message);
                    const testJson = JSON.stringify({test: normalizedMessage});
                    JSON.parse(testJson); // Nếu có lỗi sẽ throw exception
                    
                    const response = await fetch(chatEndpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json; charset=UTF-8', // Chỉ định rõ charset=UTF-8
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ 
                            message: normalizedMessage,
                            conversation_id: currentConversationId
                        })
                    });
                    
                    console.log('Response status:', response.status);
                    
                    // Remove typing indicator
                    removeTypingIndicator();
                    
                    if (!response.ok) {
                        // Handle HTTP errors
                        let errorData;
                        try {
                            errorData = await response.json();
                            console.error('Error response data:', errorData);
                        } catch (jsonError) {
                            console.error('Failed to parse error response as JSON:', jsonError);
                            errorData = { message: 'Có lỗi trong quá trình kết nối với máy chủ' };
                        }
                        const errorMessage = errorData.message || 'Có lỗi trong quá trình kết nối với máy chủ';
                        appendErrorMessage(errorMessage);
                        return;
                    }
                    
                    let data;
                    try {
                        data = await response.json();
                        console.log('Response data:', data);
                    } catch (jsonError) {
                        console.error('Failed to parse response as JSON:', jsonError);
                        appendErrorMessage('Có lỗi khi xử lý phản hồi từ máy chủ');
                        return;
                    }
                    
                    if (data.success === false) {
                        // Handle application errors
                        const errorMessage = data.message || 'Có lỗi xảy ra khi xử lý tin nhắn của bạn';
                        appendErrorMessage(errorMessage);
                    } else {
                        // Display bot response with citations if available
                        const botMessage = data.message;
                        const citations = data.citations || [];
                        
                        // Log citation details in console for debugging
                        if (citations && citations.length > 0) {
                            console.log(`[CITATION-FRONTEND] Nhận được ${citations.length} trích dẫn từ server`);
                            citations.forEach((citation, index) => {
                                console.log(`[CITATION-FRONTEND] Trích dẫn #${index+1}: doc_id=${citation.doc_id}, title=${citation.title}, page=${citation.page}, URL=${citation.url}`);
                            });
                        } else {
                            console.log('[CITATION-FRONTEND] Không có trích dẫn trong phản hồi');
                        }
                        
                        appendMessage(botMessage, 'bot', citations);
                    
                        // Update conversation ID if this was a new conversation
                        if (!currentConversationId && data.conversation_id) {
                            currentConversationId = data.conversation_id;
                            
                            // Load conversations to update sidebar
                            loadConversations();
                        }
                    }
                } catch (jsonValidationError) {
                    // Xử lý lỗi khi kiểm tra JSON
                    console.error('JSON validation error:', jsonValidationError);
                    removeTypingIndicator();
                    appendErrorMessage('Có vấn đề với định dạng văn bản. Vui lòng kiểm tra lại nội dung tin nhắn.');
                    return;
                }
                
            } catch (error) {
                console.error('Network error:', error);
                removeTypingIndicator();
                
                let errorMessage = 'Không thể kết nối với máy chủ. Vui lòng kiểm tra kết nối internet của bạn.';
                
                if (error.message && error.message.includes('UTF-8')) {
                    // Cơ chế dự phòng - thử gửi lại với mã hóa đơn giản hơn
                    try {
                        console.log('Detected UTF-8 error, trying with simplified text');
                        const simplifiedMessage = message.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                        console.log('Retrying with simplified text:', simplifiedMessage);
                        
                        // Gửi lại với văn bản đã đơn giản hóa (bỏ dấu)
                        const retryResponse = await fetch(chatEndpoint, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json; charset=UTF-8',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ 
                                message: simplifiedMessage,
                                conversation_id: currentConversationId
                            })
                        });
                        
                        // Xử lý response của lần gửi lại
                        if (retryResponse.ok) {
                            const data = await retryResponse.json();
                            // Hiển thị kết quả
                            appendMessage(data.message, 'bot', data.citations || []);
                            return;
                        }
                    } catch (retryError) {
                        console.error('Retry also failed:', retryError);
                    }
                    
                    errorMessage = 'Có vấn đề với định dạng văn bản tiếng Việt. Vui lòng thử chia nhỏ tin nhắn hoặc tạo cuộc trò chuyện mới.';
                }
                
                appendErrorMessage(errorMessage);
            } finally {
                // Reset state
                isWaitingForResponse = false;
                toggleSendButtonState(false);
                messageInput.focus();
            }
        });
        
        // Handle "New chat" button clicks
        newChatBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // If current chat is empty/new (has only welcome message), just refresh
            if (chatMessages.querySelectorAll('.chat-message').length === 0) {
                // Just refresh the page
                window.location.reload();
                return;
            }
            
            // Reset current state
            currentConversationId = null;
            clearChatMessages();
            
            // Create new chat interface (clear messages)
            createNewChat();
        });
        
        // Load conversations from server
        async function loadConversations() {
            try {
                const response = await fetch('{{ route("chat.conversations") }}', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                
                if (!response.ok) {
                    console.error('Failed to load conversations:', response.status);
                    return;
                }
                
                const data = await response.json();
                
                if (data.success && data.conversations) {
                    // Clear existing conversations
                    conversationsContainer.innerHTML = '';
                    
                    // Add each conversation to sidebar
                    data.conversations.forEach(conversation => {
                        addConversationToSidebar(conversation);
                    });
                    
                    // Mark as loaded
                    conversationsLoaded = true;
                    
                    // If no conversation is selected and we have conversations, select the first one
                    if (!currentConversationId && data.conversations.length > 0) {
                        loadConversation(data.conversations[0].id);
                    }
                }
            } catch (error) {
                console.error('Error loading conversations:', error);
            }
        }
        
        // Load a specific conversation
        async function loadConversation(conversationId) {
            try {
                const response = await fetch(`{{ url('chat/conversations') }}/${conversationId}/messages`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                
                if (!response.ok) {
                    console.error('Failed to load conversation:', response.status);
                    return;
                }
                
                const data = await response.json();
                
                if (data.success) {
                    // Set current conversation
                    currentConversationId = conversationId;
                
                    // Clear chat messages
                    clearChatMessages();
                    
                    // Add messages to chat
                    if (data.messages && data.messages.length > 0) {
                        data.messages.forEach(message => {
                            // Kiểm tra nếu có trích dẫn trong tin nhắn
                            const citations = message.citations ? message.citations : null;
                            appendMessage(message.content, message.sender, citations);
                        });
                    }
                
                    // Update active state in sidebar
                    document.querySelectorAll('.conversation-item').forEach(item => {
                        item.classList.remove('active');
                        if (item.dataset.conversationId == conversationId) {
                            item.classList.add('active');
                        }
                    });
                    
                    // Close mobile sidebar after selecting a conversation
                    closeMobileSidebar();
                    
                    // Scroll to bottom
                    scrollToBottom();
                }
            } catch (error) {
                console.error('Error loading conversation:', error);
            }
        }
        
        // Add a conversation to the sidebar
        function addConversationToSidebar(conversation) {
            const conversationElement = document.createElement('div');
            conversationElement.className = 'conversation-item';
            conversationElement.dataset.conversationId = conversation.id;
            
            if (currentConversationId && currentConversationId == conversation.id) {
                conversationElement.classList.add('active');
            }
            
            // Format date
            const createdDate = new Date(conversation.created_at);
            const formattedDate = formatDate(createdDate);
            
            conversationElement.innerHTML = `
                <div class="conversation-title">${conversation.title}</div>
                <div class="conversation-date">${formattedDate}</div>
                <button class="delete-chat-btn" data-conversation-id="${conversation.id}">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            
            // Add click event for loading conversation
            conversationElement.addEventListener('click', function(e) {
                // Ignore clicks on delete button
                if (e.target.closest('.delete-chat-btn')) {
                    return;
                }
                
                loadConversation(conversation.id);
            });
            
            // Add event for delete button
            const deleteBtn = conversationElement.querySelector('.delete-chat-btn');
            deleteBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                deleteConversation(conversation.id);
            });
            
            // Add to sidebar
            conversationsContainer.appendChild(conversationElement);
        }
        
        // Delete a conversation
        async function deleteConversation(conversationId) {
            // Confirm deletion
            if (!confirm('Bạn có chắc chắn muốn xóa cuộc trò chuyện này?')) {
                return;
            }
            
            try {
                const response = await fetch(`{{ url('chat/conversations') }}/${conversationId}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                
                if (!response.ok) {
                    console.error('Failed to delete conversation:', response.status);
                    return;
                }
                
                const data = await response.json();
                
                if (data.success) {
                    // Remove from sidebar
                    const conversationEl = document.querySelector(`.conversation-item[data-conversation-id="${conversationId}"]`);
                    if (conversationEl) {
                        conversationEl.remove();
                    }
                    
                    // If we deleted the current conversation, create a new one
                    if (conversationId == currentConversationId) {
                        createNewChat();
                    }
                }
            } catch (error) {
                console.error('Error deleting conversation:', error);
            }
        }
        
        // Clear chat messages
        function clearChatMessages() {
            chatMessages.innerHTML = `
                <div class="welcome-message">
                    <h1>Tôi có thể giúp gì cho bạn?</h1>
                    ${docIdsFromUrl && docIdsFromUrl.length > 0 ? `
                    <div class="alert alert-info mt-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-info-circle me-2"></i> Bạn đang chat với tài liệu ID: ${docIdsFromUrl.join(', ')}</span>
                        </div>
                        <p class="mt-2 mb-0">Hãy đặt câu hỏi liên quan đến nội dung của tài liệu.</p>
                    </div>
                    ` : ''}
                </div>
            `;
        }
        
        // Format date for display
        function formatDate(date) {
            return date.toLocaleDateString('vi-VN', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Add a message to the chat window
        function appendMessage(message, sender, citations = null) {
            const messageElement = document.createElement('div');
            messageElement.className = `chat-message ${sender}`;
            
            const contentElement = document.createElement('div');
            contentElement.className = 'message-content';
            
            if (sender === 'bot') {
                // Tìm và xử lý các đoạn [Đoạn X] trong tin nhắn nếu không có citations
                // Chỉ áp dụng cho chat RAG (có citations)
                if (citations && citations.length > 0) {
                    // Giữ nguyên xử lý markdown cho tin nhắn có citations
                    contentElement.innerHTML = marked.parse(message);
                    
                    // Thêm phần hiển thị trích dẫn
                    const citationsElement = document.createElement('div');
                    citationsElement.className = 'message-citations';
                    
                    const citationsTitle = document.createElement('h5');
                    citationsTitle.className = 'citations-title';
                    citationsTitle.innerHTML = '<i class="fas fa-book me-2"></i>Tài liệu tham khảo:';
                    citationsElement.appendChild(citationsTitle);
                    
                    const citationsList = document.createElement('ul');
                    citationsList.className = 'citations-list';
                    
                    citations.forEach((citation, index) => {
                        const citationItem = document.createElement('li');
                        citationItem.innerHTML = `
                            <a href="#" class="citation-link" 
                               data-doc-id="${citation.doc_id}" 
                               data-page="${citation.page}" 
                               data-chunk="${citation.chunk_index || 0}">
                                <i class="fas fa-file-alt me-1"></i>
                                ${citation.title} - Trang ${citation.page}${citation.chunk_index ? ', đoạn ' + citation.chunk_index : ''}
                            </a>
                        `;
                        citationsList.appendChild(citationItem);
                    });
                    
                    citationsElement.appendChild(citationsList);
                    contentElement.appendChild(citationsElement);
                } else {
                    // Đây là chat kinh tế (không có citations)
                    // Xử lý markdown nhưng không giữ đánh số đoạn nếu có
                    contentElement.innerHTML = marked.parse(message);
                }
            } else {
                contentElement.textContent = message;
            }
            
            messageElement.appendChild(contentElement);
            chatMessages.appendChild(messageElement);
            
            // Kích hoạt các liên kết trích dẫn
            setupCitationLinks();
            
            // Scroll to bottom
            scrollToBottom();
        }
        
        // Hàm thiết lập sự kiện cho các liên kết trích dẫn
        function setupCitationLinks() {
            document.querySelectorAll('.citation-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const docId = this.dataset.docId;
                    const page = this.dataset.page;
                    const chunk = this.dataset.chunk;
                    
                    // Xây dựng URL tới tài liệu gốc với tham số highlight
                    const documentUrl = `/documents/${docId}?page=${page}&highlight=${chunk}`;
                    
                    // Mở tài liệu trong cửa sổ mới hoặc hiển thị modal với iframe
                    showDocumentPreview(documentUrl, docId, page, chunk);
                });
            });
        }
        
        // Hàm hiển thị preview tài liệu
        function showDocumentPreview(url, docId, page, chunk) {
            // Tạo modal để hiển thị tài liệu
            const modal = document.createElement('div');
            modal.className = 'citation-modal';
            modal.innerHTML = `
                <div class="citation-modal-content">
                    <div class="citation-modal-header">
                        <h4>Tài liệu tham khảo</h4>
                        <div class="citation-modal-actions">
                            <a href="${url}" target="_blank" class="btn btn-sm btn-primary me-2">
                                <i class="fas fa-external-link-alt me-1"></i>Mở trong tab mới
                            </a>
                            <button class="btn-close citation-modal-close"></button>
                        </div>
                    </div>
                    <div class="citation-modal-body">
                        <div class="citation-loading">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Đang tải tài liệu...</p>
                        </div>
                        <iframe src="${url}" class="citation-iframe" style="display: none;"></iframe>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Xử lý đóng modal khi nhấn nút đóng
            const closeBtn = modal.querySelector('.citation-modal-close');
            closeBtn.addEventListener('click', function() {
                modal.remove();
            });
            
            // Đóng modal khi nhấn bên ngoài modal content
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.remove();
                }
            });
            
            // Đóng modal khi nhấn phím ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && document.querySelector('.citation-modal')) {
                    modal.remove();
                }
            });
            
            // Xử lý khi iframe đã tải xong
            const iframe = modal.querySelector('.citation-iframe');
            iframe.onload = function() {
                modal.querySelector('.citation-loading').style.display = 'none';
                iframe.style.display = 'block';
                
                // Xác định loại tài liệu từ URL thay vì gọi API (để tránh lỗi 500)
                const fileExtension = getFileExtensionFromUrl(url) || 'unknown';
                
                // Nếu là file Word, hiển thị nội dung từ API
                if (['docx', 'doc'].includes(fileExtension.toLowerCase())) {
                    // Xử lý hiển thị nội dung Word
                    iframe.style.display = 'none';
                    
                    // Tạo container hiển thị nội dung Word
                    const wordContainer = document.createElement('div');
                    wordContainer.className = 'word-content-container';
                    wordContainer.innerHTML = `
                        <div class="p-4 word-content">
                            <div class="word-header mb-4 text-center">
                                <i class="fas fa-file-word fa-3x text-primary mb-3"></i>
                                <h5 class="fw-bold">Tài liệu Word - Trang ${page}</h5>
                            </div>
                            <div class="word-text bg-light p-4 rounded shadow-sm">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Đang tải nội dung...</p>
                            </div>
                        </div>
                    `;
                    
                    // Thêm container vào modal
                    modal.querySelector('.citation-modal-body').appendChild(wordContainer);
                    
                    // Gọi API để lấy nội dung văn bản
                    fetch(`/api/citation/${docId}/${page}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! Status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                const wordText = wordContainer.querySelector('.word-text');
                                wordText.innerHTML = `<div class="word-content-text">${data.content}</div>`;
                                
                                // Highlight đoạn text nếu có chunk_index
                                if (chunk) {
                                    setTimeout(() => {
                                        const paragraphs = wordText.querySelectorAll('p, div');
                                        if (paragraphs.length > chunk) {
                                            const targetParagraph = paragraphs[chunk];
                                            targetParagraph.classList.add('highlighted-text');
                                            targetParagraph.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                        }
                                    }, 300);
                                }
                            } else {
                                wordContainer.querySelector('.word-text').innerHTML = `
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        ${data.message || 'Không thể tải nội dung tài liệu'}
                                    </div>
                                `;
                            }
                        })
                        .catch(error => {
                            console.error('Error loading Word content:', error);
                            wordContainer.querySelector('.word-text').innerHTML = `
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    Lỗi khi tải nội dung tài liệu
                                </div>
                            `;
                        });
                }
            };
        }
        
        // Hàm lấy extension từ URL
        function getFileExtensionFromUrl(url) {
            try {
                // Loại bỏ các tham số query
                const baseUrl = url.split('?')[0];
                
                // Nếu URL có chứa documents/, assume đây là URL xem document
                if (url.includes('/documents/')) {
                    // Có thể là tài liệu word hoặc text
                    if (url.toLowerCase().includes('highlight=')) {
                        return 'txt';
                    } else {
                        return 'docx';
                    }
                }
                
                // Lấy phần mở rộng từ URL nếu có
                const extension = baseUrl.split('.').pop();
                
                // Kiểm tra nếu extension hợp lệ
                if (['pdf', 'docx', 'doc', 'txt', 'md'].includes(extension.toLowerCase())) {
                    return extension;
                }
                
                return 'unknown';
            } catch (error) {
                console.error('Error getting file extension:', error);
                return 'unknown';
            }
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
    });

    // Theo dõi các click trên liên kết trong trang
    document.addEventListener('click', function(e) {
        // Kiểm tra xem người dùng có nhấp vào liên kết không
        const link = e.target.closest('a');
        if (link && link.href && link.href.includes(window.location.origin)) {
            // Sau khi click, đợi một chút để URL cập nhật rồi kiểm tra lại doc_ids
            setTimeout(function() {
                docIdsFromUrl = getDocIdsFromUrl();
                updateDocIdsInfo();
            }, 100);
        }
    });
</script>
@endpush