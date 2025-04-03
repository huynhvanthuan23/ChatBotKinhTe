<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $posts = Post::with('user')->latest()->paginate(10);
        return view('admin.posts.index', compact('posts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.posts.create');
    }

    /**
     * Kiểm tra tiêu đề đã tồn tại chưa
     */
    public function checkTitle(Request $request)
    {
        $title = $request->input('title');
        $slug = Str::slug($title);
        $postId = $request->input('post_id'); // Nếu đang cập nhật
        
        $query = Post::where('slug', $slug);
        if ($postId) {
            $query->where('id', '!=', $postId);
        }
        
        $exists = $query->exists();
        
        return response()->json([
            'exists' => $exists,
            'slug' => $slug
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'content' => 'required',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'required|in:draft,published',
        ]);

        // Tạo slug từ title
        $slug = Str::slug($request->title);
        
        // Kiểm tra nếu người dùng đã xác nhận sử dụng tiêu đề trùng lặp
        $useOriginalTitle = $request->input('use_original_title', false);
        
        // Nếu người dùng chưa xác nhận và slug đã tồn tại
        if (!$useOriginalTitle && Post::where('slug', $slug)->exists()) {
            // Lưu dữ liệu vào session để hiển thị lại form với dữ liệu đã nhập
            return redirect()->route('admin.posts.create')
                ->withInput()
                ->with('title_exists', true)
                ->with('duplicate_slug', $slug);
        }
        
        // Nếu người dùng đã xác nhận hoặc tiêu đề là duy nhất
        if ($useOriginalTitle) {
            // Tự động thêm số vào slug
            $count = 1;
            $originalSlug = $slug;
            
            while (Post::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $count;
                $count++;
            }
        }
        
        $validated['slug'] = $slug;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('posts', 'public');
            $validated['image'] = $path;
        }

        $validated['user_id'] = auth()->id();
        Post::create($validated);

        return redirect()->route('admin.posts.index')
            ->with('success', 'Bài đăng đã được tạo thành công.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Post $post)
    {
        return view('admin.posts.edit', compact('post'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Post $post)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'content' => 'required',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'required|in:draft,published',
        ]);

        // Chỉ cập nhật slug nếu title thay đổi
        if ($post->title !== $request->title) {
            $slug = Str::slug($request->title);
            
            // Kiểm tra nếu người dùng đã xác nhận sử dụng tiêu đề trùng lặp
            $useOriginalTitle = $request->input('use_original_title', false);
            
            // Nếu người dùng chưa xác nhận và slug đã tồn tại (loại trừ post hiện tại)
            if (!$useOriginalTitle && Post::where('slug', $slug)->where('id', '!=', $post->id)->exists()) {
                return redirect()->route('admin.posts.edit', $post)
                    ->withInput()
                    ->with('title_exists', true)
                    ->with('duplicate_slug', $slug);
            }
            
            // Nếu người dùng đã xác nhận hoặc tiêu đề là duy nhất
            if ($useOriginalTitle) {
                // Tự động thêm số vào slug
                $count = 1;
                $originalSlug = $slug;
                
                while (Post::where('slug', $slug)->where('id', '!=', $post->id)->exists()) {
                    $slug = $originalSlug . '-' . $count;
                    $count++;
                }
            }
            
            $validated['slug'] = $slug;
        }

        if ($request->hasFile('image')) {
            // Xóa ảnh cũ nếu có
            if ($post->image) {
                Storage::disk('public')->delete($post->image);
            }
            
            $path = $request->file('image')->store('posts', 'public');
            $validated['image'] = $path;
        }

        $post->update($validated);

        return redirect()->route('admin.posts.index')
            ->with('success', 'Bài đăng đã được cập nhật thành công.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post)
    {
        // Xóa ảnh nếu có
        if ($post->image) {
            Storage::disk('public')->delete($post->image);
        }
        
        $post->delete();

        return redirect()->route('admin.posts.index')
            ->with('success', 'Bài đăng đã được xóa thành công.');
    }
}