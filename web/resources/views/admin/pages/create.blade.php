@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Tạo trang mới</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.pages.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="title" class="form-label">Tiêu đề</label>
                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                           id="title" name="title" value="{{ old('title') }}" required>
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

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input @error('show_in_menu') is-invalid @enderror" 
                           id="show_in_menu" name="show_in_menu" {{ old('show_in_menu') ? 'checked' : '' }}>
                    <label class="form-check-label" for="show_in_menu">Hiển thị trong menu</label>
                    @error('show_in_menu')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="order" class="form-label">Thứ tự hiển thị</label>
                    <input type="number" class="form-control @error('order') is-invalid @enderror" 
                           id="order" name="order" value="{{ old('order', 0) }}" min="0">
                    @error('order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <button type="submit" class="btn btn-primary">Lưu trang</button>
                    <a href="{{ route('admin.pages.index') }}" class="btn btn-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection 