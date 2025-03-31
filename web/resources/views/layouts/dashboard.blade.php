@extends('layouts.admin')

@section('header')
    Dashboard
@endsection

@section('content')
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <h3 class="text-lg font-semibold mb-4">Thống kê hệ thống</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-100 p-4 rounded-lg">
                    <div class="text-xl font-bold">{{ $usersCount }}</div>
                    <div>Người dùng</div>
                </div>
            </div>
            
            <div class="mt-8">
                <h3 class="text-lg font-semibold mb-4">Quản lý hệ thống</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <a href="{{ route('admin.users.index') }}" class="block p-4 bg-indigo-100 rounded-lg hover:bg-indigo-200">
                        <h4 class="text-lg font-semibold">Quản lý người dùng</h4>
                        <p class="text-sm text-gray-700">Xem và quản lý tài khoản người dùng</p>
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
