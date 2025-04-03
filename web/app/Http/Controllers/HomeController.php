<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Page;
use App\Models\Post;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        // Lấy trang chủ đã thiết lập hoặc sử dụng view mặc định
        $homepage = Page::where('is_homepage', true)->first();
        
        // Lấy bài viết mới nhất
        $latestPosts = Post::where('status', 'published')
                          ->orderBy('created_at', 'desc')
                          ->take(3)
                          ->get();
        
        if ($homepage) {
            return view('pages.homepage', compact('homepage', 'latestPosts'));
        }
        
        return view('home', compact('latestPosts'));
    }
} 