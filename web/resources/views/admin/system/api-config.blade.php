@extends('layouts.admin')

@section('title', 'Cấu hình API')

@section('content')
<div class="row">
    <div class="col-md-12">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-hidden="true"></button>
                <h5><i class="icon fas fa-check"></i> Thành công!</h5>
                {{ session('success') }}
            </div>
        @endif
        
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-hidden="true"></button>
                <h5><i class="icon fas fa-ban"></i> Lỗi!</h5>
                {{ session('error') }}
            </div>
        @endif
    </div>
    
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Chọn Loại API</h3>
            </div>
            <div class="card-body">
                <form id="api-type-form" action="{{ route('admin.system.api-config') }}" method="GET" class="d-flex justify-content-center">
                    <div class="form-group mb-0 me-3" style="width: 300px;">
                        <select name="api_type" id="api-type-selector" class="form-control">
                            <option value="">-- Chọn Loại API --</option>
                            <option value="google" {{ request('api_type') == 'google' ? 'selected' : '' }}>Google (Gemini)</option>
                            <option value="openai" {{ request('api_type') == 'openai' ? 'selected' : '' }}>OpenAI (GPT)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Chuyển loại API</button>
                </form>
            </div>
        </div>
    </div>

    @if(request('api_type') == 'google')
    <!-- GOOGLE API CONFIG -->
    <div class="col-md-6">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Cấu hình Google API</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.system.update-api-config') }}" id="google-config-form">
                    @csrf
                    <input type="hidden" name="api_type" value="google">
                    
                    <div class="form-group mb-3">
                        <label>Google API Key</label>
                        <div class="input-group">
                            <input type="password" name="google_api_key" id="google_api_key" class="form-control" 
                                   placeholder="Nhập Google API Key" value="{{ $apiConfig['google_api_key'] }}" autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary toggle-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Google Model</label>
                        <select name="google_model" id="google_model" class="form-control">
                            @foreach($availableModels['google'] as $model => $label)
                                <option value="{{ $model }}" {{ $apiConfig['google_model'] == $model ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Lưu cấu hình</button>
                        <button type="button" class="btn btn-info float-end test-connection">Kiểm tra kết nối</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Thông tin Google API</h3>
            </div>
            <div class="card-body">
                <div class="callout callout-info">
                    <h5>API hiện tại: <span>Google</span></h5>
                    <p>Model đang dùng: <span>{{ $apiConfig['google_model'] }}</span></p>
                </div>
                
                <div class="mt-4">
                    <h5>Hướng dẫn cài đặt Google API:</h5>
                    <ol>
                        <li>Truy cập Google AI Studio</li>
                        <li>Đăng nhập bằng tài khoản Google của bạn</li>
                        <li>Vào phần <strong>Get API Key</strong></li>
                        <li>Tạo key mới hoặc sử dụng key hiện có</li>
                        <li>Sao chép API key và dán vào trường Google API Key</li>
                    </ol>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> Các model của Google Gemini có giới hạn sử dụng miễn phí khá cao, phù hợp cho việc thử nghiệm.
                    </div>
                </div>
                
                <div class="mt-4 connection-result" style="display: none;">
                    <h5>Kết quả kiểm tra kết nối:</h5>
                    <div class="connection-details p-3 rounded"></div>
                </div>
            </div>
        </div>
    </div>
    @elseif(request('api_type') == 'openai')
    <!-- OPENAI API CONFIG -->
    <div class="col-md-6">
        <div class="card card-success">
            <div class="card-header">
                <h3 class="card-title">Cấu hình OpenAI API</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.system.update-api-config') }}" id="openai-config-form">
                    @csrf
                    <input type="hidden" name="api_type" value="openai">
                    
                    <div class="form-group mb-3">
                        <label>OpenAI API Key</label>
                        <div class="input-group">
                            <input type="password" name="openai_api_key" id="openai_api_key" class="form-control" 
                                   placeholder="Nhập OpenAI API Key" value="{{ $apiConfig['openai_api_key'] }}" autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary toggle-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>OpenAI Model</label>
                        <select name="openai_model" id="openai_model" class="form-control">
                            @foreach($availableModels['openai'] as $model => $label)
                                <option value="{{ $model }}" {{ $apiConfig['openai_model'] == $model ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Lưu cấu hình</button>
                        <button type="button" class="btn btn-info float-end test-connection">Kiểm tra kết nối</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Thông tin OpenAI API</h3>
            </div>
            <div class="card-body">
                <div class="callout callout-info">
                    <h5>API hiện tại: <span>OpenAI</span></h5>
                    <p>Model đang dùng: <span>{{ $apiConfig['openai_model'] }}</span></p>
                </div>
                
                <div class="mt-4">
                    <h5>Hướng dẫn cài đặt OpenAI API:</h5>
                    <ol>
                        <li>Truy cập OpenAI Platform</li>
                        <li>Đăng nhập vào tài khoản OpenAI của bạn</li>
                        <li>Chọn <strong>Create new secret key</strong></li>
                        <li>Đặt tên cho key (để dễ quản lý)</li>
                        <li>Sao chép API key và dán vào trường OpenAI API Key</li>
                    </ol>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> OpenAI yêu cầu thông tin thanh toán cho hầu hết các model. Kiểm tra mức sử dụng để tránh chi phí không mong muốn.
                    </div>
                </div>
                
                <div class="mt-4 connection-result" style="display: none;">
                    <h5>Kết quả kiểm tra kết nối:</h5>
                    <div class="connection-details p-3 rounded"></div>
                </div>
            </div>
        </div>
    </div>
    @else
    <!-- Trang chọn loại API -->
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Vui lòng chọn loại API để tiếp tục</h3>
            </div>
            <div class="card-body text-center">
                <p class="mb-4">Chọn loại API bạn muốn cấu hình từ danh sách trên.</p>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="mb-3">Google Gemini</h5>
                                <p>API từ Google với các model Gemini hiện đại</p>
                                <p class="text-success">Miễn phí với giới hạn cao</p>
                                <a href="{{ route('admin.system.api-config', ['api_type' => 'google']) }}" class="btn btn-primary">Chọn Google API</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="mb-3">OpenAI GPT</h5>
                                <p>API từ OpenAI với các model GPT mạnh mẽ</p>
                                <p class="text-warning">Yêu cầu phương thức thanh toán</p>
                                <a href="{{ route('admin.system.api-config', ['api_type' => 'openai']) }}" class="btn btn-success">Chọn OpenAI API</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@stop

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Xử lý hiển thị/ẩn mật khẩu
    var toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var input = this.parentNode.querySelector('input');
            var icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    // Xử lý kiểm tra kết nối API
    var testButtons = document.querySelectorAll('.test-connection');
    testButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var connectionResult = this.closest('.card').parentNode.querySelector('.connection-result');
            var connectionDetails = connectionResult.querySelector('.connection-details');
            
            connectionDetails.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Đang kiểm tra kết nối...</div>';
            connectionResult.style.display = 'block';
            
            // Gửi request kiểm tra kết nối
            fetch('{{ route('admin.system.test-api-connection') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    connectionDetails.innerHTML = `
                        <div class="alert alert-success">
                            <strong><i class="fas fa-check-circle"></i> ${data.message}</strong>
                            <div class="mt-2">
                                <p><strong>API:</strong> ${data.data.api_type}</p>
                                <p><strong>Model:</strong> ${data.data.model}</p>
                                <p><strong>Trạng thái:</strong> ${data.data.status}</p>
                            </div>
                        </div>
                    `;
                } else {
                    connectionDetails.innerHTML = `
                        <div class="alert alert-danger">
                            <strong><i class="fas fa-times-circle"></i> ${data.message}</strong>
                        </div>
                    `;
                }
            })
            .catch(error => {
                connectionDetails.innerHTML = `
                    <div class="alert alert-danger">
                        <strong><i class="fas fa-times-circle"></i> Lỗi kết nối:</strong> ${error.message}
                    </div>
                `;
            });
        });
    });
});
</script>
@stop 