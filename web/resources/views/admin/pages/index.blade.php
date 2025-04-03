@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Quản lý trang</h1>
        <a href="{{ route('admin.pages.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Tạo trang mới
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width: 50px">ID</th>
                            <th>Tiêu đề</th>
                            <th>Đường dẫn</th>
                            <th>Trang cha</th>
                            <th>Trạng thái</th>
                            <th style="width: 100px">Menu</th>
                            <th style="width: 80px">Thứ tự</th>
                            <th style="width: 200px">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pages as $page)
                        <tr class="{{ $page->is_homepage ? 'table-primary' : '' }}">
                            <td>{{ $page->id }}</td>
                            <td>
                                {{ $page->title }}
                                @if($page->is_homepage)
                                    <span class="badge bg-primary ms-1">Trang chủ</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ $page->getUrl() }}" target="_blank" class="text-decoration-none">
                                    {{ $page->slug }}
                                    <i class="fas fa-external-link-alt fa-xs ms-1"></i>
                                </a>
                            </td>
                            <td>
                                @if($page->parent)
                                    {{ $page->parent->title }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($page->status == 'published')
                                    @if($page->published_at && $page->published_at->isFuture())
                                        <span class="badge bg-info">
                                            Lên lịch: {{ $page->published_at->format('d/m/Y H:i') }}
                                        </span>
                                    @else
                                        <span class="badge bg-success">Đã xuất bản</span>
                                    @endif
                                @else
                                    <span class="badge bg-warning text-dark">Bản nháp</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($page->show_in_menu)
                                    <i class="fas fa-check text-success"></i>
                                @else
                                    <i class="fas fa-times text-danger"></i>
                                @endif
                            </td>
                            <td class="text-center">
                                {{ $page->order }}
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="{{ route('admin.pages.edit', $page) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Sửa
                                    </a>
                                    @if(!$page->is_homepage)
                                        <form action="{{ route('admin.pages.set-homepage', $page) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-info" title="Đặt làm trang chủ">
                                                <i class="fas fa-home"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if(!$page->is_homepage)
                                        <form action="{{ route('admin.pages.destroy', $page) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa trang này?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center">Không có trang nào.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $pages->links() }}
        </div>
    </div>
</div>
@endsection
