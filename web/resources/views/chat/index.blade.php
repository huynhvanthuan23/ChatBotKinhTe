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

    /* Thêm CSS để đảm bảo trích dẫn hiển thị rõ ràng */
    .chat-message.bot .message-content .citation-link {
        color: #1890ff;
        text-decoration: none;
        cursor: pointer;
        font-weight: 500;
        border-bottom: 1px dashed #1890ff;
        display: inline-block;
        margin: 2px 0;
    }

    .chat-message.bot .message-content .citation-link:hover {
        color: #40a9ff;
        text-decoration: none;
        background-color: rgba(24, 144, 255, 0.1);
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
    
    // Create a new chat session
    function createNewChat() {
        // Clear chat messages
        clearChatMessages();
        
        // Reset current conversation ID
        currentConversationId = null;
        
        // Remove active state from sidebar
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.classList.remove('active');
        });
            
        // Focus on input
        messageInput.focus();
    }
    
    // Lưu doc_ids từ URL nếu có
    docIdsFromUrl = getDocIdsFromUrl();
    if (docIdsFromUrl && docIdsFromUrl.length > 0) {
        console.log('Detected doc_ids in URL:', docIdsFromUrl);
        // Hiển thị thông báo cho người dùng
        const welcomeMessage = document.querySelector('.welcome-message');
        if (welcomeMessage) {
            const docIdsInfo = document.createElement('div');
            docIdsInfo.className = 'alert alert-info mt-3';
            docIdsInfo.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-info-circle me-2"></i> Bạn đang chat với tài liệu ID: ${docIdsFromUrl.join(', ')}</span>
                </div>
                <p class="mt-2 mb-0">Hãy đặt câu hỏi liên quan đến nội dung của tài liệu.</p>
            `;
            welcomeMessage.appendChild(docIdsInfo);
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
            
            // Thêm tham số doc_ids vào URL nếu có
            if (docIdsFromUrl && docIdsFromUrl.length > 0) {
                // Kiểm tra xem URL đã có tham số chưa
                if (chatEndpoint.includes('?')) {
                    chatEndpoint += '&doc_ids=' + docIdsFromUrl.join(',');
                } else {
                    chatEndpoint += '?doc_ids=' + docIdsFromUrl.join(',');
                }
            }
            
                console.log('Sending request to:', chatEndpoint);
                
                const response = await fetch(chatEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                body: JSON.stringify({ 
                    message,
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
                    // Display bot response
                    const botMessage = data.message;
                    appendMessage(botMessage, 'bot');
                
                // Update conversation ID if this was a new conversation
                if (!currentConversationId && data.conversation_id) {
                    currentConversationId = data.conversation_id;
                    
                    // Load conversations to update sidebar
                    loadConversations();
                }
                }
                
            } catch (error) {
                console.error('Network error:', error);
                removeTypingIndicator();
                const errorMessage = 'Không thể kết nối với máy chủ. Vui lòng kiểm tra kết nối internet của bạn.';
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
                        appendMessage(message.content, message.sender);
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
        function appendMessage(message, sender) {
            const messageElement = document.createElement('div');
            messageElement.className = `chat-message ${sender}`;
            
            const contentElement = document.createElement('div');
            contentElement.className = 'message-content';
            
            if (sender === 'bot') {
                // Debug: Log raw message before parsing
                console.log('Original bot message before parsing:', message);
                
                // Debug: Look for citation pattern
                const hasCitations = message.includes('**Nguồn trích dẫn:**');
                console.log('Message contains citation section:', hasCitations);
                
                // Process markdown for bot responses
                contentElement.innerHTML = marked.parse(message);
                
                // Log để debug link trích dẫn
                console.log('Bot message parsed as HTML:', contentElement.innerHTML);
                
                // Xử lý các liên kết trích dẫn để mở trong side panel
                const citationLinks = contentElement.querySelectorAll('a');
                console.log(`Found ${citationLinks.length} links in the message`);
                
                citationLinks.forEach(link => {
                    console.log('Link found:', link.href, link.textContent);
                    
                    // Kiểm tra xem có phải là liên kết đến tài liệu không
                    if (link.href.includes('/documents/')) {
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            
                            // Mở liên kết trong side panel thay vì chuyển hướng
                            const documentUrl = this.getAttribute('href');
                            console.log('Opening document citation:', documentUrl);
                            
                            // Tạo iframe để hiển thị tài liệu
                            openDocumentInSidePanel(documentUrl);
                        });
                        
                        // Thêm lớp CSS cho liên kết trích dẫn
                        link.classList.add('citation-link');
                        console.log('Citation link styled:', link.href);
                    }
                });
            } else {
                contentElement.textContent = message;
            }
            
            messageElement.appendChild(contentElement);
            chatMessages.appendChild(messageElement);
            
            // Scroll to bottom
            scrollToBottom();
        }
        
        // Mở tài liệu trong side panel
        function openDocumentInSidePanel(url) {
            console.log('Opening document in side panel with URL:', url);
            
            // Kiểm tra xem side panel đã tồn tại chưa
            let sidePanel = document.getElementById('document-side-panel');
            
            if (!sidePanel) {
                // Tạo side panel mới
                sidePanel = document.createElement('div');
                sidePanel.id = 'document-side-panel';
                sidePanel.className = 'document-side-panel';
                
                // Thanh tiêu đề cho side panel
                const panelHeader = document.createElement('div');
                panelHeader.className = 'panel-header';
                
                const panelTitle = document.createElement('div');
                panelTitle.className = 'panel-title';
                panelTitle.textContent = 'Tài liệu trích dẫn';
                
                const closeButton = document.createElement('button');
                closeButton.className = 'panel-close-btn';
                closeButton.innerHTML = '<i class="fas fa-times"></i>';
                closeButton.addEventListener('click', function() {
                    sidePanel.classList.remove('open');
                });
                
                panelHeader.appendChild(panelTitle);
                panelHeader.appendChild(closeButton);
                
                // Khung nội dung
                const panelContent = document.createElement('div');
                panelContent.className = 'panel-content';
                
                const iframe = document.createElement('iframe');
                iframe.id = 'document-iframe';
                iframe.src = url;
                iframe.frameBorder = '0';
                
                panelContent.appendChild(iframe);
                
                sidePanel.appendChild(panelHeader);
                sidePanel.appendChild(panelContent);
                
                document.body.appendChild(sidePanel);
                
                // Thêm style cho side panel
                const style = document.createElement('style');
                style.textContent = `
                    .document-side-panel {
                        position: fixed;
                        top: 64px;
                        right: -50%;
                        width: 50%;
                        height: calc(100vh - 64px);
                        background-color: white;
                        box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
                        z-index: 1050;
                        transition: right 0.3s ease;
                        display: flex;
                        flex-direction: column;
                    }
                    
                    .document-side-panel.open {
                        right: 0;
                    }
                    
                    .panel-header {
                        height: 50px;
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        padding: 0 16px;
                        border-bottom: 1px solid #eee;
                    }
                    
                    .panel-title {
                        font-weight: 600;
                    }
                    
                    .panel-close-btn {
                        background: none;
                        border: none;
                        cursor: pointer;
                        font-size: 16px;
                        color: #666;
                        width: 32px;
                        height: 32px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        border-radius: 4px;
                    }
                    
                    .panel-close-btn:hover {
                        background-color: #f5f5f5;
                        color: #333;
                    }
                    
                    .panel-content {
                        flex: 1;
                        overflow: hidden;
                    }
                    
                    .panel-content iframe {
                        width: 100%;
                        height: 100%;
                        border: none;
                    }
                    
                    @media (max-width: 768px) {
                        .document-side-panel {
                            width: 100%;
                            right: -100%;
                        }
                    }
                `;
                
                document.head.appendChild(style);
            } else {
                // Cập nhật URL của iframe
                const iframe = document.getElementById('document-iframe');
                iframe.src = url;
            }
            
            // Mở side panel
            setTimeout(() => {
                sidePanel.classList.add('open');
                console.log('Side panel opened with URL:', url);
            }, 100);
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
</script>
@endpush