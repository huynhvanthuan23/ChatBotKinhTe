<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;

class PageController extends Controller
{
    /**
     * Hiển thị chi tiết trang
     */
    public function show($slug)
    {
        // Lấy trang đã xuất bản và đã đến thời gian xuất bản
        $page = Page::where('slug', $slug)
                ->where('status', 'published')
                ->where(function($query) {
                    $query->whereNull('published_at')
                          ->orWhere('published_at', '<=', now());
                })
                ->firstOrFail();
                
        return view('pages.show', compact('page'));
    }
}
