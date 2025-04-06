@extends('layouts.admin')

@section('title', 'Giám sát hoạt động hệ thống')

@section('content_header')
    <h1>Giám sát hoạt động hệ thống</h1>
@stop

@section('content')
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Bảng điều khiển giám sát Chatbot</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-primary" id="refresh-monitor">
                        <i class="fas fa-sync-alt"></i> Làm mới
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 col-sm-6 col-12">
                        <div class="info-box">
                            <span class="info-box-icon bg-info"><i class="far fa-file-alt"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Tệp tạm thời</span>
                                <span class="info-box-number">{{ count($tempFiles) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6 col-12">
                        <div class="info-box">
                            <span class="info-box-icon bg-success"><i class="fas fa-database"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Vector DB</span>
                                <span class="info-box-number">{{ count($vectorDbFiles) }} tệp</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6 col-12">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Thời gian cập nhật</span>
                                <span class="info-box-number" id="update-time">{{ now()->format('H:i:s') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-alt mr-1"></i>
                    Tệp tạm thời (Temporary Files)
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-danger" id="delete-temp-files">
                        <i class="fas fa-trash"></i> Xóa tất cả
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tên file</th>
                            <th>Kích thước</th>
                            <th>Thời gian</th>
                        </tr>
                    </thead>
                    <tbody id="temp-files-table">
                        @forelse($tempFiles as $file)
                        <tr>
                            <td>{{ $file['name'] }}</td>
                            <td>{{ $file['size'] }}</td>
                            <td>{{ $file['modified'] }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center">Không có tệp tạm thời</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-database mr-1"></i>
                    Vector Database Files
                </h3>
            </div>
            <div class="card-body p-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tên file</th>
                            <th>Kích thước</th>
                            <th>Thời gian</th>
                        </tr>
                    </thead>
                    <tbody id="vector-db-table">
                        @forelse($vectorDbFiles as $file)
                        <tr>
                            <td>{{ $file['name'] }}</td>
                            <td>{{ $file['size'] }}</td>
                            <td>{{ $file['modified'] }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center">Không tìm thấy dữ liệu vector database</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Thông báo kết quả -->
<div id="result-alert" class="alert" style="display: none; position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>
@stop

@section('css')
<style>
.log-container {
    background-color: #1e1e1e;
    color: #f1f1f1;
    font-family: 'Courier New', monospace;
    height: 300px;
    overflow-y: scroll;
    font-size: 12px;
    padding: 5px;
    scrollbar-width: thin;
    scrollbar-color: #666 #1e1e1e;
}

.log-container::-webkit-scrollbar {
    width: 8px;
}

.log-container::-webkit-scrollbar-track {
    background: #1e1e1e;
}

.log-container::-webkit-scrollbar-thumb {
    background: #666;
    border-radius: 4px;
}

.log-container::-webkit-scrollbar-thumb:hover {
    background: #888;
}

.log-line {
    padding: 2px 5px;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.log-error {
    color: #ff6b6b;
}

.log-warning {
    color: #feca57;
}
</style>
@stop

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hiển thị thông báo
    function showAlert(message, type) {
        const alertBox = document.getElementById('result-alert');
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = message;
        alertBox.style.display = 'block';
        
        // Tự động ẩn sau 3 giây
        setTimeout(function() {
            alertBox.style.display = 'none';
        }, 3000);
    }
    
    // Scroll logs to bottom
    const scrollToBottom = (elementId) => {
        const element = document.getElementById(elementId);
        if (element) {
            element.scrollTop = element.scrollHeight;
        }
    };
    
    scrollToBottom('laravel-logs');
    
    // Refresh page - Đã sửa để sử dụng fetch thay vì window.location.reload()
    document.getElementById('refresh-monitor').addEventListener('click', function() {
        // Hiển thị hiệu ứng loading
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang làm mới...';
        
        // Sử dụng fetch để lấy dữ liệu mới
        fetch('{{ route("admin.system.monitor") }}')
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // Cập nhật dữ liệu
                document.getElementById('temp-files-table').innerHTML = doc.getElementById('temp-files-table').innerHTML;
                document.getElementById('vector-db-table').innerHTML = doc.getElementById('vector-db-table').innerHTML;
                document.getElementById('laravel-logs').innerHTML = doc.getElementById('laravel-logs').innerHTML;
                
                // Cập nhật thời gian
                document.getElementById('update-time').textContent = new Date().toLocaleTimeString();
                
                // Cuộn xuống cuối log
                scrollToBottom('laravel-logs');
                
                // Khôi phục nút
                this.innerHTML = '<i class="fas fa-sync-alt"></i> Làm mới';
                
                // Hiển thị thông báo
                showAlert('Đã cập nhật dữ liệu thành công!', 'success');
            })
            .catch(error => {
                console.error('Error refreshing data:', error);
                this.innerHTML = '<i class="fas fa-sync-alt"></i> Làm mới';
                showAlert('Lỗi khi làm mới dữ liệu!', 'danger');
            });
    });
    
    // Xóa tất cả tệp tạm thời
    document.getElementById('delete-temp-files').addEventListener('click', function() {
        if (!confirm('Bạn có chắc chắn muốn xóa tất cả tệp tạm thời không?')) {
            return;
        }
        
        // Hiển thị hiệu ứng loading
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xóa...';
        this.disabled = true;
        
        fetch('{{ route("admin.system.delete-temp-files") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            // Khôi phục nút
            this.innerHTML = '<i class="fas fa-trash"></i> Xóa tất cả';
            this.disabled = false;
            
            if (data.success) {
                // Cập nhật bảng dữ liệu
                document.getElementById('temp-files-table').innerHTML = '<tr><td colspan="3" class="text-center">Không có tệp tạm thời</td></tr>';
                
                // Hiển thị thông báo
                showAlert(data.message, 'success');
            } else {
                // Hiển thị lỗi
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error deleting files:', error);
            this.innerHTML = '<i class="fas fa-trash"></i> Xóa tất cả';
            this.disabled = false;
            showAlert('Đã xảy ra lỗi khi xóa tệp!', 'danger');
        });
    });
    
    // Refresh Laravel logs
    document.getElementById('refresh-laravel-logs').addEventListener('click', function() {
        fetch('{{ route("admin.system.monitor") }}')
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                document.getElementById('laravel-logs').innerHTML = doc.getElementById('laravel-logs').innerHTML;
                scrollToBottom('laravel-logs');
                document.getElementById('update-time').textContent = new Date().toLocaleTimeString();
            })
            .catch(error => console.error('Error refreshing logs:', error));
    });
    
    // Auto-refresh logs every 60 seconds
    setInterval(function() {
        document.getElementById('refresh-laravel-logs').click();
    }, 60000);
});
</script>
@stop 