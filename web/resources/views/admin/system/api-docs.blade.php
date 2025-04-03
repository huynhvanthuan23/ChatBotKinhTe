@extends('layouts.admin')

@section('title', 'Tài liệu API Chatbot')

@section('content_header')
    <h1>Tài liệu API Chatbot</h1>
@stop

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Thông tin chung về API</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-2"></i> API Chatbot được xây dựng trên nền tảng FastAPI, cung cấp khả năng truy cập đến mô hình ngôn ngữ lớn để trả lời các câu hỏi về kinh tế.
                </div>
                
                <div class="mt-4">
                    <h5>Thông tin cơ bản</h5>
                    <table class="table table-bordered">
                        <tr>
                            <th style="width: 20%">Base URL</th>
                            <td><code>{{ env('CHATBOT_API_URL', 'http://localhost:8000/api/v1/chat') }}</code></td>
                        </tr>
                        <tr>
                            <th>Authentication</th>
                            <td>Không yêu cầu (chỉ cho nội bộ)</td>
                        </tr>
                        <tr>
                            <th>Format</th>
                            <td>JSON</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Chat API Endpoint</h3>
            </div>
            <div class="card-body">
                <h5>POST /chat</h5>
                <p>Gửi một tin nhắn đến chatbot và nhận phản hồi.</p>
                
                <div class="mt-4">
                    <h6>Request</h6>
                    <pre><code class="language-json">{
    "message": "Tình hình kinh tế Việt Nam hiện nay như thế nào?",
    "user_id": 1 // Tùy chọn
}</code></pre>
                </div>
                
                <div class="mt-4">
                    <h6>Response</h6>
                    <pre><code class="language-json">{
    "response": "Tình hình kinh tế Việt Nam hiện nay đang có những tiến triển tích cực...",
    "query": "Tình hình kinh tế Việt Nam hiện nay như thế nào?"
}</code></pre>
                </div>
                
                <div class="mt-4">
                    <h6>Mã lỗi có thể xảy ra</h6>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>HTTP Status</th>
                                <th>Mô tả</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>400</td>
                                <td>Bad Request - Yêu cầu không hợp lệ</td>
                            </tr>
                            <tr>
                                <td>500</td>
                                <td>Internal Server Error - Lỗi xử lý từ phía server</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4">
                    <h6>Ví dụ sử dụng với cURL</h6>
                    <pre><code class="language-bash">curl -X POST "{{ str_replace('/chat', '', env('CHATBOT_API_URL', 'http://localhost:8000/api/v1/chat')) }}/chat" \
     -H "Content-Type: application/json" \
     -d '{"message": "Tình hình kinh tế Việt Nam hiện nay như thế nào?"}'</code></pre>
                </div>
                
                <div class="mt-4">
                    <h6>Ví dụ sử dụng với JavaScript</h6>
                    <pre><code class="language-javascript">fetch('{{ str_replace('/chat', '', env('CHATBOT_API_URL', 'http://localhost:8000/api/v1/chat')) }}/chat', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        message: 'Tình hình kinh tế Việt Nam hiện nay như thế nào?'
    })
})
.then(response => response.json())
.then(data => {
    console.log(data.response);
})
.catch(error => {
    console.error('Error:', error);
});</code></pre>
                </div>
                
                <div class="mt-4">
                    <h6>Ví dụ sử dụng với PHP</h6>
                    <pre><code class="language-php">&lt;?php
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, '{{ str_replace('/chat', '', env('CHATBOT_API_URL', 'http://localhost:8000/api/v1/chat')) }}/chat');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'message' => 'Tình hình kinh tế Việt Nam hiện nay như thế nào?'
]));

$headers = [];
$headers[] = 'Content-Type: application/json';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$result = curl_exec($ch);
if (curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
}
curl_close($ch);

$response = json_decode($result, true);
echo $response['response'];
?></code></pre>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Health Check Endpoint</h3>
            </div>
            <div class="card-body">
                <h5>GET /health</h5>
                <p>Kiểm tra trạng thái hoạt động của API.</p>
                
                <div class="mt-4">
                    <h6>Response</h6>
                    <pre><code class="language-json">{
    "status": "ok",
    "api_version": "1.0.0",
    "project_name": "ChatBotKinhTe",
    "resources": {
        "model_file_exists": true,
        "vector_db_exists": true
    }
}</code></pre>
                </div>
                
                <div class="mt-4">
                    <h6>Ví dụ sử dụng với cURL</h6>
                    <pre><code class="language-bash">curl -X GET "{{ str_replace('/chat', '', env('CHATBOT_API_URL', 'http://localhost:8000/api/v1/chat')) }}/health"</code></pre>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@section('css')
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/default.min.css">
<style>
    pre {
        background-color: #f8f9fa;
        border-radius: 4px;
        padding: 15px;
    }
    code {
        font-family: Consolas, Monaco, 'Andale Mono', 'Ubuntu Mono', monospace;
    }
</style>
@stop

@section('js')
<script src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
<script>hljs.highlightAll();</script>
@stop 