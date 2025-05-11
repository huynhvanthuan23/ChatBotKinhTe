@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-11">
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

                <div class="card-body p-3 p-lg-4">
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

                    <div class="document-header mb-3 border-bottom pb-3">
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
                        <div class="col-lg-3 mb-4 mb-lg-0">
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
                                            <span class="text-truncate ms-2 fw-medium" style="max-width: 150px;">{{ $document->file_name }}</span>
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
                        
                        <div class="col-lg-9">
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
                                        <div style="height: 700px; border-radius: 0 0 0.5rem 0.5rem; overflow: hidden;">
                                            <embed src="{{ asset('storage/' . $document->file_path) }}" type="application/pdf" width="100%" height="100%">
                                        </div>
                                    @elseif(in_array($extension, ['doc', 'docx']))
                                        @if($extension == 'docx')
                                            <div style="position: relative;">
                                                <div style="height: 700px; border-radius: 0 0 0.5rem 0.5rem; overflow: hidden;">
                                                    <iframe src="{{ route('documents.view', $document->id) }}" frameborder="0" width="100%" height="100%"></iframe>
                                                </div>
                                                <div style="position: absolute; bottom: 10px; right: 10px; z-index: 100; background-color: rgba(255,255,255,0.7); padding: 5px; border-radius: 5px;">
                                                    <!-- <a href="{{ route('documents.reload-cache', $document->id) }}" class="btn btn-sm btn-info rounded-pill me-2" title="Làm mới cache">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </a> -->
                                                    <a href="{{ asset('storage/' . $document->file_path) }}" class="btn btn-sm btn-primary rounded-pill" download="{{ $document->file_name }}" title="Tải xuống">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        @else
                                            <div class="p-5 text-center">
                                                <i class="fas fa-file-word fa-5x text-primary mb-4 opacity-75"></i>
                                                <h5 class="fw-bold mb-3">Tài liệu Word (.doc) không thể xem trực tiếp</h5>
                                                <p class="text-muted mb-4">Vui lòng tải xuống để xem nội dung đầy đủ</p>
                                                <a href="{{ asset('storage/' . $document->file_path) }}" class="btn btn-primary px-4 py-2 rounded-3" download="{{ $document->file_name }}">
                                                    <i class="fas fa-download me-2"></i> Tải xuống tài liệu
                                                </a>
                                            </div>
                                        @endif
                                    @elseif(in_array($extension, ['txt', 'md']))
                                        <div class="document-content p-4" style="height: 700px; overflow-y: auto;">
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
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-danger" id="deleteModalLabel">
                    <i class="fas fa-trash-alt me-2"></i> Xác nhận xóa tài liệu
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body pt-0 pb-3">
                <p class="mb-0 text-center fs-5">Bạn có chắc chắn muốn xóa tài liệu này không?</p>
                <p class="mb-0 text-center text-muted">Hành động này không thể hoàn tác.</p>
            </div>
            
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-secondary px-4 py-2" data-bs-dismiss="modal">Hủy bỏ</button>
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
    
    .docx-viewer {
        position: relative;
    }
    
    .document-controls {
        position: absolute;
        bottom: 10px;
        right: 10px;
        z-index: 100;
        padding: 5px 10px;
        background-color: rgba(255, 255, 255, 0.8);
        border-radius: 20px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
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
    
    /* Citation highlight styles */
    .highlighted-text {
        background-color: #ffffa0;
        padding: 3px;
        border-radius: 3px;
        border: 1px solid #ffd700;
        box-shadow: 0 0 5px rgba(255, 215, 0, 0.5);
        animation: highlight-pulse 2s infinite;
    }
    
    @keyframes highlight-pulse {
        0%, 100% {
            background-color: #ffffa0;
            box-shadow: 0 0 5px rgba(255, 215, 0, 0.5);
        }
        50% {
            background-color: #fff8d9;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.7);
        }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Lấy tham số page và highlight từ URL nếu có
        const urlParams = new URLSearchParams(window.location.search);
        const page = urlParams.get('page');
        const highlight = urlParams.get('highlight');
        
        if (page && highlight) {
            console.log('[CITATION-HIGHLIGHT] Xử lý tham số trích dẫn: page=' + page + ', highlight=' + highlight);
            
            // Tạo banner thông báo trích dẫn
            createCitationBanner(page, highlight);
            
            // Kiểm tra loại tài liệu và áp dụng xử lý phù hợp
            const embedElement = document.querySelector('embed[type="application/pdf"]');
            const iframeElement = document.querySelector('iframe');
            const textContent = document.querySelector('.document-content pre');
            
            if (embedElement) {
                // PDF file
                processPdfCitation(embedElement, page);
            } else if (iframeElement) {
                // DOCX file
                processDocxCitation(iframeElement, page, highlight);
            } else if (textContent) {
                // Text file (txt, md)
                processTextCitation(textContent, page, highlight);
            }
        } else {
            console.log('[CITATION-HIGHLIGHT] Không có tham số trích dẫn trong URL');
        }
        
        // Tạo banner thông báo trích dẫn ở đầu trang
        function createCitationBanner(page, section) {
            const cardBody = document.querySelector('.card-body');
            if (!cardBody) return;
            
            const citationBanner = document.createElement('div');
            citationBanner.className = 'alert alert-primary alert-dismissible fade show mb-3';
            citationBanner.role = 'alert';
            citationBanner.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-quote-left fs-4 me-3 text-primary"></i>
                    <div>
                        <h5 class="mb-1 fw-bold">Đang xem trích dẫn</h5>
                        <p class="mb-0">Trang ${page}, đoạn ${section}</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            cardBody.insertBefore(citationBanner, cardBody.firstChild);
        }
        
        // Xử lý trích dẫn cho PDF
        function processPdfCitation(embedElement, page) {
            // Cố gắng chuyển đến trang cụ thể
            const currentSrc = embedElement.src;
            if (!currentSrc.includes('#page=')) {
                embedElement.src = currentSrc + '#page=' + page;
                console.log('[CITATION-HIGHLIGHT] Đã chuyển PDF đến trang ' + page);
            }
        }
        // Xử lý trích dẫn cho file văn bản
        function processCitationForText() {
            const textContent = document.querySelector('.document-content pre');
            if (!textContent) return;
            
            console.log('[CITATION-HIGHLIGHT] Xử lý trích dẫn cho file văn bản');
            showCitationAlert('text');
            requestCitationContent();
        }
        
        // Xử lý trích dẫn cho DOCX
        function processDocxCitation(iframeElement, page, highlight) {
            console.log('[CITATION-HIGHLIGHT] Đã tìm thấy iframe cho tài liệu Word');
            
            // Lấy nội dung trích dẫn để hiển thị
            requestCitationContent();
        }
        
        // Xử lý trích dẫn cho file Text
        function processTextCitation(textElement, page, highlight) {
            console.log('[CITATION-HIGHLIGHT] Đã tìm thấy nội dung văn bản');
            
            // Lấy nội dung trích dẫn để hiển thị và highlight
            requestCitationContent();
        }
        
        // Lấy nội dung trích dẫn từ API
        function requestCitationContent() {
            const contentContainer = document.querySelector('.card-body');
            if (!contentContainer) return;
            
            // Thêm thông báo đang tải
            const loadingInfo = document.createElement('div');
            loadingInfo.className = 'alert alert-info text-center mb-3';
            loadingInfo.id = 'citation-loading';
            loadingInfo.innerHTML = `
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                    <span class="visually-hidden">Đang tải...</span>
                </div>
                <span>Đang tải nội dung trích dẫn...</span>
            `;
            
            contentContainer.insertBefore(loadingInfo, contentContainer.querySelector('.card'));
            
            // Gọi API để lấy nội dung trích dẫn
            fetch(`/api/citation/{{ $document->id }}/${page}`)
                .then(response => response.json())
                .then(data => {
                    // Xóa thông báo đang tải
                    document.getElementById('citation-loading')?.remove();
                    
                    if (data.success && data.content) {
                        console.log('[CITATION-HIGHLIGHT] Nhận được nội dung trích dẫn thành công');
                        
                        // Tạo hộp hiển thị trích dẫn
                        const citationBox = document.createElement('div');
                        citationBox.className = 'card shadow-sm mb-4 border-warning';
                        citationBox.innerHTML = `
                            <div class="card-header bg-warning bg-opacity-10 py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-quote-left me-2"></i> Nội dung trích dẫn (Trang ${page})
                                </h5>
                            </div>
                            <div class="card-body highlighted-text">
                                ${data.content}
                            </div>
                        `;
                        
                        // Thêm vào trang
                        contentContainer.insertBefore(citationBox, contentContainer.querySelector('.card'));
                        
                        // Hiển thị thông báo trích dẫn
                        if (!document.querySelector('.citation-banner')) {
                            createCitationBanner(page, 'n/a');
                        }
                        
                        // Highlight nội dung trong tài liệu gốc nếu là file text
                        const textContent = document.querySelector('.document-content pre');
                        if (textContent) {
                            highlightTextInDocument(data.content, textContent);
                        }
                    } else {
                        console.log('[CITATION-HIGHLIGHT] Không nhận được nội dung trích dẫn');
                        // Hiển thị thông báo không tìm thấy
                        const notFoundAlert = document.createElement('div');
                        notFoundAlert.className = 'alert alert-warning mb-3';
                        notFoundAlert.innerHTML = `
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Không thể tìm thấy nội dung chính xác của trích dẫn
                        `;
                        contentContainer.insertBefore(notFoundAlert, contentContainer.querySelector('.card'));
                    }
                })
                .catch(error => {
                    console.error('[CITATION-HIGHLIGHT] Lỗi khi lấy nội dung trích dẫn:', error);
                    // Xóa thông báo đang tải
                    document.getElementById('citation-loading')?.remove();
                    
                    // Hiển thị thông báo lỗi
                    const errorAlert = document.createElement('div');
                    errorAlert.className = 'alert alert-danger mb-3';
                    errorAlert.innerHTML = `
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Có lỗi xảy ra khi tải nội dung trích dẫn
                    `;
                    contentContainer.insertBefore(errorAlert, contentContainer.querySelector('.card'));
                });
        }
        
        // Highlight text trong tài liệu gốc
        function highlightTextInDocument(htmlContent, textElement) {
            try {
                // Convert HTML to plain text
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = htmlContent;
                const plainText = tempDiv.textContent || tempDiv.innerText;
                
                if (!plainText || plainText.length < 10) return;
                
                // Get an excerpt for matching (first 50 characters)
                const searchText = plainText.substring(0, 50).trim();
                // Escape text for regex
                const escapedText = searchText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                
                // Get document text
                const documentText = textElement.textContent || textElement.innerText;
                
                // Try to find the text
                const regex = new RegExp(`(${escapedText}.{0,150})`, 'i');
                const match = documentText.match(regex);
                
                if (match && match[0]) {
                    console.log('[CITATION-HIGHLIGHT] Tìm thấy đoạn trích dẫn trong tài liệu gốc');
                    
                    // Thay thế phương pháp highlight để tránh lỗi với thẻ pre
                    // Tạo một container mới để chứa nội dung có highlight
                    const container = document.createElement('div');
                    container.className = 'document-content-wrapper';
                    
                    // Chia văn bản thành 3 phần: trước, đoạn match, và sau
                    const matchIndex = documentText.indexOf(match[0]);
                    const textBefore = documentText.substring(0, matchIndex);
                    const textAfter = documentText.substring(matchIndex + match[0].length);
                    
                    // Tạo thẻ pre mới với nội dung đã phân đoạn
                    const newPre = document.createElement('pre');
                    newPre.className = textElement.className;
                    newPre.style = textElement.style.cssText;
                    
                    // Phần trước đoạn match
                    const beforeSpan = document.createElement('span');
                    beforeSpan.textContent = textBefore;
                    newPre.appendChild(beforeSpan);
                    
                    // Đoạn match với highlight
                    const highlightSpan = document.createElement('span');
                    highlightSpan.id = 'citation-highlight';
                    highlightSpan.className = 'bg-warning p-1 rounded';
                    highlightSpan.style.backgroundColor = '#fff3cd';
                    highlightSpan.style.padding = '2px 0';
                    highlightSpan.style.borderRadius = '3px';
                    highlightSpan.style.border = '1px solid #ffeeba';
                    highlightSpan.style.display = 'inline';
                    highlightSpan.textContent = match[0];
                    newPre.appendChild(highlightSpan);
                    
                    // Phần sau đoạn match
                    const afterSpan = document.createElement('span');
                    afterSpan.textContent = textAfter;
                    newPre.appendChild(afterSpan);
                    
                    // Thay thế phần tử cũ bằng phần tử mới
                    textElement.parentNode.replaceChild(newPre, textElement);
                    
                    // Scroll tới phần highlight
                    setTimeout(() => {
                        document.getElementById('citation-highlight')?.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }, 500);
                } else {
                    console.log('[CITATION-HIGHLIGHT] Không tìm thấy đoạn trích dẫn trong tài liệu gốc');
                }
            } catch (e) {
                console.error('[CITATION-HIGHLIGHT] Lỗi khi highlight text:', e);
            }
        }
        
//         // Hàm hiển thị thông báo trích dẫn theo loại tài liệu
//         function showCitationAlert(docType) {
//             const contentContainer = document.querySelector('.card-body');
//             if (!contentContainer) return;
            
//             // Chỉ thêm thông báo nếu chưa có
//             if (document.querySelector('.citation-banner')) return;
            
//             const citationBanner = document.createElement('div');
//             citationBanner.className = 'alert alert-primary alert-dismissible fade show mb-3 citation-banner';
//             citationBanner.role = 'alert';
            
//             let message = '';
//             if (docType === 'pdf') {
//                 message = `
//                     <div class="d-flex align-items-center">
//                         <i class="fas fa-file-pdf fs-4 me-3 text-danger"></i>
//                         <div>
//                             <h5 class="mb-1 fw-bold">Đang xem trích dẫn từ tài liệu PDF</h5>
//                             <p class="mb-0">Trang ${page} đã được tự động chọn. Nội dung trích dẫn được hiển thị bên dưới.</p>
//                         </div>
//                     </div>
//                 `;
//             } else if (docType === 'docx') {
//                 message = `
//                     <div class="d-flex align-items-center">
//                         <i class="fas fa-file-word fs-4 me-3 text-primary"></i>
//                         <div>
//                             <h5 class="mb-1 fw-bold">Đang xem trích dẫn từ tài liệu Word</h5>
//                             <p class="mb-0">Nội dung trích dẫn từ trang ${page} được hiển thị bên dưới.</p>
//                         </div>
//                     </div>
//                 `;
//             } else {
//                 message = `
//                     <div class="d-flex align-items-center">
//                         <i class="fas fa-quote-left fs-4 me-3 text-primary"></i>
//                         <div>
//                             <h5 class="mb-1 fw-bold">Đang xem trích dẫn</h5>
//                             <p class="mb-0">Nội dung trích dẫn từ trang ${page} được hiển thị bên dưới và đã được đánh dấu trong tài liệu.</p>
//                         </div>
//                     </div>
//                 `;
//             }
            
//             citationBanner.innerHTML = message + `
//                 <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
//             `;
            
//             contentContainer.insertBefore(citationBanner, contentContainer.firstChild);
//         }
//     });
// </script>

<!-- Thêm script xử lý highlight cho file txt/md -->
@php
    $extension = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));
@endphp

@if(in_array($extension, ['txt', 'md']))
    <script src="{{ asset('js/citation-highlight.js') }}"></script>
@elseif($extension == 'pdf')
    <script src="{{ asset('js/pdf-citation.js') }}"></script>
@endif
@endpush