<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        
        // Xử lý nội dung trước khi hiển thị nếu cần
        $page->content = $this->formatPageContent($page->content);
                
        return view('pages.show', compact('page'));
    }
    
    /**
     * Định dạng nội dung trang để hiển thị đẹp hơn
     */
    protected function formatPageContent($content)
    {
        // Nếu nội dung không chứa thẻ HTML, có thể là văn bản thô từ file
        if (!Str::contains($content, ['<p>', '<div>', '<h1>', '<h2>', '<h3>'])) {
            // Tách đoạn văn theo dấu xuống dòng
            $paragraphs = preg_split('/\r\n|\r|\n/', $content);
            
            // Lọc bỏ các dòng trống
            $paragraphs = array_filter($paragraphs, function($paragraph) {
                return trim($paragraph) !== '';
            });
            
            // Tạo HTML có định dạng với các thẻ <p>
            $formatted = '';
            
            foreach ($paragraphs as $paragraph) {
                // Tìm tiêu đề có thể (dòng ngắn dưới 100 ký tự có thể là tiêu đề)
                if (strlen(trim($paragraph)) < 100 && substr_count($paragraph, '.') <= 1) {
                    $formatted .= '<h2 class="text-xl font-semibold my-4">' . htmlspecialchars($paragraph) . '</h2>';
                } else {
                    $formatted .= '<p class="mb-4">' . htmlspecialchars($paragraph) . '</p>';
                }
            }
            
            return $formatted;
        }
        
        // Nếu nội dung đã có HTML, giữ nguyên
        return $content;
    }
}
