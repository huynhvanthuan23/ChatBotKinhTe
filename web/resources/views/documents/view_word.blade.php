@extends('layouts.app')

@section('content')
<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            <div class="card shadow border-0 rounded-4">
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-primary ps-2">
                            <i class="fas fa-file-word me-2"></i> {{ $document->title }}
                        </h5>
                        <div class="action-buttons">
                            <a href="{{ route('documents.show', $document->id) }}" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Quay lại
                            </a>
                            <!-- <a href="{{ route('documents.reload-cache', $document->id) }}" class="btn btn-info ms-2">
                                <i class="fas fa-sync-alt me-1"></i> Làm mới cache
                            </a> -->
                            <a href="{{ asset('storage/' . $document->file_path) }}" class="btn btn-primary ms-2" download="{{ $document->file_name }}">
                                <i class="fas fa-download me-1"></i> Tải xuống
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    @if(empty(trim(strip_tags(str_replace(['<div', '</div>', '<p></p>'], '', $htmlContent)))))
                        <div class="p-5 text-center">
                            <i class="fas fa-exclamation-circle fa-4x text-warning mb-4"></i>
                            <h4 class="mb-3">Không thể hiển thị nội dung tài liệu</h4>
                            <p class="text-muted mb-4">Tài liệu có thể có định dạng đặc biệt hoặc cấu trúc không được hỗ trợ để xem trực tiếp.</p>
                            <a href="{{ asset('storage/' . $document->file_path) }}" class="btn btn-primary px-4 py-2 rounded-3" download="{{ $document->file_name }}">
                                <i class="fas fa-download me-2"></i> Tải xuống tài liệu để xem
                            </a>
                        </div>
                    @else
                        <div class="document-viewer bg-white overflow-auto">
                            <div class="p-4 p-lg-5 word-document phpword-content">
                                {!! $htmlContent !!}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .document-viewer {
        min-height: 300px;
        max-height: 800px;
        border-radius: 0 0 0.5rem 0.5rem;
    }
    
    .document-viewer img {
        max-width: 100%;
        height: auto;
    }
    
    .document-viewer table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    
    .document-viewer table, .document-viewer th, .document-viewer td {
        border: 1px solid #ddd;
    }
    
    .document-viewer th, .document-viewer td {
        padding: 8px;
        text-align: left;
    }
    
    .document-viewer h1, .document-viewer h2, .document-viewer h3, .document-viewer h4 {
        color: #4098e5;
        margin-top: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .document-viewer p {
        margin-bottom: 0.5rem;
        line-height: 1.6;
    }
    
    .document-viewer ul, .document-viewer ol {
        padding-left: 2rem;
        margin-bottom: 1rem;
    }
    
    /* Định dạng cho nội dung Word */
    .word-document {
        font-family: 'Segoe UI', Arial, sans-serif;
        line-height: 1.6;
        color: #333;
    }
    
    /* Định dạng thêm cho PHPWord output */
    .phpword-content table {
        border-collapse: collapse;
        width: 100%;
    }
    
    .phpword-content td, .phpword-content th {
        border: 1px solid #ddd;
        padding: 8px;
    }
    
    .phpword-content tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    
    .phpword-content tr:hover {
        background-color: #f1f1f1;
    }
    
    .phpword-content th {
        padding-top: 12px;
        padding-bottom: 12px;
        text-align: left;
        background-color: #f2f2f2;
        color: #333;
    }
    
    /* Cải thiện bố cục tài liệu Word */
    .phpword-content p:empty { display: none; }
    .phpword-content p.MsoNormal { margin-bottom: 0.3rem; }
    .phpword-content p:has(br:only-child) { margin-bottom: 0; }
    .phpword-content span.MsoHyperlink { color: #0563c1; text-decoration: underline; }
    
    /* Giảm khoảng cách giữa các đoạn */
    .phpword-content p + p { margin-top: 0.3rem; }
    
    /* Cải thiện hiển thị trên điện thoại */
    @media (max-width: 768px) {
        .document-viewer {
            max-height: 600px;
        }
        
        .card-header .d-flex {
            flex-direction: column;
            gap: 10px;
        }
        
        .action-buttons {
            display: flex;
            width: 100%;
            gap: 10px;
        }
        
        .action-buttons a {
            flex: 1;
        }
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/docx-citation.js') }}"></script>
@endpush 