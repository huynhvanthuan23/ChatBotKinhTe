<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Post;
use App\Models\Page;
use App\Models\Media;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // Đếm số lượng người dùng
        $usersCount = User::count();
        
        // Đếm số lượng bài đăng (nếu có model Post)
        $postsCount = class_exists('App\Models\Post') ? Post::count() : 0;
        
        // Đếm số lượng trang (nếu có model Page)
        $pagesCount = class_exists('App\Models\Page') ? Page::count() : 0;
        
        // Đếm số lượng media (nếu có model Media)
        $mediaCount = class_exists('App\Models\Media') ? Media::count() : 0;
        
        // Các thống kê khác nếu cần
        $recentUsers = User::latest()->take(5)->get();
        $recentPosts = class_exists('App\Models\Post') ? Post::latest()->take(5)->get() : collect([]);
        
        return view('admin.dashboard', compact(
            'usersCount',
            'postsCount',
            'pagesCount',
            'mediaCount',
            'recentUsers',
            'recentPosts'
        ));
    }
}
