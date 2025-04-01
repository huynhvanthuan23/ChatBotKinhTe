@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Quản lý media</h1>
        <a href="{{ route('admin.media.create') }}" class="btn btn-primary">
            Tải lên file mới
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <form action="{{ route('admin.media.index') }}" method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Tìm kiếm tên file..." value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary" type="submit">Tìm</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="type" class="form-select" onchange="this.form.submit()">
                        <option value="">Tất cả loại file</option>
                        <option value="image" {{ request('type') == 'image' ? 'selected' : '' }}>Hình ảnh</option>
                        <option value="video" {{ request('type') == 'video' ? 'selected' : '' }}>Video</option>
                        <option value="document" {{ request('type') == 'document' ? 'selected' : '' }}>Tài liệu</option>
                    </select>
                </div>
                <div class="col-md-4 text-end">
                    @if(request('search') || request('type'))
                        <a href="{{ route('admin.media.index') }}" class="btn btn-secondary">Xóa bộ lọc</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        @forelse($media as $item)
            <div class="col-md-2 mb-4">
                <div class="card h-100">
                    <div class="position-relative">
                        @if($item->isImage())
                            <img src="{{ $item->getFullUrl() }}" class="card-img-top" alt="{{ $item->name }}" style="height: 150px; object-fit: cover;">
                        @else
                            <div class="d-flex justify-content-center align-items-center bg-light" style="height: 150px;">
                                <i class="fas {{ $item->getIconClass() }} fa-3x text-muted"></i>
                            </div>
                        @endif
                    </div>
                    <div class="card-body">
                        <h6 class="card-title text-truncate" title="{{ $item->name }}">{{ $item->name }}</h6>
                        <p class="card-text text-muted small">
                            {{ $item->getFormattedSize() }} - {{ $item->created_at->format('d/m/Y') }}
                        </p>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <a href="{{ $item->getFullUrl() }}" target="_blank" class="btn btn-sm btn-info" title="Xem">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="{{ route('admin.media.edit', $item) }}" class="btn btn-sm btn-primary" title="Sửa">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('admin.media.destroy', $item) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" title="Xóa" onclick="return confirm('Bạn có chắc chắn muốn xóa file này?')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info">
                    Không có file media nào. Hãy tải lên file mới!
                </div>
            </div>
        @endforelse
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $media->links() }}
    </div>
</div>
@endsection
