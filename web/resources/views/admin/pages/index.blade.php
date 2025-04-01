@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Quản lý trang</h1>
        <a href="{{ route('admin.pages.create') }}" class="btn btn-primary">
            Tạo trang mới
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tiêu đề</th>
                            <th>Đường dẫn</th>
                            <th>Trạng thái</th>
                            <th>Hiển thị menu</th>
                            <th>Thứ tự</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pages as $page)
                        <tr>
                            <td>{{ $page->id }}</td>
                            <td>{{ $page->title }}</td>
                            <td>{{ $page->slug }}</td>
                            <td>
                                <span class="badge {{ $page->status === 'published' ? 'bg-success' : 'bg-warning' }}">
                                    {{ $page->status === 'published' ? 'Đã xuất bản' : 'Bản nháp' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $page->show_in_menu ? 'bg-primary' : 'bg-secondary' }}">
                                    {{ $page->show_in_menu ? 'Có' : 'Không' }}
                                </span>
                            </td>
                            <td>{{ $page->order }}</td>
                            <td>
                                <a href="{{ route('admin.pages.edit', $page) }}" class="btn btn-sm btn-primary">
                                    Sửa
                                </a>
                                <form action="{{ route('admin.pages.destroy', $page) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa?')">
                                        Xóa
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $pages->links() }}
        </div>
    </div>
</div>
@endsection
