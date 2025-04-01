@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Chỉnh sửa thông tin file</h3>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="text-center">
                        @if($media->isImage())
                            <img src="{{ $media->getFullUrl() }}" alt="{{ $media->name }}" class="img-fluid mb-3" style="max-height: 200px;">
                        @else
                            <div class="d-flex justify-content-center align-items-center bg-light mb-3" style="height: 200px;">
                                <i class="fas {{ $media->getIconClass() }} fa-5x text-muted"></i>
                            </div>
                        @endif
                        <p>
                            <a href="{{ $media->getFullUrl() }}" target="_blank" class="btn btn-sm btn-info">
                                <i class="fas fa-external-link-alt"></i> Xem file
                            </a>
                        </p>
                    </div>
                </div>
                <div class="col-md-8">
                    <table class="table">
                        <tr>
                            <th>Tên file gốc:</th>
                            <td>{{ $media->file_name }}</td>
                        </tr>
                        <tr>
                            <th>Định dạng:</th>
                            <td>{{ $media->mime_type }}</td>
                        </tr>
                        <tr>
                            <th>Kích thước:</th>
                            <td>{{ $media->getFormattedSize() }}</td>
                        </tr>
                        <tr>
                            <th>Người tải lên:</th>
                            <td>{{ $media->user->name }}</td>
                        </tr>
                        <tr>
                            <th>Ngày tải lên:</th>
                            <td>{{ $media->created_at->format('d/m/Y H:i:s') }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <form action="{{ route('admin.media.update', $media) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="mb-3">
                    <label for="name" class="form-label">Tên hiển thị</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                           id="name" name="name" value="{{ old('name', $media->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <button type="submit" class="btn btn-primary">Cập nhật</button>
                    <a href="{{ route('admin.media.index') }}" class="btn btn-secondary">Quay lại</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection 