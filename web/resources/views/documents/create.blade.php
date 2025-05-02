@extends('layouts.app')

@section('content')
<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <div class="card shadow border-0 rounded-4">
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-primary ps-2">
                            <i class="fas fa-file-upload me-2"></i> Tải lên tài liệu mới
                        </h5>
                        <div class="header-actions">
                            <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Quay lại
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4 p-lg-5">
                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        @csrf
                        
                        <div class="form-group custom-form-group mb-4">
                            <div class="input-group input-group-lg has-validation">
                                <span class="input-group-text bg-white text-primary border-end-0">
                                    
                                </span>
                                <div class="form-floating flex-grow-1">
                                    <input type="text" class="form-control form-control-lg border-start-0 @error('title') is-invalid @enderror" 
                                        id="title" name="title" value="{{ old('title') }}" 
                                        placeholder="Nhập tiêu đề tài liệu" required>
                                    <label for="title">Tiêu đề tài liệu <span class="text-danger">*</span></label>
                                </div>
                                @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                        </div>
                        
                        <div class="form-group custom-form-group mb-5">
                            <div class="input-group has-validation">
                                <span class="input-group-text bg-white text-primary border-end-0">
                                   
                                </span>
                                <div class="form-floating flex-grow-1">
                                    <textarea class="form-control border-start-0 @error('description') is-invalid @enderror" 
                                        id="description" name="description" style="height: 120px;" 
                                        placeholder="Mô tả ngắn gọn về nội dung tài liệu">{{ old('description') }}</textarea>
                                    <label for="description">Mô tả tài liệu</label>
                                </div>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-text text-muted">
                                Mô tả ngắn gọn về nội dung tài liệu (không bắt buộc)
                            </div>
                        </div>
                        
                        <div class="mb-5">
                            <label for="document_file" class="form-label fw-semibold fs-5 mb-2">
                                <i class="fas fa-file-alt me-1 text-primary"></i> Chọn tài liệu <span class="text-danger">*</span>
                            </label>
                            
                            <div class="upload-container rounded-4 mb-3 @error('document_file') border-danger @enderror">
                                <input type="file" class="upload-input" id="document_file" name="document_file" required accept=".pdf,.doc,.docx,.txt,.md">
                                <label for="document_file" class="upload-label mb-0">
                                    <div class="upload-label-content text-center">
                                        <div class="upload-icon mb-3">
                                            <i class="fas fa-cloud-upload-alt fa-4x text-primary opacity-75"></i>
                                        </div>
                                        <h6 class="file-name mb-3 text-truncate">Chưa có tệp tin nào được chọn</h6>
                                        <p class="text-muted mb-2">Kéo và thả tài liệu vào đây hoặc nhấp để chọn</p>
                                        <div class="mt-3">
                                            <span class="btn btn-sm btn-primary px-4">Chọn tệp tin</span>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            
                            @error('document_file')
                                <div class="text-danger small mb-2">{{ $message }}</div>
                            @enderror
                            
                            <div class="file-info mt-4">
                                <div class="alert alert-light border shadow-sm d-flex align-items-center py-3 mb-0 rounded-3">
                                    <div class="file-info-icon me-3 text-primary">
                                        <i class="fas fa-info-circle fa-2x"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1 fw-bold">Thông tin tệp tin hỗ trợ</h6>
                                        <p class="mb-2 small">Định dạng hỗ trợ:  <span class="badge bg-light text-dark border px-2 me-1">TXT</span>
                                        <p class="mb-0 small">Kích thước tối đa: <span class="badge bg-light text-dark border px-2">10MB</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid mt-5">
                            <button type="submit" class="btn btn-primary btn-lg py-3 rounded-3 shadow">
                                <i class="fas fa-cloud-upload-alt me-2"></i> Tải lên tài liệu
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .upload-container {
        position: relative;
        border: 2px dashed #dee2e6;
        transition: all 0.3s ease;
        background-color: #f8f9fa;
        overflow: hidden;
    }

    .upload-container:hover {
        border-color: #4098e5;
        background-color: rgba(64, 152, 229, 0.03);
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08);
    }
    
    .upload-container.drag-over {
        border-color: #4098e5;
        background-color: rgba(64, 152, 229, 0.05);
        box-shadow: 0 0.5rem 1.5rem rgba(64, 152, 229, 0.15);
    }
    
    .upload-input {
        position: absolute;
        width: 0.1px;
        height: 0.1px;
        opacity: 0;
        overflow: hidden;
        z-index: -1;
    }
    
    .upload-label {
        display: block;
        width: 100%;
        padding: 3rem;
        cursor: pointer;
        text-align: center;
    }
    
    .file-name {
        max-width: 80%;
        margin-left: auto;
        margin-right: auto;
        font-size: 1.1rem;
    }
    
    .btn-primary {
        background-color: #4098e5;
        border-color: #4098e5;
    }
    
    .btn-primary:hover {
        background-color: #3184d6;
        border-color: #3184d6;
    }
    
    .text-primary {
        color: #4098e5 !important;
    }
    
    .form-floating > .form-control,
    .form-floating > .form-control-plaintext {
        padding: 1rem 0.75rem;
    }
    
    .form-floating > .form-control-plaintext ~ label,
    .form-floating > .form-control:focus ~ label,
    .form-floating > .form-control:not(:placeholder-shown) ~ label {
        color: #4098e5;
        transform: scale(0.8) translateY(-0.5rem) translateX(0.15rem);
    }
    
    .form-floating > label {
        padding: 1rem 0.75rem;
    }
    
    .input-group-text {
        color: #4098e5;
    }
    
    .input-group:focus-within .input-group-text {
        border-color: #4098e5;
    }
    
    .form-control:focus {
        border-color: #4098e5;
        box-shadow: 0 0 0 0.25rem rgba(64, 152, 229, 0.25);
    }
    
    .form-control {
        transition: all 0.3s ease;
    }
    
    .form-control:hover:not(:focus) {
        border-color: #4098e5;
    }
    
    .border-primary {
        border-color: #4098e5 !important;
    }
    
    .custom-form-group {
        position: relative;
    }
    
    .custom-form-group .input-group {
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border-radius: 0.5rem;
        transition: all 0.3s ease;
    }
    
    .custom-form-group .input-group:focus-within {
        box-shadow: 0 5px 15px rgba(64, 152, 229, 0.1);
        transform: translateY(-2px);
    }
    
    .custom-form-group .form-control,
    .custom-form-group .input-group-text {
        border-color: #e9ecef;
    }
    
    .form-text {
        margin-top: 0.5rem;
        padding-left: 0.75rem;
    }
    
    .badge {
        font-weight: 500;
        font-size: 0.75rem;
    }

    @media (max-width: 767.98px) {
        .header-actions {
            margin-left: auto;
        }
        
        .card-header .d-flex {
            flex-direction: column;
            align-items: flex-start !important;
        }

        .card-header .header-actions {
            margin-top: 0.5rem;
            margin-left: 0;
        }

        .file-info-icon {
            display: none;
        }
        
        .upload-label {
            padding: 2rem 1rem;
        }
        
        .container-fluid {
            padding-left: 1.25rem;
            padding-right: 1.25rem;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('document_file');
        const fileName = document.querySelector('.file-name');
        const uploadContainer = document.querySelector('.upload-container');
        
        // Auto-focus vào trường title khi trang được tải
        document.getElementById('title').focus();
        
        // Hiển thị tên file khi chọn
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                fileName.textContent = file.name;
                uploadContainer.classList.add('border-primary');
                
                // Hiển thị kích thước file
                const fileSize = file.size / 1024 / 1024; // Convert to MB
                fileName.innerHTML = `<strong>${file.name}</strong><br><span class="text-muted small">${fileSize.toFixed(2)} MB</span>`;
                
                // Tự động đặt tiêu đề từ tên file nếu trường tiêu đề còn trống
                const title = document.getElementById('title');
                if (!title.value) {
                    title.value = file.name.split('.').slice(0, -1).join('.');
                }
            } else {
                fileName.textContent = 'Chưa có tệp tin nào được chọn';
                uploadContainer.classList.remove('border-primary');
            }
        });
        
        // Hiệu ứng kéo và thả
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadContainer.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                uploadContainer.classList.add('drag-over');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadContainer.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                uploadContainer.classList.remove('drag-over');
                
                if (eventName === 'drop') {
                    const files = e.dataTransfer.files;
                    if (files.length) {
                        fileInput.files = files;
                        const changeEvent = new Event('change');
                        fileInput.dispatchEvent(changeEvent);
                    }
                }
            }, false);
        });
        
        // Xác thực form
        const form = document.querySelector('.needs-validation');
        form.addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.classList.add('was-validated');
        });
    });
</script>
@endpush 