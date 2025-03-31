@extends('layouts.admin')

@section('header')
    Quản lý người dùng
@endsection

@section('content')
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold">Danh sách người dùng</h3>
                <a href="{{ route('admin.users.create') }}" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Thêm người dùng</a>
            </div>
            
            @if(session('success'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                    {{ session('success') }}
                </div>
            @endif
            
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b">ID</th>
                            <th class="py-2 px-4 border-b">Tên</th>
                            <th class="py-2 px-4 border-b">Email</th>
                            <th class="py-2 px-4 border-b">Ngày tạo</th>
                            <th class="py-2 px-4 border-b">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td class="py-2 px-4 border-b">{{ $user->id }}</td>
                                <td class="py-2 px-4 border-b">{{ $user->name }}</td>
                                <td class="py-2 px-4 border-b">{{ $user->email }}</td>
                                <td class="py-2 px-4 border-b">{{ $user->created_at->format('d/m/Y') }}</td>
                                <td class="py-2 px-4 border-b">
                                    <div class="flex space-x-2">
                                        <a href="{{ route('admin.users.show', $user) }}" class="px-2 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">Xem</a>
                                        <a href="{{ route('admin.users.edit', $user) }}" class="px-2 py-1 bg-yellow-600 text-white rounded hover:bg-yellow-700 text-sm">Sửa</a>
                                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn xóa?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-2 py-1 bg-red-600 text-white rounded hover:bg-red-700 text-sm">Xóa</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
