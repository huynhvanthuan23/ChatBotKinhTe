@extends('layouts.admin')

@section('header')
    Chi tiết người dùng
@endsection

@section('content')
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <div class="mb-4">
                <a href="{{ route('admin.users.index') }}" class="text-blue-600 hover:underline">&larr; Quay lại danh sách</a>
            </div>
            
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4">Thông tin người dùng</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">ID</p>
                        <p class="font-medium">{{ $user->id }}</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-600">Tên</p>
                        <p class="font-medium">{{ $user->name }}</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-600">Email</p>
                        <p class="font-medium">{{ $user->email }}</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-600">Ngày tạo</p>
                        <p class="font-medium">{{ $user->created_at->format('d/m/Y H:i:s') }}</p>
                    </div>
                </div>
            </div>
            
            <div class="flex space-x-2">
                <a href="{{ route('admin.users.edit', $user) }}" class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700">Chỉnh sửa</a>
                <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn xóa?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Xóa</button>
                </form>
            </div>
        </div>
    </div>
@endsection
