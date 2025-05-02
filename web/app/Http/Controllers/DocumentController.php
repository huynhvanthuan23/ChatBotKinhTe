<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;
use App\Jobs\ProcessDocumentVectors;

class DocumentController extends Controller
{
    /**
     * Display a listing of the documents.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = Auth::user();
        $documents = Document::byUser($user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        // Lấy danh sách tài liệu đã chọn từ session
        $selectedDocumentIds = session('selected_document_ids', []);
            
        return view('documents.index', compact('documents', 'selectedDocumentIds'));
    }

    /**
     * Show the form for creating a new document.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('documents.create');
    }

    /**
     * Store a newly created document in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'document_file' => 'required|file|mimes:pdf,doc,docx,txt,md|max:10240', // Max 10MB
        ]);

        try {
            $file = $request->file('document_file');
            $originalName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $fileType = $file->getMimeType();
            
            // Tạo tên file duy nhất
            $fileName = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            
            // Lưu file vào storage
            $filePath = $file->storeAs('documents/' . Auth::id(), $fileName, 'public');
            
            // Tạo record trong database
            $document = Document::create([
                'user_id' => Auth::id(),
                'title' => $request->title,
                'description' => $request->description,
                'file_path' => $filePath,
                'file_name' => $originalName,
                'file_size' => $fileSize,
                'file_type' => $fileType,
                'status' => 'uploaded',
                'vector_status' => 'pending'
            ]);
            
            return redirect()->route('documents.index')
                ->with('success', 'Tài liệu đã được tải lên thành công. Vui lòng nhấn nút tạo vector để xử lý tài liệu.');
        } catch (\Exception $e) {
            Log::error('Document upload error: ' . $e->getMessage());
            return back()->with('error', 'Có lỗi xảy ra khi tải tài liệu: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified document.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $document = Document::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
            
        return view('documents.show', compact('document'));
    }

    /**
     * Remove the specified document from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        $document = Document::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
            
        try {
            // Xóa file từ storage
            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }
            
            // Xóa record
            $document->delete();
            
            return redirect()->route('documents.index')
                ->with('success', 'Tài liệu đã được xóa thành công.');
        } catch (\Exception $e) {
            Log::error('Document delete error: ' . $e->getMessage());
            return back()->with('error', 'Có lỗi xảy ra khi xóa tài liệu.');
        }
    }
    
    /**
     * Tạo vector cho một tài liệu cụ thể.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function createVector($id)
    {
        try {
            $document = Document::findOrFail($id);
            
            // Cập nhật trạng thái
            $document->vector_status = 'processing';
            $document->save();

            // Sử dụng job để xử lý tài liệu
            ProcessDocumentVectors::dispatch($document);

            return response()->json([
                'status' => 'success',
                'message' => 'Vector processing started',
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating vector', [
                'document_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Tạo vector cho tất cả tài liệu chưa được xử lý.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createAllVectors()
    {
        try {
            // Lấy tất cả documents có trạng thái pending hoặc failed
            $documents = Document::whereIn('vector_status', ['pending', 'failed'])->get();
            
            if ($documents->isEmpty()) {
                return response()->json([
                    'status' => 'info',
                    'message' => 'No documents need vector processing',
                ]);
            }

            // Cập nhật trạng thái và dispatch job cho từng tài liệu
            foreach ($documents as $document) {
                $document->vector_status = 'processing';
                $document->save();
                
                // Dispatch job để xử lý tài liệu
                ProcessDocumentVectors::dispatch($document);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Started processing ' . $documents->count() . ' documents',
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating vectors for all documents', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Update the vector status of a document (callback from Python API)
     */
    public function updateVectorStatus(Request $request)
    {
        try {
            $validated = $request->validate([
                'document_id' => 'required|integer',
                'status' => 'required|string|in:completed,failed',
                'message' => 'nullable|string',
            ]);

            $document = Document::findOrFail($validated['document_id']);
            $document->vector_status = $validated['status'];
            $document->save();

            Log::info('Document vector status updated', [
                'document_id' => $document->id,
                'status' => $validated['status'],
                'message' => $validated['message'] ?? '',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Vector status updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating vector status', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Hiển thị giao diện chat với tài liệu đã được xử lý.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function showChat($id)
    {
        try {
            $document = Document::where('id', $id)
                ->where('user_id', Auth::id())
                ->where('vector_status', 'completed')
                ->firstOrFail();
            
            // Chuyển hướng đến trang chat với tham số doc_ids
            return redirect()->route('chat', ['doc_ids' => $id]);
                
        } catch (\Exception $e) {
            Log::error('Error showing document chat', [
                'document_id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return redirect()->route('documents.index')
                ->with('error', 'Không thể truy cập chức năng chat với tài liệu này. Hãy đảm bảo tài liệu đã được xử lý vector.');
        }
    }

    /**
     * Xử lý yêu cầu hỏi đáp trên nhiều tài liệu đã chọn.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function askSelected(Request $request)
    {
        $validated = $request->validate([
            'selected_documents' => 'required|array',
            'selected_documents.*' => 'exists:documents,id',
        ]);

        try {
            $documentIds = $validated['selected_documents'];
            $userId = Auth::id();
            
            // Đảm bảo các tài liệu thuộc về người dùng hiện tại
            $documents = Document::whereIn('id', $documentIds)
                ->where('user_id', $userId)
                ->where('vector_status', 'completed')
                ->get();
            
            if ($documents->isEmpty()) {
                return redirect()->route('documents.index')
                    ->with('error', 'Không tìm thấy tài liệu hợp lệ hoặc tài liệu chưa được xử lý vector.');
            }
            
            // Lấy IDs của tài liệu
            $docIds = $documents->pluck('id')->toArray();
            
            // Lưu danh sách document IDs vào session để sử dụng khi chat
            $request->session()->put('selected_document_ids', $docIds);
            
            // Log thông tin session đã được lưu
            Log::info('Document IDs saved to session', [
                'session_id' => $request->session()->getId(),
                'document_ids' => $docIds,
                'count' => $documents->count(),
                'user_id' => Auth::id()
            ]);
            
            // Chuyển hướng đến trang chat với tham số doc_ids trong URL
            return redirect()->route('chat', ['doc_ids' => implode(',', $docIds)])
                ->with('success', 'Đã chọn ' . $documents->count() . ' tài liệu để hỏi đáp.');
        } catch (\Exception $e) {
            Log::error('Error in askSelected', [
                'error' => $e->getMessage(),
                'document_ids' => $request->selected_documents ?? [],
            ]);
            
            return redirect()->route('documents.index')
                ->with('error', 'Đã xảy ra lỗi khi xử lý yêu cầu: ' . $e->getMessage());
        }
    }

    /**
     * Lưu danh sách tài liệu đã chọn vào session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveSelection(Request $request)
    {
        try {
            $validated = $request->validate([
                'doc_ids' => 'required|array',
                'doc_ids.*' => 'exists:documents,id',
            ]);
            
            $documentIds = $validated['doc_ids'];
            $userId = Auth::id();
            
            // Đảm bảo các tài liệu thuộc về người dùng hiện tại
            $documents = Document::whereIn('id', $documentIds)
                ->where('user_id', $userId)
                ->where('vector_status', 'completed')
                ->get();
            
            if ($documents->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy tài liệu hợp lệ hoặc tài liệu chưa được xử lý vector.'
                ]);
            }
            
            // Lưu danh sách document IDs vào session
            $request->session()->put('selected_document_ids', $documents->pluck('id')->toArray());
            
            // Log thông tin session đã được lưu
            Log::info('Document selection saved to session via API', [
                'session_id' => $request->session()->getId(),
                'document_ids' => $documents->pluck('id')->toArray(),
                'count' => $documents->count(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Đã lưu lựa chọn tài liệu vào session',
                'count' => $documents->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving document selection', [
                'error' => $e->getMessage(),
                'document_ids' => $request->doc_ids ?? [],
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi khi lưu lựa chọn: ' . $e->getMessage()
            ], 500);
        }
    }
}
