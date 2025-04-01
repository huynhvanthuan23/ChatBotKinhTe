@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Tải lên file mới</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.media.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="file" class="form-label">Chọn file</label>
                    <input type="file" class="form-control @error('file') is-invalid @enderror" 
                           id="file" name="file" required>
                    <div class="form-text">Kích thước tối đa: 10MB</div>
                    @error('file')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="name" class="form-label">Tên file (tùy chọn)</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                           id="name" name="name" value="{{ old('name') }}">
                    <div class="form-text">Nếu không nhập, tên file gốc sẽ được sử dụng</div>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <button type="submit" class="btn btn-primary">Tải lên</button>
                    <a href="{{ route('admin.media.index') }}" class="btn btn-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection 