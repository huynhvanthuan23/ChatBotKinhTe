<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'file_name',
        'mime_type',
        'path',
        'disk',
        'file_hash',
        'size',
        'user_id'
    ];

    /**
     * Lấy đường dẫn đầy đủ của file
     */
    public function getFullUrl()
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Kiểm tra xem file có phải là hình ảnh không
     */
    public function isImage()
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Kiểm tra xem file có phải là video không
     */
    public function isVideo()
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Kiểm tra xem file có phải là tài liệu không
     */
    public function isDocument()
    {
        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain'
        ];
        
        return in_array($this->mime_type, $documentTypes);
    }

    /**
     * Lấy icon cho loại file
     */
    public function getIconClass()
    {
        if ($this->isImage()) {
            return 'fa-image';
        } elseif ($this->isVideo()) {
            return 'fa-video';
        } elseif ($this->isDocument()) {
            if ($this->mime_type == 'application/pdf') {
                return 'fa-file-pdf';
            } elseif (str_contains($this->mime_type, 'word')) {
                return 'fa-file-word';
            } elseif (str_contains($this->mime_type, 'excel') || str_contains($this->mime_type, 'spreadsheet')) {
                return 'fa-file-excel';
            } elseif (str_contains($this->mime_type, 'powerpoint') || str_contains($this->mime_type, 'presentation')) {
                return 'fa-file-powerpoint';
            } else {
                return 'fa-file-alt';
            }
        } else {
            return 'fa-file';
        }
    }

    /**
     * Lấy kích thước file ở định dạng dễ đọc
     */
    public function getFormattedSize()
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->size;
        $i = 0;
        
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * Relation với user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
