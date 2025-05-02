<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - @yield('title', 'Dashboard')</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        body {
            padding-top: 56px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .admin-navbar {
            background-color: #343a40;
        }
        .admin-navbar .navbar-brand {
            font-weight: bold;
            color: #ffffff;
        }
        .admin-navbar .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            margin: 0 0.25rem;
        }
        .admin-navbar .nav-link:hover {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .admin-navbar .nav-link.active {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.2);
            font-weight: 500;
        }
        .admin-navbar .dropdown-menu {
            background-color: #343a40;
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .admin-navbar .dropdown-item {
            color: rgba(255, 255, 255, 0.85);
        }
        .admin-navbar .dropdown-item:hover, 
        .admin-navbar .dropdown-item:focus {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .content-wrapper {
            flex: 1;
            padding: 2rem 0;
        }
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
            padding-top: 1rem;
        }
        .sidebar .nav-link {
            color: #343a40;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            margin-bottom: 0.25rem;
        }
        .sidebar .nav-link:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        .sidebar .nav-link.active {
            color: #007bff;
            background-color: rgba(0, 123, 255, 0.1);
            font-weight: 500;
        }
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 0.5rem;
        }
        footer {
            background-color: #f8f9fa;
            padding: 1rem 0;
            border-top: 1px solid #dee2e6;
        }
        .submenu {
            padding-left: 1.5rem;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        .submenu.show {
            max-height: 300px;
        }
    </style>
    @stack('styles')
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top admin-navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('admin.dashboard') }}">Admin Panel</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link {{ Request::routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ Request::routeIs('admin.users.*') || Request::routeIs('admin.posts.*') || Request::routeIs('admin.pages.*') || Request::routeIs('admin.media.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i> Quản lý
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item {{ Request::routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                                    <i class="fas fa-users"></i> Quản lý người dùng
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item {{ Request::routeIs('admin.posts.*') ? 'active' : '' }}" href="{{ route('admin.posts.index') }}">
                                    <i class="fas fa-file-alt"></i> Quản lý bài đăng
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item {{ Request::routeIs('admin.pages.*') ? 'active' : '' }}" href="{{ route('admin.pages.index') }}">
                                    <i class="fas fa-file"></i> Quản lý trang
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item {{ Request::routeIs('admin.media.*') ? 'active' : '' }}" href="{{ route('admin.media.index') }}">
                                    <i class="fas fa-photo-video"></i> Quản lý Media
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ Request::routeIs('admin.system.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cogs"></i> Hệ thống
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item {{ Request::routeIs('admin.system.index') ? 'active' : '' }}" href="{{ route('admin.system.index') }}">
                                    <i class="fas fa-tachometer-alt"></i> Trạng thái
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item {{ Request::routeIs('admin.system.api-config') ? 'active' : '' }}" href="{{ route('admin.system.api-config') }}">
                                    <i class="fas fa-key"></i> Cấu hình API
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item {{ Request::routeIs('admin.system.api-docs') ? 'active' : '' }}" href="{{ route('admin.system.api-docs') }}">
                                    <i class="fas fa-book"></i> Tài liệu API
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('home') }}" target="_blank">
                            <i class="fas fa-home"></i> Xem trang chủ
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> {{ Auth::user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                    <i class="fas fa-user-edit"></i> Hồ sơ
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="fas fa-sign-out-alt"></i> Đăng xuất
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <div class="container-fluid mt-4">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <h5><i class="icon fas fa-check"></i> Thành công!</h5>
                <p>{{ session('success') }}</p>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <h5><i class="icon fas fa-ban"></i> Lỗi!</h5>
                <p>{{ session('error') }}</p>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <h5><i class="icon fas fa-exclamation-triangle"></i> Cảnh báo!</h5>
                <p>{{ session('warning') }}</p>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('info'))
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <h5><i class="icon fas fa-info"></i> Thông tin!</h5>
                <p>{{ session('info') }}</p>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
    </div>

    <!-- Main Content -->
    <div class="container-fluid content-wrapper">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block d-none sidebar">
                <div class="position-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link {{ Request::routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Request::routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                                <i class="fas fa-users"></i> Quản lý người dùng
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Request::routeIs('admin.posts.*') ? 'active' : '' }}" href="{{ route('admin.posts.index') }}">
                                <i class="fas fa-file-alt"></i> Quản lý bài đăng
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Request::routeIs('admin.pages.*') ? 'active' : '' }}" href="{{ route('admin.pages.index') }}">
                                <i class="fas fa-file"></i> Quản lý trang
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Request::routeIs('admin.media.*') ? 'active' : '' }}" href="{{ route('admin.media.index') }}">
                                <i class="fas fa-photo-video"></i> Quản lý Media
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link dropdown-toggle {{ Request::routeIs('admin.system.*') ? 'active' : '' }}" href="#" id="systemDropdown">
                                <i class="fas fa-cogs"></i> Hệ thống
                            </a>
                            <ul class="nav flex-column submenu {{ Request::routeIs('admin.system.*') ? 'show' : '' }}">
                                <li class="nav-item">
                                    <a class="nav-link {{ Request::routeIs('admin.system.index') ? 'active' : '' }}" href="{{ route('admin.system.index') }}">
                                        <i class="fas fa-tachometer-alt"></i> Trạng thái
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ Request::routeIs('admin.system.api-config') ? 'active' : '' }}" href="{{ route('admin.system.api-config') }}">
                                        <i class="fas fa-key"></i> Cấu hình API
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ Request::routeIs('admin.system.api-docs') ? 'active' : '' }}" href="{{ route('admin.system.api-docs') }}">
                                        <i class="fas fa-book"></i> Tài liệu API
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="nav-link" href="{{ route('home') }}" target="_blank">
                                <i class="fas fa-home"></i> Xem trang chủ
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.system.index') }}" class="nav-link {{ request()->routeIs('admin.system.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-server"></i>
                                <p>Quản lý hệ thống</p>
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-cogs"></i>
                                <p>Cấu hình website</p>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-center py-3">
        <div class="container">
            <span class="text-muted">© {{ date('Y') }} Admin Panel. All rights reserved.</span>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS for sidebar -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle sidebar dropdown
            document.getElementById('systemDropdown').addEventListener('click', function(e) {
                e.preventDefault();
                const submenu = this.nextElementSibling;
                submenu.classList.toggle('show');
            });
        });
    </script>
    @stack('scripts')
    
    <!-- Thêm Media Manager Modal nếu cần -->
    @if(View::exists('admin.partials.media-manager'))
        @include('admin.partials.media-manager')
    @endif
</body>
</html>
