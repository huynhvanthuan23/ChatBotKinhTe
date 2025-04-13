<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'image',
        'status',
        'user_id'
    ];

    // Tự động tạo slug độc nhất khi tạo bài đăng mới
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($post) {
            // Tạo slug từ title nếu chưa có
            if (empty($post->slug)) {
                $post->slug = static::createUniqueSlug($post->title);
            }
        });
        
        static::updating(function ($post) {
            // Cập nhật slug khi title thay đổi
            if ($post->isDirty('title')) {
                $post->slug = static::createUniqueSlug($post->title, $post->id);
            }
        });
    }
    
    /**
     * Tạo slug duy nhất
     */
    protected static function createUniqueSlug($title, $ignore_id = null)
    {
        // Tạo slug ban đầu
        $slug = Str::slug($title);
        
        // Kiểm tra nếu slug đã tồn tại
        $count = 1;
        $originalSlug = $slug;
        
        // Query để kiểm tra trùng lặp, bỏ qua ID hiện tại nếu có
        $query = static::where('slug', $slug);
        if ($ignore_id) {
            $query->where('id', '!=', $ignore_id);
        }
        
        // Thêm số vào slug cho đến khi không còn trùng lặp
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

    // Quan hệ với user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    // Kiểm tra có ảnh hay không
    public function hasImage()
    {
        return !empty($this->image) && file_exists(public_path('storage/' . $this->image));
    }
}
