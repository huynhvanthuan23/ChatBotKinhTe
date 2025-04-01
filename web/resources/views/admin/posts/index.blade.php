@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Quản lý bài đăng</h1>
        <a href="{{ route('admin.posts.create') }}" class="btn btn-primary">
            Tạo bài đăng mới
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
                            <th>Tác giả</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($posts as $post)
                        <tr>
                            <td>{{ $post->id }}</td>
                            <td>{{ $post->title }}</td>
                            <td>{{ $post->user->name }}</td>
                            <td>
                                <span class="badge {{ $post->status === 'published' ? 'bg-success' : 'bg-warning' }}">
                                    {{ $post->status === 'published' ? 'Đã xuất bản' : 'Bản nháp' }}
                                </span>
                            </td>
                            <td>{{ $post->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.posts.edit', $post) }}" class="btn btn-sm btn-primary">
                                    Sửa
                                </a>
                                <form action="{{ route('admin.posts.destroy', $post) }}" method="POST" class="d-inline">
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
            {{ $posts->links() }}
        </div>
    </div>
</div>
@endsection
