@extends('layouts.admin')

@section('header')
    Thêm người dùng mới
@endsection

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Thêm người dùng mới</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.users.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="name" class="form-label">Tên</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                           id="name" name="name" value="{{ old('name') }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" 
                           id="email" name="email" value="{{ old('email') }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Mật khẩu</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" 
                           id="password" name="password" required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">Xác nhận mật khẩu</label>
                    <input type="password" class="form-control" 
                           id="password_confirmation" name="password_confirmation" required>
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label">Vai trò</label>
                    <select class="form-select @error('role') is-invalid @enderror" 
                            id="role" name="role" required>
                        <option value="user" {{ old('role') == 'user' ? 'selected' : '' }}>Người dùng</option>
                        <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Quản trị viên</option>
                    </select>
                    @error('role')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <button type="submit" class="btn btn-primary">Thêm mới</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
