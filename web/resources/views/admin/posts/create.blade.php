@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Tạo bài đăng mới</h3>
        </div>
        <div class="card-body">
            @if(session('title_exists'))
            <div class="alert alert-warning" id="titleExistsAlert">
                <h5><i class="fas fa-exclamation-triangle"></i> Cảnh báo!</h5>
                <p>Tiêu đề "<strong>{{ old('title') }}</strong>" đã tồn tại và sẽ tạo ra URL trùng lặp.</p>
                <p>Bạn có 2 lựa chọn:</p>
                <form action="{{ route('admin.posts.store') }}" method="POST" enctype="multipart/form-data" id="duplicateForm">
                    @csrf
                    <input type="hidden" name="title" value="{{ old('title') }}">
                    <input type="hidden" name="content" value="{{ old('content') }}">
                    <input type="hidden" name="status" value="{{ old('status') }}">
                    <input type="hidden" name="use_original_title" value="1">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Tiếp tục và tự động thêm số vào URL ({{ session('duplicate_slug') }}-1)
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('titleExistsAlert').style.display='none';">
                            <i class="fas fa-edit"></i> Tôi sẽ thay đổi tiêu đề
                        </button>
                    </div>
                </form>
            </div>
            @endif

            <form action="{{ route('admin.posts.store') }}" method="POST" enctype="multipart/form-data" id="postForm">
                @csrf
                <div class="mb-3">
                    <label for="title" class="form-label">Tiêu đề</label>
                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                           id="title" name="title" value="{{ old('title') }}" required>
                    <div id="titleFeedback" class="invalid-feedback d-none">
                        Tiêu đề này đã tồn tại. Hãy sửa đổi hoặc tiếp tục để hệ thống tự động thêm số vào URL.
                    </div>
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="content" class="form-label">Nội dung</label>
                    <textarea class="form-control @error('content') is-invalid @enderror" 
                              id="content" name="content" rows="10" required>{{ old('content') }}</textarea>
                    @error('content')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group mb-4">
                    <label for="image" class="block mb-2 text-sm font-medium text-gray-700">Hình ảnh</label>
                    
                    <div class="flex items-center">
                        <div class="flex-1">
                            <label class="block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 cursor-pointer hover:bg-gray-50">
                                <input type="file" id="image" name="image" accept="image/*" class="sr-only" />
                                <span class="text-sm text-gray-600">Chọn ảnh...</span>
                            </label>
                            
                            <div class="text-xs text-gray-500 mt-1">PNG, JPG, GIF tối đa 2MB</div>
                            
                            @error('image')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select @error('status') is-invalid @enderror" 
                            id="status" name="status" required>
                        <option value="draft" {{ old('status') == 'draft' ? 'selected' : '' }}>Bản nháp</option>
                        <option value="published" {{ old('status') == 'published' ? 'selected' : '' }}>Xuất bản</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <button type="submit" class="btn btn-primary" id="submitBtn">Lưu bài đăng</button>
                    <a href="{{ route('admin.posts.index') }}" class="btn btn-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.getElementById('title');
    const titleFeedback = document.getElementById('titleFeedback');
    const submitBtn = document.getElementById('submitBtn');
    let titleExists = false;
    
    // Kiểm tra tiêu đề khi nhập xong
    titleInput.addEventListener('blur', function() {
        if (this.value.trim() === '') return;
        
        fetch('{{ route("admin.posts.checkTitle") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                title: this.value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                titleFeedback.classList.remove('d-none');
                titleInput.classList.add('is-invalid');
                titleExists = true;
            } else {
                titleFeedback.classList.add('d-none');
                titleInput.classList.remove('is-invalid');
                titleExists = false;
            }
        });
    });
    
    // Nếu đã upload hình ảnh trong form cũ, chuyển qua form mới
    document.getElementById('duplicateForm')?.addEventListener('submit', function(e) {
        const imageInput = document.getElementById('image');
        if (imageInput.files.length > 0) {
            e.preventDefault();
            
            // Thêm dữ liệu hình ảnh vào form
            const formData = new FormData(this);
            formData.append('image', imageInput.files[0]);
            
            // Submit form bằng fetch
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.redirected ? window.location = response.url : null);
        }
    });

    const imageInput = document.getElementById('image');
    const imagePreview = document.getElementById('image-preview');
    const placeholderIcon = document.getElementById('placeholder-icon');
    
    imageInput.addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
                imagePreview.style.display = 'block';
                placeholderIcon.style.display = 'none';
            }
            
            reader.readAsDataURL(e.target.files[0]);
        } else {
            imagePreview.style.display = 'none';
            placeholderIcon.style.display = 'block';
        }
    });
});
</script>
@endpush 