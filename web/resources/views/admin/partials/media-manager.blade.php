<div class="modal fade" id="mediaManagerModal" tabindex="-1" aria-labelledby="mediaManagerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mediaManagerModalLabel">Quản lý Media</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-8">
                        <input type="text" id="mediaSearchInput" class="form-control" placeholder="Tìm kiếm file...">
                    </div>
                    <div class="col-md-4">
                        <select id="mediaTypeFilter" class="form-select">
                            <option value="">Tất cả loại file</option>
                            <option value="image">Hình ảnh</option>
                            <option value="video">Video</option>
                            <option value="document">Tài liệu</option>
                        </select>
                    </div>
                </div>
                
                <div class="media-items-container row" id="mediaItemsContainer">
                    <!-- Các file media sẽ được load bằng AJAX -->
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-center mt-3">
                    <nav aria-label="Media pagination" id="mediaPagination"></nav>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" id="selectMediaBtn" disabled>Chọn file</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Khai báo biến toàn cục
    let selectedMediaId = null;
    let mediaCallback = null;
    
    // Hàm load danh sách media bằng AJAX
    function loadMedia(page = 1, search = '', type = '') {
        const container = document.getElementById('mediaItemsContainer');
        container.innerHTML = `
            <div class="text-center py-5 col-12">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        
        fetch(`{{ route('admin.media.index') }}?page=${page}&search=${search}&type=${type}&ajax=1`)
            .then(response => response.json())
            .then(data => {
                container.innerHTML = '';
                
                if (data.media.data.length === 0) {
                    container.innerHTML = `
                        <div class="col-12">
                            <div class="alert alert-info">
                                Không có file media nào. Hãy tải lên file mới!
                            </div>
                        </div>
                    `;
                    return;
                }
                
                data.media.data.forEach(item => {
                    const card = document.createElement('div');
                    card.className = 'col-md-2 mb-4';
                    
                    let thumbnail = '';
                    if (item.mime_type.startsWith('image/')) {
                        thumbnail = `<img src="${item.url}" class="card-img-top" alt="${item.name}" style="height: 100px; object-fit: cover;">`;
                    } else {
                        thumbnail = `
                            <div class="d-flex justify-content-center align-items-center bg-light" style="height: 100px;">
                                <i class="fas ${item.icon_class} fa-3x text-muted"></i>
                            </div>
                        `;
                    }
                    
                    card.innerHTML = `
                        <div class="card h-100 media-item" data-id="${item.id}" data-url="${item.url}">
                            <div class="position-relative">
                                ${thumbnail}
                            </div>
                            <div class="card-body">
                                <h6 class="card-title text-truncate" title="${item.name}">${item.name}</h6>
                                <p class="card-text text-muted small">
                                    ${item.formatted_size} - ${item.formatted_date}
                                </p>
                            </div>
                        </div>
                    `;
                    
                    container.appendChild(card);
                });
                
                // Xử lý phân trang
                renderPagination(data.media);
                
                // Xử lý sự kiện chọn file
                setupMediaSelection();
            })
            .catch(error => {
                console.error('Error loading media:', error);
                container.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger">
                            Có lỗi xảy ra khi tải danh sách media. Vui lòng thử lại sau.
                                                    </div>
                    </div>
                </div>
            })
            .catch(error => {
                console.error('Error loading media:', error);
                container.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger">
                            Có lỗi xảy ra khi tải danh sách media. Vui lòng thử lại sau.
                        </div>
                    </div>
                `;
            });
    }
    
    // Tạo phân trang
    function renderPagination(data) {
        const pagination = document.getElementById('mediaPagination');
        
        if (!data || data.last_page <= 1) {
            pagination.innerHTML = '';
            return;
        }
        
        let html = '<ul class="pagination">';
        
        // Nút Previous
        if (data.current_page > 1) {
            html += `<li class="page-item">
                <a class="page-link" href="#" data-page="${data.current_page - 1}" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>`;
        } else {
            html += `<li class="page-item disabled">
                <span class="page-link" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </span>
            </li>`;
        }
        
        // Trang
        const startPage = Math.max(data.current_page - 2, 1);
        const endPage = Math.min(startPage + 4, data.last_page);
        
        for (let i = startPage; i <= endPage; i++) {
            if (i === data.current_page) {
                html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
            } else {
                html += `<li class="page-item"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
            }
        }
        
        // Nút Next
        if (data.current_page < data.last_page) {
            html += `<li class="page-item">
                <a class="page-link" href="#" data-page="${data.current_page + 1}" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>`;
        } else {
            html += `<li class="page-item disabled">
                <span class="page-link" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </span>
            </span>
            </li>`;
        }
        
        html += '</ul>';
        pagination.innerHTML = html;
        
        // Thêm event listeners cho các link phân trang
        const pageLinks = pagination.querySelectorAll('.page-link');
        pageLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = this.getAttribute('data-page');
                if (page) {
                    const search = document.getElementById('mediaSearchInput').value;
                    const type = document.getElementById('mediaTypeFilter').value;
                    loadMedia(page, search, type);
                }
            });
        });
    }
    
    // Thiết lập sự kiện chọn media
    function setupMediaSelection() {
        const mediaItems = document.querySelectorAll('.media-item');
        const selectBtn = document.getElementById('selectMediaBtn');
        
        mediaItems.forEach(item => {
            item.addEventListener('click', function() {
                mediaItems.forEach(i => i.classList.remove('border-primary', 'border-2'));
                this.classList.add('border-primary', 'border-2');
                selectedMediaId = this.getAttribute('data-id');
                selectBtn.removeAttribute('disabled');
            });
        });
        
        // Reset selection khi mở modal
        document.getElementById('mediaManagerModal').addEventListener('hidden.bs.modal', function() {
            mediaItems.forEach(i => i.classList.remove('border-primary', 'border-2'));
            selectedMediaId = null;
            selectBtn.setAttribute('disabled', 'disabled');
        });
    }
    
    // Mở media manager và trả về callback khi chọn
    function openMediaManager(callback) {
        mediaCallback = callback;
        const modal = new bootstrap.Modal(document.getElementById('mediaManagerModal'));
        modal.show();
        loadMedia();
    }
    
    // Sự kiện khi nút "Chọn file" được click
    document.getElementById('selectMediaBtn').addEventListener('click', function() {
        if (selectedMediaId && mediaCallback) {
            const selectedItem = document.querySelector(`.media-item[data-id="${selectedMediaId}"]`);
            const mediaUrl = selectedItem.getAttribute('data-url');
            mediaCallback(selectedMediaId, mediaUrl);
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('mediaManagerModal'));
            modal.hide();
        }
    });
    
    // Sự kiện tìm kiếm
    document.getElementById('mediaSearchInput').addEventListener('input', debounce(function() {
        const search = this.value;
        const type = document.getElementById('mediaTypeFilter').value;
        loadMedia(1, search, type);
    }, 500));
    
    // Sự kiện lọc theo loại
    document.getElementById('mediaTypeFilter').addEventListener('change', function() {
        const search = document.getElementById('mediaSearchInput').value;
        const type = this.value;
        loadMedia(1, search, type);
    });
    
    // Hàm debounce để tránh gọi API quá nhiều lần khi tìm kiếm
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                func.apply(context, args);
            }, wait);
        };
    }
</script>
@endpush