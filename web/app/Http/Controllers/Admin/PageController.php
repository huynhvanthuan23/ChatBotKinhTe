<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class PageController extends Controller
{
    // Middleware đã được đặt ở route group
    
    /**
     * Hiển thị danh sách trang
     */
    public function index()
    {
        $pages = Page::with('parent')
                    ->orderBy('is_homepage', 'desc')
                    ->orderBy('parent_id', 'asc')
                    ->orderBy('order')
                    ->paginate(15);
                    
        return view('admin.pages.index', compact('pages'));
    }

    /**
     * Hiển thị form tạo trang mới
     */
    public function create()
    {
        $parentPages = Page::whereNull('parent_id')
                           ->orderBy('title')
                           ->get();
        $hasHomepage = Page::where('is_homepage', true)->exists();
        $nextOrder = Page::max('order') + 1;
        
        return view('admin.pages.create', compact('parentPages', 'hasHomepage', 'nextOrder'));
    }

    /**
     * Kiểm tra tiêu đề hoặc slug đã tồn tại chưa
     */
    public function checkTitle(Request $request)
    {
        $title = $request->input('title');
        $slug = $request->input('slug') ? Str::slug($request->input('slug')) : Str::slug($title);
        $pageId = $request->input('page_id');
        
        $query = Page::where(function($q) use ($title, $slug) {
            $q->where('title', $title)
              ->orWhere('slug', $slug);
        });
        
        if ($pageId) {
            $query->where('id', '!=', $pageId);
        }
        
        $exists = $query->exists();
        $existingPage = null;
        
        if ($exists) {
            $existingPage = Page::where('title', $title)->orWhere('slug', $slug)->first();
        }
        
        return response()->json([
            'exists' => $exists,
            'slug' => $slug,
            'message' => $exists ? 'Tiêu đề hoặc đường dẫn đã tồn tại' : '',
            'existingTitle' => $exists ? $existingPage->title : '',
            'existingSlug' => $exists ? $existingPage->slug : ''
        ]);
    }

    /**
     * Lưu trang mới
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'slug' => 'nullable|string|max:255|unique:pages,slug',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'status' => 'required|in:draft,published',
            'parent_id' => 'nullable|exists:pages,id',
            'show_in_menu' => 'boolean',
            'is_homepage' => 'boolean',
            'order' => 'nullable|integer',
            'published_at' => 'nullable|date',
        ]);
        
        // Xử lý nội dung nếu là dạng văn bản thô
        $validated['content'] = $this->formatContentIfNeeded($validated['content']);
        
        $page = Page::create($validated);
        
        // Xử lý trang chủ nếu cần
        if ($request->input('is_homepage')) {
            // Đặt tất cả các trang khác thành không phải trang chủ
            Page::where('id', '!=', $page->id)->update(['is_homepage' => false]);
        }
        
        return redirect()->route('admin.pages.index')
                         ->with('success', 'Trang đã được tạo thành công.');
    }

    /**
     * Định dạng nội dung nếu cần
     */
    protected function formatContentIfNeeded($content)
    {
        // Kiểm tra xem nội dung có dán từ văn bản thuần không (không có HTML)
        if (!Str::contains($content, ['<p>', '<div>', '<h1>', '<h2>', '<h3>'])) {
            // Chuẩn hóa xuống dòng
            $content = str_replace(["\r\n", "\r"], "\n", $content);
            
            // Tách các đoạn văn
            $paragraphs = explode("\n\n", $content);
            $paragraphs = array_filter($paragraphs, function($p) {
                return trim($p) !== '';
            });
            
            // Chuyển đổi mỗi đoạn thành thẻ <p>
            $formattedContent = '';
            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                
                // Loại bỏ khoảng trắng/xuống dòng thừa
                $paragraph = preg_replace('/\s+/', ' ', $paragraph);
                
                // Đoạn ngắn và có dấu hiệu là tiêu đề thì dùng thẻ <h2>
                if (strlen($paragraph) < 100 && substr_count($paragraph, '.') <= 1) {
                    $formattedContent .= "<h2>$paragraph</h2>\n\n";
                } else {
                    $formattedContent .= "<p>$paragraph</p>\n\n";
                }
            }
            
            return $formattedContent;
        }
        
        return $content;
    }

    /**
     * Hiển thị form chỉnh sửa trang
     */
    public function edit(Page $page)
    {
        $parentPages = Page::where('id', '!=', $page->id)
                           ->whereNull('parent_id')
                           ->orderBy('title')
                           ->get();
        $hasHomepage = Page::where('is_homepage', true)
                           ->where('id', '!=', $page->id)
                           ->exists();
        
        return view('admin.pages.edit', compact('page', 'parentPages', 'hasHomepage'));
    }

    /**
     * Cập nhật trang
     */
    public function update(Request $request, Page $page)
    {
        $validated = $request->validate([
            'title' => ['required', 'max:255', Rule::unique('pages')->ignore($page->id)],
            'slug' => ['nullable', 'max:255', Rule::unique('pages')->ignore($page->id)],
            'content' => 'required',
            'meta_title' => 'nullable|max:70',
            'meta_description' => 'nullable|max:160',
            'meta_keywords' => 'nullable|max:255',
            'parent_id' => [
                'nullable',
                'exists:pages,id',
                function ($attribute, $value, $fail) use ($page) {
                    // Không cho phép chọn chính trang hiện tại hoặc con của nó làm parent
                    if ($value == $page->id) {
                        $fail('Không thể chọn chính trang này làm trang cha.');
                    }
                    
                    // Kiểm tra không cho chọn con của trang này làm cha
                    $childIds = $page->children()->pluck('id')->toArray();
                    if (in_array($value, $childIds)) {
                        $fail('Không thể chọn trang con làm trang cha.');
                    }
                },
            ],
            'status' => 'required|in:draft,published',
            'show_in_menu' => 'sometimes|boolean',
            'is_homepage' => 'sometimes|boolean',
            'order' => 'required|integer|min:1',
            'published_at' => 'nullable|date',
        ]);

        // Tạo slug từ title nếu không được cung cấp
        if (empty($validated['slug'])) {
            if ($page->title !== $request->title) {
                $validated['slug'] = Str::slug($request->title);
            }
        } else {
            $validated['slug'] = Str::slug($validated['slug']);
        }
        
        // Chuyển đổi checkbox thành boolean
        $validated['show_in_menu'] = $request->has('show_in_menu');
        $validated['is_homepage'] = $request->has('is_homepage');
        
        // Nếu đặt làm trang chủ, cập nhật các trang khác
        if ($validated['is_homepage'] && !$page->is_homepage) {
            Page::where('is_homepage', true)->update(['is_homepage' => false]);
        }
        
        // Xử lý published_at
        if ($validated['status'] == 'published') {
            if (!empty($validated['published_at'])) {
                $validated['published_at'] = Carbon::parse($validated['published_at']);
            } else if ($page->published_at === null) {
                $validated['published_at'] = now();
            }
        } else {
            $validated['published_at'] = null;
        }

        $page->update($validated);

        return redirect()->route('admin.pages.index')
            ->with('success', 'Trang đã được cập nhật thành công.');
    }

    /**
     * Xóa trang
     */
    public function destroy(Page $page)
    {
        // Kiểm tra xem có phải trang chủ không
        if ($page->is_homepage) {
            return redirect()->route('admin.pages.index')
                ->with('error', 'Không thể xóa trang chủ.');
        }
        
        // Cập nhật các trang con để không còn parent_id
        $page->children()->update(['parent_id' => null]);
        
        // Xóa trang
        $page->delete();

        return redirect()->route('admin.pages.index')
            ->with('success', 'Trang đã được xóa thành công.');
    }
    
    /**
     * Set a page as homepage
     */
    public function setHomepage(Page $page)
    {
        // Cập nhật tất cả các trang khác
        Page::where('is_homepage', true)->update(['is_homepage' => false]);
        
        // Đặt trang này làm trang chủ
        $page->update([
            'is_homepage' => true,
            'status' => 'published',
            'published_at' => now()
        ]);
        
        return redirect()->route('admin.pages.index')
            ->with('success', 'Đã đặt "' . $page->title . '" làm trang chủ.');
    }
}
