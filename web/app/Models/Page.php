<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'status',
        'show_in_menu',
        'order'
    ];

    // Tự động tạo slug từ title khi lưu
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($page) {
            $page->slug = Str::slug($page->title);
        });
    }
}
