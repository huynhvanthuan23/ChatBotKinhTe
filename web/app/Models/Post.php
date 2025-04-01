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

    // Tự động tạo slug từ title khi lưu
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($post) {
            $post->slug = Str::slug($post->title);
        });
    }

    // Relationship với user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
