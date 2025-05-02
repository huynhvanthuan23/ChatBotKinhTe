@extends('layouts.app')

@section('content')
<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow border-0 rounded-4">
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-primary ps-2">
                            <i class="fas fa-file-alt me-2"></i> Chi tiết tài liệu
                        </h5>
                        <div class="action-buttons">
                            <a href="{{ route('documents.index') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Quay lại
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4 p-lg-5">
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <div class="document-header mb-4 border-bottom pb-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="document-title mb-2 fw-bold">{{ $document->title }}</h3>
                                @if($document->description)
                                    <p class="text-muted mb-0">{{ $document->description }}</p>
                                @endif
                            </div>
                            <div class="document-actions ms-3">
                                <div class="btn-group shadow-sm">
                                    <a href="{{ asset('storage/' . $document->file_path) }}" class="btn btn-primary" download="{{ $document->file_name }}">
                                        <i class="fas fa-download me-2 "></i> Tải xuống
                                    </a>
                                   
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-4 mb-4 mb-lg-0">
                            <!-- Thông tin tài liệu -->
                            <div class="card h-100 border-0 shadow-sm rounded-3">
                                <div class="card-header bg-light py-3 border-0">
                                    <h6 class="mb-0 fw-bold">
                                        <i class="fas fa-info-circle me-2 text-primary"></i> Thông tin tài liệu
                                    </h6>
                                </div>
                                <div class="card-body p-0">
                                    <ul class="list-group list-group-flush rounded-3">
                                        <li class="list-group-item d-flex justify-content-between px-4 py-3 border-0 border-bottom">
                                            <span class="text-muted">Tên file:</span>
                                            <span class="text-truncate ms-2 fw-medium" style="max-width: 200px;">{{ $document->file_name }}</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between px-4 py-3 border-0 border-bottom">
                                            <span class="text-muted">Loại file:</span>
                                            @php
                                                $extension = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));
                                                $badgeClass = 'bg-secondary';
                                                
                                                if ($extension == 'pdf') {
                                                    $badgeClass = 'bg-danger';
                                                } elseif (in_array($extension, ['doc', 'docx'])) {
                                                    $badgeClass = 'bg-primary';
                                                } elseif ($extension == 'txt') {
                                                    $badgeClass = 'bg-success';
                                                } elseif ($extension == 'md') {
                                                    $badgeClass = 'bg-warning';
                                                }
                                            @endphp
                                            <span class="badge {{ $badgeClass }} rounded-pill px-3 py-2">{{ strtoupper($extension) }}</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between px-4 py-3 border-0 border-bottom">
                                            <span class="text-muted">Kích thước:</span>
                                            <span class="fw-medium">{{ $document->human_file_size }}</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between px-4 py-3 border-0">
                                            <span class="text-muted">Ngày tải lên:</span>
                                            <span class="fw-medium">{{ $document->created_at->format('d/m/Y H:i') }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-8">
                            <!-- Xem trước nội dung -->
                            <div class="card border-0 shadow-sm rounded-3">
                                <div class="card-header bg-light py-3 border-0">
                                    <h6 class="mb-0 fw-bold">
                                        <i class="fas fa-eye me-2 text-primary"></i> Xem nội dung
                                    </h6>
                                </div>
                                <div class="card-body p-0 rounded-bottom-3">
                                    @php
                                        $extension = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));
                                    @endphp
                                    
                                    @if($extension == 'pdf')
                                        <div class="ratio ratio-16x9" style="max-height: 600px; border-radius: 0 0 0.5rem 0.5rem; overflow: hidden;">
                                            <embed src="{{ asset('storage/' . $document->file_path) }}" type="application/pdf" width="100%" height="100%">
                                        </div>
                                    @elseif(in_array($extension, ['doc', 'docx']))
                                        <div class="p-5 text-center">
                                            <i class="fas fa-file-word fa-5x text-primary mb-4 opacity-75"></i>
                                            <h5 class="fw-bold mb-3">Tài liệu Word không thể xem trực tiếp</h5>
                                            <p class="text-muted mb-4">Vui lòng tải xuống để xem nội dung đầy đủ</p>
                                            <a href="{{ asset('storage/' . $document->file_path) }}" class="btn btn-primary px-4 py-2 rounded-3" download="{{ $document->file_name }}">
                                                <i class="fas fa-download me-2"></i> Tải xuống tài liệu
                                            </a>
                                        </div>
                                    @elseif(in_array($extension, ['txt', 'md']))
                                        <div class="document-content p-4" style="max-height: 600px; overflow-y: auto;">
                                            @php
                                                $filePath = storage_path('app/public/' . $document->file_path);
                                                if (file_exists($filePath)) {
                                                    $content = file_get_contents($filePath);
                                                    if ($extension == 'md') {
                                                        echo '<pre class="bg-light p-4 rounded-3 shadow-sm" style="font-family: var(--bs-font-monospace); font-size: 0.9rem; line-height: 1.5;">' . htmlspecialchars($content) . '</pre>';
                                                    } else {
                                                        echo '<pre class="bg-light p-4 rounded-3 shadow-sm" style="font-family: var(--bs-font-monospace); font-size: 0.9rem; line-height: 1.5;">' . htmlspecialchars($content) . '</pre>';
                                                    }
                                                } else {
                                                    echo '<div class="alert alert-warning m-4">Không thể đọc nội dung tệp tin</div>';
                                                }
                                            @endphp
                                        </div>
                                    @else
                                        <div class="p-5 text-center">
                                            <i class="fas fa-file fa-5x text-secondary mb-4 opacity-75"></i>
                                            <h5 class="fw-bold mb-3">Không thể xem trước loại tài liệu này</h5>
                                            <p class="text-muted mb-4">Vui lòng tải xuống để xem nội dung đầy đủ</p>
                                            <a href="{{ asset('storage/' . $document->file_path) }}" class="btn btn-primary px-4 py-2 rounded-3" download="{{ $document->file_name }}">
                                                <i class="fas fa-download me-2"></i> Tải xuống tài liệu
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            
            
            <div class="modal-footer border-top-0">
                
                <form action="{{ route('documents.destroy', $document->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger px-5 py-3 fs-5 rounded-3">Xác nhận xóa</button>


                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .document-title {
        word-break: break-word;
        color: #333;
    }
    
    .list-group-item {
        transition: all 0.2s ease;
    }
    
    .list-group-item:hover {
        background-color: rgba(64, 152, 229, 0.03);
    }
    
    .btn-primary {
        background-color: #4098e5;
        border-color: #4098e5;
    }
    
    .btn-primary:hover {
        background-color: #3184d6;
        border-color: #3184d6;
    }
    
    .btn-info {
        background-color: #17a2b8;
        border-color: #17a2b8;
    }
    
    .btn-info:hover {
        background-color: #138496;
        border-color: #138496;
    }
    
    .text-primary {
        color: #4098e5 !important;
    }
    
    .bg-light {
        background-color: #f8f9fa !important;
    }
    
    .btn-group .btn {
        padding: 0.6rem 1.2rem;
        font-weight: 500;
    }
    
    .card {
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .card:hover {
        box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08) !important;
    }
    
    .card-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }
    
    .document-content pre {
        white-space: pre-wrap;
        word-wrap: break-word;
    }
    
    .fw-medium {
        font-weight: 500;
    }
    
    .rounded-3 {
        border-radius: 0.5rem !important;
    }
    
    .rounded-4 {
        border-radius: 0.75rem !important;
    }
    
    .rounded-pill {
        border-radius: 50rem !important;
    }
    
    .rounded-bottom-3 {
        border-bottom-right-radius: 0.5rem !important;
        border-bottom-left-radius: 0.5rem !important;
    }
    
    @media (max-width: 767.98px) {
        .document-header {
            flex-direction: column;
        }
        
        .document-actions {
            margin-top: 1rem;
            margin-left: 0 !important;
            width: 100%;
        }
        
        .btn-group {
            display: flex;
            width: 100%;
        }
        
        .btn-group .btn {
            flex: 1;
            padding: 0.5rem;
            font-size: 0.9rem;
        }
        
        .btn-group .btn i {
            margin-right: 0 !important;
        }
        
        .card-header .d-flex {
            flex-direction: column;
            align-items: flex-start !important;
        }
        
        .card-header .action-buttons {
            margin-top: 0.5rem;
            align-self: flex-start;
        }
        
        .document-header {
            text-align: center;
        }
        
        .document-header .d-flex {
            flex-direction: column;
            align-items: center !important;
        }
    }
</style>
@endpush 