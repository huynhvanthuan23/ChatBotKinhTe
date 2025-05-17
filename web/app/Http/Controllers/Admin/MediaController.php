<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Nếu chưa có model Media, sử dụng collection trống
        if (!class_exists('App\Models\Media')) {
            return view('admin.media.index', ['media' => collect([])]);
        }
        
        $query = Media::query()->latest();
        
        // Filter by type
        if ($request->has('type')) {
            $type = $request->input('type');
            if ($type === 'image') {
                $query->where('mime_type', 'like', 'image/%');
            } elseif ($type === 'video') {
                $query->where('mime_type', 'like', 'video/%');
            } elseif ($type === 'document') {
                $query->where(function($q) {
                    $q->where('mime_type', 'like', 'application/pdf')
                      ->orWhere('mime_type', 'like', 'application/msword')
                      ->orWhere('mime_type', 'like', 'application/vnd.openxmlformats-officedocument.%')
                      ->orWhere('mime_type', 'like', 'application/vnd.ms-%');
                });
            }
        }
        
        // Search by name
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }
        
        $media = $query->paginate(24);
        
        return view('admin.media.index', compact('media'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.media.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // Max 10MB
            'name' => 'nullable|string|max:255',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $fileHash = md5_file($file->getRealPath());
            $name = $request->input('name') ?: pathinfo($fileName, PATHINFO_FILENAME);
            
            // Tạo đường dẫn lưu trữ với năm/tháng
            $path = 'media/' . date('Y/m');
            $storedPath = $file->store($path, 'public');
            
            // Lưu thông tin vào database mà không sử dụng cột disk
            $media = new Media();
            $media->name = $name;
            $media->file_name = $fileName;
            $media->mime_type = $file->getMimeType();
            $media->path = $storedPath;
            $media->size = $file->getSize();
            $media->user_id = auth()->id();
            $media->save();
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'media' => $media,
                    'url' => url('storage/' . $storedPath), // URL trực tiếp không qua disk
                ]);
            }
            
            return redirect()->route('admin.media.index')
                ->with('success', 'Tệp tin đã được tải lên thành công.');
        }
        
        if ($request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Không có tệp tin được tải lên.']);
        }
        
        return redirect()->back()->with('error', 'Không có tệp tin được tải lên.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Media $medium)
    {
        return view('admin.media.show', ['media' => $medium]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Media $medium)
    {
        return view('admin.media.edit', ['media' => $medium]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Media $medium)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);
        
        $medium->update([
            'name' => $request->input('name'),
        ]);
        
        return redirect()->route('admin.media.index')
            ->with('success', 'Thông tin tệp tin đã được cập nhật.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Media $medium)
    {
        // Xóa file từ storage không sử dụng disk
        Storage::disk('public')->delete($medium->path);
        
        // Xóa record từ database
        $medium->delete();
        
        return redirect()->route('admin.media.index')
            ->with('success', 'Tệp tin đã được xóa thành công.');
    }
    
    /**
     * Upload từ trình soạn thảo
     */
    public function uploadFromEditor(Request $request)
    {
        $request->validate([
            'upload' => 'required|file|image|max:5120', // Max 5MB, only images
        ]);
        
        if ($request->hasFile('upload')) {
            $file = $request->file('upload');
            $fileName = $file->getClientOriginalName();
            $fileHash = md5_file($file->getRealPath());
            $name = pathinfo($fileName, PATHINFO_FILENAME);
            
            // Tạo đường dẫn lưu trữ với năm/tháng
            $path = 'media/' . date('Y/m');
            $storedPath = $file->store($path, 'public');
            
            // Lưu thông tin vào database mà không sử dụng cột disk
            $media = new Media();
            $media->name = $name;
            $media->file_name = $fileName;
            $media->mime_type = $file->getMimeType();
            $media->path = $storedPath;
            $media->size = $file->getSize();
            $media->user_id = auth()->id();
            $media->save();
            
            // Trả về response định dạng cho CKEditor
            return response()->json([
                'uploaded' => 1,
                'fileName' => $fileName,
                'url' => url('storage/' . $storedPath),
            ]);
        }
        
        return response()->json([
            'uploaded' => 0,
            'error' => ['message' => 'Không có tệp tin được tải lên.'],
        ]);
    }
}

