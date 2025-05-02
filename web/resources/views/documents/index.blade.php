@extends('layouts.app')

@section('content')
<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-md-11 col-lg-10">
            <div class="card shadow border-0 rounded-3">
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <h5 class="mb-0 fw-bold text-primary ps-2">
                            <i class="fas fa-file-alt me-2"></i> Quản lý tài liệu
                        </h5>
                        <div class="mt-2 mt-md-0">
                            <button id="create-all-vectors" class="btn btn-success me-2">
                                <i class="fas fa-code-branch me-1"></i> Tạo vector tất cả
                            </button>
                            <a href="{{ route('documents.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-2"></i> Thêm tài liệu mới
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
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

                    <!-- Input hidden để lưu danh sách tài liệu đã chọn từ Controller -->
                    <input type="hidden" id="selected-from-session" value="{{ json_encode($selectedDocumentIds ?? []) }}">

                    @if ($documents->isEmpty())
                        <div class="text-center p-5">
                            <div class="mb-4">
                                <i class="fas fa-folder-open text-muted" style="font-size: 6rem;"></i>
                            </div>
                            <h4 class="mb-3 text-muted">Chưa có tài liệu nào</h4>
                            <p class="text-muted mb-4">Bạn chưa tải lên tài liệu nào. Hãy thêm tài liệu mới để bắt đầu.</p>
                            <a href="{{ route('documents.create') }}" class="btn btn-primary btn-lg px-4">
                                <i class="fas fa-plus-circle me-2"></i> Thêm tài liệu mới
                            </a>
                        </div>
                    @else
                        <div class="mb-4">
                            <form id="bulk-action-form" method="GET" action="{{ route('chat') }}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <button type="submit" class="btn btn-info btn-lg shadow-sm" id="ask-selected-btn" disabled>
                                            <i class="fas fa-question-circle me-2"></i> Hỏi về tài liệu đã chọn
                                        </button>
                                    </div>
                                    <div class="form-check form-check-lg">
                                        <input class="form-check-input" type="checkbox" id="select-all-documents">
                                        <label class="form-check-label fw-bold" for="select-all-documents">
                                            Chọn tất cả
                                        </label>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="table-responsive rounded">
                            <table class="table table-hover align-middle table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" width="5%" class="text-center">
                                            <i class="fas fa-check-square"></i>
                                        </th>
                                        <th scope="col" width="5%" class="text-center">#</th>
                                        <th scope="col" width="35%">Tài liệu</th>
                                        <th scope="col" width="10%" class="d-none d-md-table-cell text-center">Loại</th>
                                        <th scope="col" width="10%" class="d-none d-md-table-cell text-center">Kích thước</th>
                                        
                                        <th scope="col" width="10%" class="text-center">Vector</th>
                                        <th scope="col" width="15%" class="text-center">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($documents as $index => $document)
                                        <tr>
                                            <td class="text-center">
                                                <div class="form-check d-flex justify-content-center">
                                                    <input class="form-check-input document-checkbox" type="checkbox" 
                                                        name="doc_ids[]" value="{{ $document->id }}"
                                                        {{ $document->vector_status !== 'completed' ? 'disabled' : '' }}
                                                        {{ in_array($document->id, $selectedDocumentIds ?? []) ? 'checked' : '' }}>
                                                </div>
                                            </td>
                                            <td class="text-center">{{ $index + 1 }}</td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="document-icon me-3">
                                                        @php
                                                            $fileType = strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION));
                                                            $iconClass = 'fa-file-alt';
                                                            
                                                            if ($fileType == 'pdf') {
                                                                $iconClass = 'fa-file-pdf';
                                                            } elseif (in_array($fileType, ['doc', 'docx'])) {
                                                                $iconClass = 'fa-file-word';
                                                            } elseif ($fileType == 'txt') {
                                                                $iconClass = 'fa-file-lines';
                                                            } elseif ($fileType == 'md') {
                                                                $iconClass = 'fa-file-code';
                                                            }
                                                        @endphp
                                                        <i class="fas {{ $iconClass }} fa-2x text-primary"></i>
                                                    </div>
                                                    <div>
                                                        <a href="{{ route('documents.show', $document->id) }}" class="text-decoration-none fw-semibold link-primary">
                                                            {{ $document->title }}
                                                        </a>
                                                        @if ($document->description)
                                                            <div class="small text-muted text-truncate" style="max-width: 300px;">
                                                                {{ $document->description }}
                                                            </div>
                                                        @endif
                                                        <div class="d-md-none small text-muted mt-1">
                                                            <span class="me-2">{{ strtoupper(pathinfo($document->file_path, PATHINFO_EXTENSION)) }}</span>
                                                            <span>{{ $document->human_file_size }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="d-none d-md-table-cell text-center">
                                                <span class="badge bg-light text-dark border">
                                                    {{ strtoupper(pathinfo($document->file_path, PATHINFO_EXTENSION)) }}
                                                </span>
                                            </td>
                                            <td class="d-none d-md-table-cell text-center">{{ $document->human_file_size }}</td>
                                            
                                            <td class="text-center">
                                                @if ($document->vector_status === 'completed')
                                                    <span class="badge bg-success">Đã tạo vector</span>
                                                @elseif ($document->vector_status === 'failed')
                                                    <span class="badge bg-danger">Lỗi vector</span>
                                                @elseif ($document->vector_status === 'processing')
                                                    <span class="badge bg-info text-white">Đang tạo vector</span>
                                                @else
                                                    <span class="badge bg-secondary">Chưa tạo vector</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center gap-2">
                                                    <a href="{{ route('documents.show', $document->id) }}" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button class="btn btn-info btn-sm create-vector" data-id="{{ $document->id }}" 
                                                        {{ $document->vector_status == 'completed' || $document->vector_status == 'processing' ? 'disabled' : '' }}>
                                                        <i class="fas fa-code-branch"></i>
                                                    </button>
                                                    @if ($document->vector_status === 'completed')
                                                        <a href="{{ route('documents.chat', $document->id) }}" class="btn btn-success btn-sm">
                                                            <i class="fas fa-comments"></i>
                                                        </a>
                                                    @endif
                                                    <button type="button" class="btn btn-danger btn-sm btn-delete" data-id="{{ $document->id }}">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Forms for delete actions -->
                        @foreach ($documents as $document)
                            <form id="delete-form-{{ $document->id }}" action="{{ route('documents.destroy', $document->id) }}" method="POST" class="d-none">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endforeach
                        
                        <div class="d-flex justify-content-center mt-4">
                            {{ $documents->links() }}
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
    .pagination {
        --bs-pagination-active-bg: #4098e5;
        --bs-pagination-active-border-color: #4098e5;
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
        color: white;
    }
    
    .btn-info:hover {
        background-color: #138496;
        border-color: #138496;
        color: white;
    }
    
    .btn-cyan {
        background-color: #00b8d4;
        border-color: #00b8d4;
        color: white;
    }
    
    .btn-cyan:hover {
        background-color: #00a0b8;
        border-color: #00a0b8;
        color: white;
    }
    
    .text-primary {
        color: #4098e5 !important;
    }
    
    .link-primary {
        color: #4098e5 !important;
    }
    
    .link-primary:hover {
        color: #3184d6 !important;
    }
    
    .btn {
        font-weight: 500;
        padding: 0.6rem 1rem;
        border-radius: 0.375rem;
    }
    
    .document-icon {
        width: 40px;
        text-align: center;
    }
    
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
        border-radius: 0.5rem;
    }
    
    .table th {
        font-weight: 600;
        background-color: #f8f9fa;
        border-bottom: 2px solid #e9ecef;
    }
    
    .table td {
        vertical-align: middle;
        padding: 0.75rem;
    }
    
    .table-striped tbody tr:nth-of-type(odd) {
        background-color: rgba(0, 0, 0, 0.02);
    }
    
    .form-check-input {
        width: 1.25em;
        height: 1.25em;
    }
    
    .form-check-lg .form-check-input {
        width: 1.5em;
        height: 1.5em;
        margin-top: 0.2em;
    }
    
    .form-check-lg .form-check-label {
        font-size: 1.1em;
        padding-left: 0.25rem;
    }
    
    #ask-selected-btn {
        padding: 0.65rem 1.5rem;
        font-size: 1rem;
        transition: all 0.3s ease;
    }
    
    #ask-selected-btn:not(:disabled) {
        box-shadow: 0 0.25rem 0.75rem rgba(0, 123, 255, 0.15);
    }
    
    #ask-selected-btn:not(:disabled):hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 123, 255, 0.2);
    }
    
    #ask-selected-btn:disabled {
        opacity: 0.65;
        cursor: not-allowed;
    }
    
    .badge {
        font-weight: 500;
        padding: 0.5em 0.75em;
    }
    
    .btn-sm {
        padding: 0.4rem 0.65rem;
        font-size: 0.875rem;
    }
    
    @media (max-width: 767.98px) {
        .table-responsive {
            border: 0;
        }
        
        .document-icon {
            width: 30px;
        }
        
        .document-icon i {
            font-size: 1.5rem !important;
        }
        
        .btn {
            padding: 0.5rem 0.75rem;
        }
        
        #ask-selected-btn {
            width: 100%;
            margin-bottom: 1rem;
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
        // Xử lý nút chọn tất cả tài liệu
        const selectAllCheckbox = document.getElementById('select-all-documents');
        const documentCheckboxes = document.querySelectorAll('.document-checkbox:not([disabled])');
        const askSelectedBtn = document.getElementById('ask-selected-btn');
        
        // Lưu trạng thái các checkbox vào localStorage
        function saveCheckboxState() {
            const selectedIds = [];
            documentCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    selectedIds.push(checkbox.value);
                }
            });
            localStorage.setItem('selected_documents', JSON.stringify(selectedIds));
            console.log('Saved selected documents:', selectedIds);
        }
        
        // Khôi phục trạng thái các checkbox từ localStorage
        function restoreCheckboxState() {
            try {
                // Đầu tiên kiểm tra trong session
                const selectedFromSession = document.getElementById('selected-from-session');
                let sessionIds = [];
                if (selectedFromSession && selectedFromSession.value) {
                    sessionIds = JSON.parse(selectedFromSession.value);
                }
                
                // Sau đó kiểm tra trong localStorage
                const savedIds = JSON.parse(localStorage.getItem('selected_documents')) || [];
                console.log('Restored selected documents from localStorage:', savedIds);
                console.log('Restored selected documents from session:', sessionIds);
                
                // Ưu tiên session trước, sau đó đến localStorage
                const idsToUse = sessionIds.length > 0 ? sessionIds : savedIds;
                
                if (idsToUse.length > 0) {
                    documentCheckboxes.forEach(checkbox => {
                        checkbox.checked = idsToUse.includes(checkbox.value);
                    });
                    
                    // Kiểm tra nếu tất cả đều được chọn
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = Array.from(documentCheckboxes).every(cb => cb.checked);
                    }
                    
                    // Cập nhật trạng thái nút
                    updateButtonState();
                }
            } catch (e) {
                console.error('Error restoring checkbox state:', e);
            }
        }
        
        // Hàm kiểm tra số lượng checkbox đã chọn và cập nhật trạng thái nút
        function updateButtonState() {
            const checkedCount = document.querySelectorAll('.document-checkbox:checked').length;
            askSelectedBtn.disabled = checkedCount === 0;
        }
        
        // Khôi phục trạng thái khi trang được tải
        restoreCheckboxState();
        
        // Lưu tài liệu đã chọn vào session
        function saveToSession() {
            const selectedIds = [];
            documentCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    selectedIds.push(checkbox.value);
                }
            });
            
            if (selectedIds.length > 0) {
                fetch('/documents/save-selection', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ doc_ids: selectedIds })
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Saved selection to session:', data);
                })
                .catch(error => {
                    console.error('Error saving selection to session:', error);
                });
            }
        }
        
        // Thêm hiển thị thông báo khi có tài liệu đã chọn
        function checkExistingSelection() {
            // Kiểm tra trong session (thông qua một thẻ hidden được thêm vào từ Controller)
            const selectedFromSession = document.getElementById('selected-from-session');
            if (selectedFromSession && selectedFromSession.value) {
                try {
                    const ids = JSON.parse(selectedFromSession.value);
                    if (ids.length > 0) {
                        // Tạo URL với tham số doc_ids
                        const chatUrl = `{{ route('chat') }}?doc_ids=${ids.join(',')}`;
                        
                        // Hiển thị thông báo
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-info alert-dismissible fade show';
                        alertDiv.innerHTML = `
                            
                            <div class="mt-2">
                                <a href="/chat" class="btn btn-cyan">
                                    <i class="fas fa-arrow-left me-1"></i> Quay lại trang chat
                                </a>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        `;
                        
                        // Thêm vào đầu card-body
                        const cardBody = document.querySelector('.card-body');
                        if (cardBody) {
                            cardBody.insertBefore(alertDiv, cardBody.firstChild);
                        }
                    }
                } catch (e) {
                    console.error('Error parsing selected documents:', e);
                }
            }
        }
        
        // Kiểm tra khi trang được tải
        checkExistingSelection();
        
        // Sự kiện cho nút "Chọn tất cả"
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                documentCheckboxes.forEach(checkbox => {
                    checkbox.checked = selectAllCheckbox.checked;
                });
                updateButtonState();
                saveCheckboxState();
                saveToSession();
            });
        }
        
        // Sự kiện cho từng checkbox
        documentCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateButtonState();
                // Kiểm tra nếu tất cả đều được chọn thì chọn cả nút "Chọn tất cả"
                const allChecked = Array.from(documentCheckboxes).every(cb => cb.checked);
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = allChecked;
                }
                saveCheckboxState();
                saveToSession();
            });
        });
        
        // Xử lý nút tạo vector cho từng tài liệu
        document.querySelectorAll('.create-vector').forEach(button => {
            button.addEventListener('click', function() {
                const documentId = this.getAttribute('data-id');
                const button = this;
                
                // Disable nút và thêm trạng thái loading
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                // Gọi API tạo vector
                fetch(`/documents/${documentId}/create-vector`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Hiển thị thông báo thành công
                        alert('Đã bắt đầu tạo vector cho tài liệu này');
                        // Reload trang sau 1 giây
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        // Hiển thị thông báo lỗi
                        alert('Lỗi: ' + (data.message || 'Không thể tạo vector'));
                        button.disabled = false;
                        button.innerHTML = '<i class="fas fa-code-branch"></i>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Đã xảy ra lỗi khi xử lý yêu cầu');
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-code-branch"></i>';
                });
            });
        });
        
        // Xử lý nút tạo vector cho tất cả tài liệu
        document.getElementById('create-all-vectors').addEventListener('click', function() {
            const button = this;
            
            // Disable nút và thêm trạng thái loading
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
            
            // Gọi API tạo vector cho tất cả
            fetch('/documents/create-all-vectors', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' || data.status === 'info') {
                    // Hiển thị thông báo thành công
                    alert(data.message || 'Đã bắt đầu tạo vector cho tất cả tài liệu');
                    // Reload trang sau 1 giây
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Hiển thị thông báo lỗi
                    alert('Lỗi: ' + (data.message || 'Không thể tạo vector'));
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-code-branch"></i> Tạo vector tất cả';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Đã xảy ra lỗi khi xử lý yêu cầu');
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-code-branch"></i> Tạo vector tất cả';
            });
        });
        
        // Xử lý nút xóa tài liệu
        document.querySelectorAll('.btn-delete').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Bạn có chắc chắn muốn xóa tài liệu này?')) {
                    const documentId = this.getAttribute('data-id');
                    document.getElementById('delete-form-' + documentId).submit();
                }
            });
        });

        // Xử lý form submit để chuyển checkbox đã chọn thành query parameters
        const bulkActionForm = document.getElementById('bulk-action-form');
        if (bulkActionForm) {
            bulkActionForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Lấy tất cả checkbox đã chọn
                const selectedIds = [];
                documentCheckboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        selectedIds.push(checkbox.value);
                    }
                });
                
                if (selectedIds.length > 0) {
                    // Lưu vào session trước
                    saveToSession();
                    
                    // Chuyển hướng với tham số doc_ids trong URL
                    window.location.href = `{{ route('chat') }}?doc_ids=${selectedIds.join(',')}`;
                } else {
                    alert('Vui lòng chọn ít nhất một tài liệu');
                }
            });
        }
    });
</script>
@endpush 