<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Chatbot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            background-color: #f7f9fc;
        }
        .chat-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        .messages {
            height: 400px;
            overflow-y: auto;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            margin-bottom: 20px;
            background-color: #f9f9f9;
        }
        .message {
            margin-bottom: 15px;
            padding: 10px 15px;
            border-radius: 18px;
            max-width: 80%;
            word-wrap: break-word;
        }
        .user-message {
            background-color: #e3f2fd;
            color: #0d47a1;
            margin-left: auto;
            border-bottom-right-radius: 5px;
        }
        .bot-message {
            background-color: #f1f1f1;
            color: #333;
            margin-right: auto;
            border-bottom-left-radius: 5px;
        }
        .input-group {
            margin-top: 15px;
        }
        .debug-info {
            margin-top: 20px;
            font-size: 12px;
            color: #666;
        }
        .loader {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top-color: #0d6efd;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="chat-container">
            <h2 class="text-center mb-4">Chatbot Test</h2>
            
            <!-- Buttons -->
            <div class="d-flex justify-content-between mb-3">
                <button id="check-health" class="btn btn-outline-primary btn-sm">Kiểm tra Health</button>
                <button id="check-service" class="btn btn-outline-info btn-sm">Thông tin Service</button>
            </div>
            
            <div class="messages" id="messages">
                <div class="message bot-message">
                    Xin chào! Tôi là chatbot kinh tế. Bạn có thể hỏi tôi về các vấn đề kinh tế.
                </div>
            </div>
            <form id="chat-form">
                <div class="input-group">
                    <input type="text" id="user-input" class="form-control" placeholder="Nhập câu hỏi của bạn...">
                    <button type="submit" class="btn btn-primary">Gửi</button>
                </div>
            </form>
            <div class="debug-info" id="debug-info"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // API Health check button
            $('#check-health').on('click', function() {
                // Hiển thị đang loading
                $('#messages').append(`
                    <div class="message bot-message" id="loading-message">
                        <div class="loader"></div> Đang kiểm tra health...
                    </div>
                `);
                
                // Scroll xuống cuối
                $('#messages').scrollTop($('#messages')[0].scrollHeight);
                
                // Gọi API health check
                $.ajax({
                    url: '/api/chatbot/health',
                    method: 'GET',
                    success: function(response) {
                        // Xóa loading
                        $('#loading-message').remove();
                        
                        // Hiển thị kết quả
                        $('#messages').append(`
                            <div class="message bot-message">
                                <strong>Health Check:</strong><br>
                                Status: ${response.data.status}<br>
                                API: ${response.data.components.api}<br>
                                Vector DB: ${response.data.components.vector_db}
                            </div>
                        `);
                        
                        // Scroll xuống cuối
                        $('#messages').scrollTop($('#messages')[0].scrollHeight);
                    },
                    error: function(xhr) {
                        // Xóa loading
                        $('#loading-message').remove();
                        
                        // Hiển thị lỗi
                        $('#messages').append(`
                            <div class="message bot-message text-danger">
                                <strong>Health Check Error:</strong><br>
                                Không thể kết nối đến API
                            </div>
                        `);
                        
                        // Scroll xuống cuối
                        $('#messages').scrollTop($('#messages')[0].scrollHeight);
                    }
                });
            });
            
            // Service Info button
            $('#check-service').on('click', function() {
                // Hiển thị đang loading
                $('#messages').append(`
                    <div class="message bot-message" id="loading-message">
                        <div class="loader"></div> Đang lấy thông tin service...
                    </div>
                `);
                
                // Scroll xuống cuối
                $('#messages').scrollTop($('#messages')[0].scrollHeight);
                
                // Gọi API service info
                $.ajax({
                    url: '/api/chatbot/service-info',
                    method: 'GET',
                    success: function(response) {
                        // Xóa loading
                        $('#loading-message').remove();
                        
                        // Hiển thị kết quả
                        const info = response.data;
                        $('#messages').append(`
                            <div class="message bot-message">
                                <strong>Service Info:</strong><br>
                                Type: ${info.service_type}<br>
                                API Type: ${info.api_type}<br>
                                Model: ${info.model}<br>
                                Status: ${info.status}
                            </div>
                        `);
                        
                        // Scroll xuống cuối
                        $('#messages').scrollTop($('#messages')[0].scrollHeight);
                    },
                    error: function(xhr) {
                        // Xóa loading
                        $('#loading-message').remove();
                        
                        // Hiển thị lỗi
                        $('#messages').append(`
                            <div class="message bot-message text-danger">
                                <strong>Service Info Error:</strong><br>
                                Không thể lấy thông tin service
                            </div>
                        `);
                        
                        // Scroll xuống cuối
                        $('#messages').scrollTop($('#messages')[0].scrollHeight);
                    }
                });
            });
            
            // Chat form submit
            $('#chat-form').on('submit', function(e) {
                e.preventDefault();
                
                const userInput = $('#user-input').val().trim();
                if (!userInput) return;
                
                // Hiển thị tin nhắn của người dùng
                $('#messages').append(`
                    <div class="message user-message">
                        ${userInput}
                    </div>
                `);
                
                // Hiển thị đang loading
                $('#messages').append(`
                    <div class="message bot-message" id="loading-message">
                        <div class="loader"></div> Đang xử lý...
                    </div>
                `);
                
                // Scroll xuống cuối
                $('#messages').scrollTop($('#messages')[0].scrollHeight);
                
                // Xóa input
                $('#user-input').val('');
                
                // Gọi API
                $.ajax({
                    url: '/api/chatbot/chat',
                    method: 'GET',
                    data: {
                        query: userInput
                    },
                    success: function(response) {
                        // Xóa loading
                        $('#loading-message').remove();
                        
                        if (response.success) {
                            // Hiển thị tin nhắn của bot
                            $('#messages').append(`
                                <div class="message bot-message">
                                    ${response.data.response}
                                </div>
                            `);
                            
                            // Hiển thị thông tin debug
                            $('#debug-info').html(`
                                <strong>Debug Info:</strong><br>
                                Thời gian xử lý: ${response.data.processing_time.toFixed(2)} giây<br>
                                Số tài liệu tìm thấy: ${response.data.documents_found}<br>
                                Sử dụng context: ${response.data.context_used ? 'Có' : 'Không'}
                            `);
                        } else {
                            // Hiển thị lỗi
                            $('#messages').append(`
                                <div class="message bot-message text-danger">
                                    Lỗi: ${response.message}
                                </div>
                            `);
                        }
                        
                        // Scroll xuống cuối
                        $('#messages').scrollTop($('#messages')[0].scrollHeight);
                    },
                    error: function(xhr) {
                        // Xóa loading
                        $('#loading-message').remove();
                        
                        // Hiển thị lỗi
                        let errorMessage = 'Đã xảy ra lỗi khi kết nối đến server.';
                        
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.message) {
                                errorMessage = response.message;
                            }
                        } catch (e) {}
                        
                        $('#messages').append(`
                            <div class="message bot-message text-danger">
                                Lỗi: ${errorMessage}
                            </div>
                        `);
                        
                        // Scroll xuống cuối
                        $('#messages').scrollTop($('#messages')[0].scrollHeight);
                    }
                });
            });
        });
    </script>
</body>
</html> 