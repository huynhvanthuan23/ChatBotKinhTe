@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Chỉnh sửa trang</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.pages.update', $page) }}" method="POST" id="pageForm">
                @csrf
                @method('PUT')
                
                <ul class="nav nav-tabs mb-3" id="pageTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="content-tab" data-bs-toggle="tab" data-bs-target="#content-tab-pane" type="button" role="tab" aria-controls="content-tab-pane" aria-selected="true">
                            Nội dung
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="seo-tab" data-bs-toggle="tab" data-bs-target="#seo-tab-pane" type="button" role="tab" aria-controls="seo-tab-pane" aria-selected="false">
                            SEO
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings-tab-pane" type="button" role="tab" aria-controls="settings-tab-pane" aria-selected="false">
                            Cài đặt
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="pageTabsContent">
                    <!-- Tab Nội dung -->
                    <div class="tab-pane fade show active" id="content-tab-pane" role="tabpanel" aria-labelledby="content-tab" tabindex="0">
                        <div class="mb-3">
                            <label for="title" class="form-label">Tiêu đề</label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                   id="title" name="title" value="{{ old('title', $page->title) }}" required>
                            <div id="titleFeedback" class="invalid-feedback d-none">
                                Tiêu đề này đã tồn tại. Vui lòng chọn tiêu đề khác.
                            </div>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">Đường dẫn (tùy chọn)</label>
                            <div class="input-group">
                                <span class="input-group-text">{{ $page->is_homepage ? '/' : '/page/' }}</span>
                                <input type="text" class="form-control @error('slug') is-invalid @enderror" 
                                       id="slug" name="slug" value="{{ old('slug', $page->slug) }}" 
                                       placeholder="Để trống để tự động tạo từ tiêu đề" {{ $page->is_homepage ? 'readonly' : '' }}>
                            </div>
                            <div id="slugFeedback" class="invalid-feedback d-none">
                                Đường dẫn này đã tồn tại. Vui lòng chọn đường dẫn khác.
                            </div>
                            <div class="form-text">URL sẽ là: {{ $page->is_homepage ? url('/') : url('/page/') }}/<span id="slugPreview"></span></div>
                            @error('slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">Nội dung</label>
                            <textarea class="form-control @error('content') is-invalid @enderror" 
                                      id="content" name="content" rows="10" required>{{ old('content', $page->content) }}</textarea>
                            @error('content')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <!-- Tab SEO -->
                    <div class="tab-pane fade" id="seo-tab-pane" role="tabpanel" aria-labelledby="seo-tab" tabindex="0">
                        <div class="mb-3">
                            <label for="meta_title" class="form-label">Meta Title (tùy chọn)</label>
                            <input type="text" class="form-control @error('meta_title') is-invalid @enderror" 
                                   id="meta_title" name="meta_title" value="{{ old('meta_title', $page->meta_title) }}" maxlength="70">
                            <div class="form-text">
                                <span id="metaTitleCount">0</span>/70 ký tự
                            </div>
                            @error('meta_title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="meta_description" class="form-label">Meta Description (tùy chọn)</label>
                            <textarea class="form-control @error('meta_description') is-invalid @enderror" 
                                      id="meta_description" name="meta_description" rows="3" maxlength="160">{{ old('meta_description', $page->meta_description) }}</textarea>
                            <div class="form-text">
                                <span id="metaDescCount">0</span>/160 ký tự
                            </div>
                            @error('meta_description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="meta_keywords" class="form-label">Meta Keywords (tùy chọn)</label>
                            <input type="text" class="form-control @error('meta_keywords') is-invalid @enderror" 
                                   id="meta_keywords" name="meta_keywords" value="{{ old('meta_keywords', $page->meta_keywords) }}">
                            <div class="form-text">Phân cách các từ khóa bằng dấu phẩy</div>
                            @error('meta_keywords')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <!-- Tab Cài đặt -->
                    <div class="tab-pane fade" id="settings-tab-pane" role="tabpanel" aria-labelledby="settings-tab" tabindex="0">
                        @if(!$page->is_homepage)
                        <div class="mb-3">
                            <label for="parent_id" class="form-label">Trang cha (tùy chọn)</label>
                            <select class="form-select @error('parent_id') is-invalid @enderror" 
                                    id="parent_id" name="parent_id">
                                <option value="">-- Không có trang cha --</option>
                                @foreach($parentPages as $parentPage)
                                    <option value="{{ $parentPage->id }}" {{ old('parent_id', $page->parent_id) == $parentPage->id ? 'selected' : '' }}>
                                        {{ $parentPage->title }}
                                    </option>
                                @endforeach
                            </select>
                            @error('parent_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif

                        <div class="mb-3">
                            <label for="status" class="form-label">Trạng thái</label>
                            <select class="form-select @error('status') is-invalid @enderror" 
                                    id="status" name="status" required {{ $page->is_homepage ? 'disabled' : '' }}>
                                <option value="draft" {{ old('status', $page->status) == 'draft' ? 'selected' : '' }}>Bản nháp</option>
                                <option value="published" {{ old('status', $page->status) == 'published' ? 'selected' : '' }}>Xuất bản</option>
                            </select>
                            @if($page->is_homepage)
                                <input type="hidden" name="status" value="published">
                                <div class="form-text text-info">Trang chủ luôn ở trạng thái xuất bản.</div>
                            @endif
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div id="publishedAtContainer" class="mb-3" style="{{ old('status', $page->status) == 'published' ? '' : 'display: none;' }}">
                            <label for="published_at" class="form-label">Thời gian xuất bản</label>
                            <input type="datetime-local" class="form-control @error('published_at') is-invalid @enderror" 
                                   id="published_at" name="published_at" 
                                   value="{{ old('published_at', $page->published_at ? $page->published_at->format('Y-m-d\TH:i') : '') }}"
                                   {{ $page->is_homepage ? 'disabled' : '' }}>
                            <div class="form-text">Để trống để xuất bản ngay lập tức</div>
                            @error('published_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input @error('show_in_menu') is-invalid @enderror" 
                                   id="show_in_menu" name="show_in_menu" value="1" {{ old('show_in_menu', $page->show_in_menu) ? 'checked' : '' }}>
                            <label class="form-check-label" for="show_in_menu">Hiển thị trong menu</label>
                            @error('show_in_menu')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3" id="orderContainer" style="{{ old('show_in_menu', $page->show_in_menu) ? '' : 'display: none;' }}">
                            <label for="order" class="form-label">Thứ tự hiển thị trong menu</label>
                            <input type="number" class="form-control @error('order') is-invalid @enderror" 
                                   id="order" name="order" value="{{ old('order', $page->order) }}" min="1">
                            <div class="form-text">Thứ tự hiển thị từ trái sang phải, bắt đầu từ 1</div>
                            @error('order')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        @if(!$page->is_homepage && !$hasHomepage)
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input @error('is_homepage') is-invalid @enderror" 
                                   id="is_homepage" name="is_homepage" value="1" {{ old('is_homepage', $page->is_homepage) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_homepage">Đặt làm trang chủ</label>
                            @error('is_homepage')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" id="submitBtn">Cập nhật</button>
                    <a href="{{ route('admin.pages.index') }}" class="btn btn-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Khởi tạo biến
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    const slugPreview = document.getElementById('slugPreview');
    const titleFeedback = document.getElementById('titleFeedback');
    const slugFeedback = document.getElementById('slugFeedback');
    const metaTitleInput = document.getElementById('meta_title');
    const metaDescInput = document.getElementById('meta_description');
    const metaTitleCount = document.getElementById('metaTitleCount');
    const metaDescCount = document.getElementById('metaDescCount');
    const submitBtn = document.getElementById('submitBtn');
    const showInMenuCheckbox = document.getElementById('show_in_menu');
    const orderContainer = document.getElementById('orderContainer');
    const statusSelect = document.getElementById('status');
    const publishedAtContainer = document.getElementById('publishedAtContainer');
    const isHomepage = {{ $page->is_homepage ? 'true' : 'false' }};
    const pageId = '{{ $page->id }}';
    
    let titleExists = false;
    let slugExists = false;
    
    // Cập nhật slug preview
    function updateSlugPreview() {
        const slugValue = slugInput.value.trim() !== '' 
            ? slugInput.value 
            : titleInput.value !== '' ? titleToSlug(titleInput.value) : '';
        slugPreview.textContent = slugValue;
    }
    
    // Chuyển đổi tiêu đề thành slug
    function titleToSlug(text) {
        return text.toLowerCase()
            .replace(/đ/g, 'd')
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_-]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }
    
    // Cập nhật counters
    function updateMetaTitleCount() {
        metaTitleCount.textContent = metaTitleInput.value.length;
        if (metaTitleInput.value.length > 60) {
            metaTitleCount.classList.add('text-danger');
        } else {
            metaTitleCount.classList.remove('text-danger');
        }
    }
    
    function updateMetaDescCount() {
        metaDescCount.textContent = metaDescInput.value.length;
        if (metaDescInput.value.length > 150) {
            metaDescCount.classList.add('text-danger');
        } else {
            metaDescCount.classList.remove('text-danger');
        }
    }
    
    // Kiểm tra tiêu đề và slug
    function checkTitleAndSlug() {
        if (titleInput.value.trim() === '' || 
            (titleInput.value === '{{ $page->title }}' && 
             slugInput.value === '{{ $page->slug }}')) return;
        
        fetch('{{ route("admin.pages.checkTitle") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                title: titleInput.value,
                slug: slugInput.value,
                page_id: pageId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                if (data.existingTitle === titleInput.value) {
                    titleFeedback.textContent = 'Tiêu đề này đã tồn tại. Vui lòng chọn tiêu đề khác.';
                    titleFeedback.classList.remove('d-none');
                    titleInput.classList.add('is-invalid');
                    titleExists = true;
                } else {
                    titleFeedback.classList.add('d-none');
                    titleInput.classList.remove('is-invalid');
                    titleExists = false;
                }
                
                if (data.existingSlug === (slugInput.value || data.slug)) {
                    slugFeedback.textContent = 'Đường dẫn này đã tồn tại. Vui lòng chọn đường dẫn khác.';
                    slugFeedback.classList.remove('d-none');
                    slugInput.classList.add('is-invalid');
                    slugExists = true;
                } else {
                    slugFeedback.classList.add('d-none');
                    slugInput.classList.remove('is-invalid');
                    slugExists = false;
                }
                
                submitBtn.disabled = titleExists || slugExists;
            } else {
                titleFeedback.classList.add('d-none');
                titleInput.classList.remove('is-invalid');
                slugFeedback.classList.add('d-none');
                slugInput.classList.remove('is-invalid');
                submitBtn.disabled = false;
                titleExists = false;
                slugExists = false;
            }
        });
    }
    
    // Event listeners
    if (!isHomepage) {
        titleInput.addEventListener('input', updateSlugPreview);
        slugInput.addEventListener('input', updateSlugPreview);
        
        titleInput.addEventListener('blur', checkTitleAndSlug);
        slugInput.addEventListener('blur', checkTitleAndSlug);
    }
    
    metaTitleInput.addEventListener('input', updateMetaTitleCount);
    metaDescInput.addEventListener('input', updateMetaDescCount);
    
    // Show/hide order field based on show_in_menu checkbox
    showInMenuCheckbox.addEventListener('change', function() {
        orderContainer.style.display = this.checked ? 'block' : 'none';
    });
    
    // Show/hide published_at field based on status
    if (!isHomepage) {
        statusSelect.addEventListener('change', function() {
            publishedAtContainer.style.display = this.value === 'published' ? 'block' : 'none';
        });
    }
    
    // Form submission
    document.getElementById('pageForm').addEventListener('submit', function(e) {
        if (titleExists || slugExists) {
            e.preventDefault();
            alert('Vui lòng sửa lỗi tiêu đề hoặc đường dẫn trùng lặp trước khi lưu.');
        }
    });
    
    // Initial updates
    updateSlugPreview();
    updateMetaTitleCount();
    updateMetaDescCount();
    
    // Initialize WYSIWYG editor if available
    if (typeof ClassicEditor !== 'undefined') {
        ClassicEditor
            .create(document.querySelector('#content'))
            .catch(error => {
                console.error(error);
            });
    }
});
</script>
@endpush 