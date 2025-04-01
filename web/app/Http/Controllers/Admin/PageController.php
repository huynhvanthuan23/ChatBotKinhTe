<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    // Middleware đã được đặt ở route group
    
    /**
     * Hiển thị danh sách trang
     */
    public function index()
    {
        $pages = Page::latest()->paginate(10);
        return view('admin.pages.index', compact('pages'));
    }

    /**
     * Hiển thị form tạo trang mới
     */
    public function create()
    {
        return view('admin.pages.create');
    }

    /**
     * Lưu trang mới
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'content' => 'required',
            'status' => 'required|in:draft,published',
            'show_in_menu' => 'boolean',
            'order' => 'integer|min:0',
        ]);

        // Tạo slug từ title
        $validated['slug'] = Str::slug($request->title);
        
        // Chuyển đổi checkbox thành boolean
        $validated['show_in_menu'] = $request->has('show_in_menu');
        
        Page::create($validated);

        return redirect()->route('admin.pages.index')
            ->with('success', 'Trang đã được tạo thành công.');
    }

    /**
     * Hiển thị form chỉnh sửa trang
     */
    public function edit(Page $page)
    {
        return view('admin.pages.edit', compact('page'));
    }

    /**
     * Cập nhật trang
     */
    public function update(Request $request, Page $page)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'content' => 'required',
            'status' => 'required|in:draft,published',
            'show_in_menu' => 'boolean',
            'order' => 'integer|min:0',
        ]);

        // Cập nhật slug nếu title thay đổi
        if ($page->title !== $request->title) {
            $validated['slug'] = Str::slug($request->title);
        }
        
        // Chuyển đổi checkbox thành boolean
        $validated['show_in_menu'] = $request->has('show_in_menu');

        $page->update($validated);

        return redirect()->route('admin.pages.index')
            ->with('success', 'Trang đã được cập nhật thành công.');
    }

    /**
     * Xóa trang
     */
    public function destroy(Page $page)
    {
        $page->delete();

        return redirect()->route('admin.pages.index')
            ->with('success', 'Trang đã được xóa thành công.');
    }
}
