@extends('layouts.admin')

@section('title', 'Trạng thái hệ thống')

@section('content_header')
    <h1>Trạng thái hệ thống</h1>
@stop

@section('content')
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Trạng thái Chatbot API</h3>
                <div class="card-tools">
                    <a href="{{ route('admin.system.api-docs') }}" class="btn btn-sm btn-info mr-2">
                        <i class="fas fa-book"></i> Tài liệu API
                    </a>
                    <button type="button" class="btn btn-sm btn-tool" id="refresh-status">
                        <i class="fas fa-sync-alt"></i> Làm mới
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="status-indicator mb-4">
                    <div class="d-flex align-items-center">
                        <div class="status-badge mr-3 {{ $chatbotStatus['online'] ? 'bg-success' : 'bg-danger' }}" style="width: 20px; height: 20px; border-radius: 50%;"></div>
                        <h4 class="m-0">
                            @if($chatbotStatus['online'])
                                <span class="text-success">Online</span>
                            @else
                                <span class="text-danger">Offline</span>
                            @endif
                        </h4>
                    </div>
                    <p class="text-muted mt-2">{{ $chatbotStatus['message'] }}</p>
                </div>
                
                <table class="table table-bordered">
                    <tr>
                        <th style="width: 40%">API Version</th>
                        <td>{{ $chatbotStatus['api_version'] }}</td>
                    </tr>
                    <tr>
                        <th>Kiểm tra lần cuối</th>
                        <td>{{ $chatbotStatus['last_checked'] }}</td>
                    </tr>
                    @if($chatbotStatus['online'] && !empty($chatbotStatus['resources']))
                        @foreach($chatbotStatus['resources'] as $key => $value)
                        <tr>
                            <th>{{ ucfirst(str_replace('_', ' ', $key)) }}</th>
                            <td>
                                @if(is_bool($value))
                                    {!! $value ? '<span class="badge badge-success">Có</span>' : '<span class="badge badge-danger">Không</span>' !!}
                                @else
                                    {{ $value }}
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    @endif
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Kiểm tra Chatbot</h3>
            </div>
            <div class="card-body">
                <form id="test-chatbot-form">
                    <div class="form-group">
                        <label for="test-message">Nhập tin nhắn để kiểm tra:</label>
                        <textarea id="test-message" class="form-control" rows="3" placeholder="Nhập câu hỏi để kiểm tra chatbot..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" id="send-test">
                        <i class="fas fa-paper-plane"></i> Gửi tin nhắn
                    </button>
                </form>
                
                <div class="mt-4">
                    <h5>Kết quả:</h5>
                    <div id="test-result" class="p-3 bg-light mt-2" style="border-radius: 5px; min-height: 100px;">
                        <p class="text-muted">Kết quả sẽ hiển thị ở đây...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Refresh status
    document.getElementById('refresh-status').addEventListener('click', function() {
        window.location.reload();
    });
    
    // Test chatbot
    document.getElementById('test-chatbot-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const message = document.getElementById('test-message').value.trim();
        if (!message) return;
        
        const resultDiv = document.getElementById('test-result');
        resultDiv.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Đang xử lý...</div>';
        
        fetch('{{ route("admin.system.test-chatbot") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                message: message
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let result = '';
                if (data.result && data.result.response) {
                    result = data.result.response;
                } else if (data.result && data.result.answer) {
                    result = data.result.answer;
                } else {
                    result = JSON.stringify(data.result, null, 2);
                }
                
                resultDiv.innerHTML = `
                    <div class="mb-2">
                        <strong>Câu hỏi:</strong> 
                        <p>${message}</p>
                    </div>
                    <div>
                        <strong>Trả lời:</strong>
                        <p>${result}</p>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        ${data.message || 'Đã xảy ra lỗi khi kiểm tra chatbot.'}
                    </div>
                `;
            }
        })
        .catch(error => {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    Lỗi khi gửi yêu cầu: ${error.message}
                </div>
            `;
        });
    });
});
</script>
@stop 