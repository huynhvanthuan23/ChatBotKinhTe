@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Chỉnh sửa bài đăng</h3>
        </div>
        <div class="card-body">
            @if(session('title_exists'))
            <div class="alert alert-warning" id="titleExistsAlert">
                <h5><i class="fas fa-exclamation-triangle"></i> Cảnh báo!</h5>
                <p>Tiêu đề "<strong>{{ old('title') }}</strong>" đã tồn tại và sẽ tạo ra URL trùng lặp.</p>
                <p>Bạn có 2 lựa chọn:</p>
                <form action="{{ route('admin.posts.update', $post) }}" method="POST" enctype="multipart/form-data" id="duplicateForm">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="title" value="{{ old('title') }}">
                    <input type="hidden" name="content" value="{{ old('content', $post->content) }}">
                    <input type="hidden" name="status" value="{{ old('status', $post->status) }}">
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

            <form action="{{ route('admin.posts.update', $post) }}" method="POST" enctype="multipart/form-data" id="postForm">
                @csrf
                @method('PUT')
                
                <div class="mb-3">
                    <label for="title" class="form-label">Tiêu đề</label>
                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                           id="title" name="title" value="{{ old('title', $post->title) }}" required>
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
                              id="content" name="content" rows="10" required>{{ old('content', $post->content) }}</textarea>
                    @error('content')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="image" class="form-label">Hình ảnh</label>
                    @if($post->image)
                        <div class="mb-2">
                            <img src="{{ Storage::url($post->image) }}" alt="Current image" style="max-width: 200px">
                        </div>
                    @endif
                    <input type="file" class="form-control @error('image') is-invalid @enderror" 
                           id="image" name="image">
                    @error('image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select @error('status') is-invalid @enderror" 
                            id="status" name="status" required>
                        <option value="draft" {{ old('status', $post->status) == 'draft' ? 'selected' : '' }}>Bản nháp</option>
                        <option value="published" {{ old('status', $post->status) == 'published' ? 'selected' : '' }}>Xuất bản</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <button type="submit" class="btn btn-primary" id="submitBtn">Cập nhật</button>
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
    const postId = '{{ $post->id }}';
    let titleExists = false;
    
    // Kiểm tra tiêu đề khi nhập xong
    titleInput.addEventListener('blur', function() {
        if (this.value.trim() === '' || this.value.trim() === '{{ $post->title }}') return;
        
        fetch('{{ route("admin.posts.checkTitle") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                title: this.value,
                post_id: postId
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
});
</script>
@endpush 