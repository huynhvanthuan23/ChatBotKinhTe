<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'status',
        'parent_id',
        'show_in_menu',
        'is_homepage',
        'order',
        'published_at'
    ];

    protected $casts = [
        'show_in_menu' => 'boolean',
        'is_homepage' => 'boolean',
        'published_at' => 'datetime',
    ];

    // Tự động tạo slug từ title nếu không được cung cấp
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($page) {
            if (empty($page->slug)) {
                $page->slug = static::createUniqueSlug($page->title);
            }
            
            // Set meta title if empty
            if (empty($page->meta_title)) {
                $page->meta_title = $page->title;
            }
            
            // Set published_at if status is published and published_at is null
            if ($page->status == 'published' && $page->published_at === null) {
                $page->published_at = now();
            }
        });
        
        static::updating(function ($page) {
            // Update slug if title changed and slug not manually set
            if ($page->isDirty('title') && !$page->isDirty('slug')) {
                $page->slug = static::createUniqueSlug($page->title, $page->id);
            }
            
            // Set published_at when status changes to published
            if ($page->isDirty('status') && $page->status == 'published' && $page->published_at === null) {
                $page->published_at = now();
            }
        });
    }
    
    // Helper to create unique slug
    protected static function createUniqueSlug($title, $ignore_id = null)
    {
        $slug = Str::slug($title);
        $count = 1;
        $originalSlug = $slug;
        
        $query = static::where('slug', $slug);
        if ($ignore_id) {
            $query->where('id', '!=', $ignore_id);
        }
        
        while ($query->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
            
            $query = static::where('slug', $slug);
            if ($ignore_id) {
                $query->where('id', '!=', $ignore_id);
            }
        }
        
        return $slug;
    }

    // Định nghĩa quan hệ parent-child
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }
    
    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('order');
    }
    
    // Scope để lấy trang đã xuất bản
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                    ->where(function($q) {
                        $q->whereNull('published_at')
                          ->orWhere('published_at', '<=', now());
                    });
    }
    
    // Lấy trang chủ
    public static function getHomepage()
    {
        return static::where('is_homepage', true)->first();
    }
    
    // Kiểm tra xem trang có phải là trang chủ không
    public function isHomepage(): bool
    {
        return $this->is_homepage;
    }
    
    // Lấy full URL
    public function getUrl(): string
    {
        return url($this->isHomepage() ? '/' : '/page/' . $this->slug);
    }
    
    // Xác định trạng thái hiển thị
    public function getStatusLabel(): string
    {
        if ($this->status == 'published') {
            if ($this->published_at && $this->published_at->isFuture()) {
                return 'Lên lịch: ' . $this->published_at->format('d/m/Y H:i');
            }
            return 'Đã xuất bản';
        }
        return 'Bản nháp';
    }
}
