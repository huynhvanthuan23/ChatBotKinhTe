<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function index()
    {
        $media = Media::latest()->paginate(16);
        return view('admin.media.index', compact('media'));
    }

    public function create()
    {
        return view('admin.media.upload');
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'file' => 'required|file|max:5120', // 5MB max
            'title' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $fileType = $file->getClientMimeType();
        $filePath = $file->storeAs('uploads', $fileName, 'public');

        Media::create([
            'title' => $request->title ?? $file->getClientOriginalName(),
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_type' => $fileType,
            'file_size' => $file->getSize(),
        ]);

        return redirect()->route('admin.media.index')
            ->with('success', 'File đã được tải lên thành công.');
    }

    public function destroy($id)
    {
        $media = Media::findOrFail($id);
        
        // Xóa file vật lý
        if (Storage::disk('public')->exists($media->file_path)) {
            Storage::disk('public')->delete($media->file_path);
        }
        
        // Xóa record trong DB
        $media->delete();

        return redirect()->route('admin.media.index')
            ->with('success', 'File đã được xóa thành công.');
    }
} 