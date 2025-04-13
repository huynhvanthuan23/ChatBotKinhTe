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
        // Lấy 6 bài viết mới nhất đã xuất bản
        $latestPosts = Post::where('status', 'published')
                        ->latest()
                        ->take(6)
                        ->get();
        
        return view('welcome', compact('latestPosts'));
    }
} 