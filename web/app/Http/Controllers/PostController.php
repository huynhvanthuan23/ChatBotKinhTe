<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostController extends Controller
{
    /**
     * Hiển thị danh sách bài viết
     */
    public function index()
    {
        $posts = Post::where('status', 'published')
                    ->latest()
                    ->paginate(12);
        
        return view('posts.index', compact('posts'));
    }
    
    /**
     * Hiển thị chi tiết bài viết
     */
    public function show($slug)
    {
        $post = Post::where('slug', $slug)
                ->where('status', 'published')
                ->firstOrFail();
        
        // Lấy bài viết liên quan
        $relatedPosts = Post::where('id', '!=', $post->id)
                        ->where('status', 'published')
                        ->latest()
                        ->take(3)
                        ->get();
        
        return view('posts.show', compact('post', 'relatedPosts'));
    }
} 