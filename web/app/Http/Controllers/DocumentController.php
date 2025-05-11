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
    public function show($id, Request $request)
    {
        $document = Document::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
            
        // Lấy tham số page và highlight từ request nếu có
        $page = $request->query('page');
        $highlight = $request->query('highlight');
        
        // Log thông tin cho debug
        if ($page || $highlight) {
            Log::info('Document view with citation parameters', [
                'document_id' => $id,
                'page' => $page,
                'highlight' => $highlight
            ]);
        }
        
        return view('documents.show', compact('document', 'page', 'highlight'));
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
            
            // Gọi API Python để xóa vector tài liệu
            try {
                $user_id = $document->user_id;
                $document_id = $document->id;
                $apiUrl = config('services.chatbot.delete_url', 'http://localhost:55050/api/v1/documents/delete');
                
                // Log thông tin gọi API
                Log::info("Gọi API xóa vector tài liệu: {$apiUrl}", [
                    'document_id' => $document_id,
                    'user_id' => $user_id
                ]);
                
                // Gọi API xóa vector
                $response = Http::timeout(10)->delete($apiUrl, [
                    'document_id' => $document_id,
                    'user_id' => $user_id
                ]);
                
                if ($response->successful()) {
                    Log::info("Đã xóa vector tài liệu thành công: document_id={$document_id}, user_id={$user_id}");
                } else {
                    Log::warning("Không thể xóa vector tài liệu: document_id={$document_id}, user_id={$user_id}", [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                }
            } catch (\Exception $api_e) {
                // Chỉ log lỗi, không dừng quá trình xóa record
                Log::error("Lỗi khi gọi API xóa vector: {$api_e->getMessage()}", [
                    'document_id' => $document->id,
                    'trace' => $api_e->getTraceAsString()
                ]);
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
    
    /**
     * Hiển thị nội dung tài liệu Word dưới dạng HTML
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function viewDocument($id, Request $request)
    {
        try {
            // Tìm tài liệu trong database
            $document = Document::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();
            
            // Đường dẫn đến file
            $filePath = storage_path('app/public/' . $document->file_path);
            
            if (!file_exists($filePath)) {
                return redirect()->route('documents.show', $id)
                    ->with('error', 'Không tìm thấy tài liệu');
            }
            
            // Lấy tham số trích dẫn từ URL
            $page = $request->query('page');
            $highlight = $request->query('highlight');
            $citationText = $request->query('citation_text');
            
            // Log thông tin cho debug
            if ($page || $highlight || $citationText) {
                Log::info('DOCX view with citation parameters', [
                    'document_id' => $id,
                    'page' => $page,
                    'highlight' => $highlight,
                    'citation_text' => $citationText ? substr($citationText, 0, 50) . '...' : null
                ]);
            }
            
            // Xác định loại file
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            // Nếu là file Word
            if (in_array($extension, ['docx', 'doc'])) {
                // Chỉ hỗ trợ .docx, không hỗ trợ .doc cũ
                if ($extension == 'doc') {
                    return redirect()->route('documents.show', $id)
                        ->with('error', 'Định dạng .doc cũ không được hỗ trợ xem trực tiếp. Vui lòng tải xuống để xem.');
                }
                
                try {
                    // Tạo đường dẫn cache
                    $cacheDir = storage_path('app/document_cache');
                    if (!file_exists($cacheDir)) {
                        mkdir($cacheDir, 0755, true);
                    }
                    
                    // Tạo tên file cache duy nhất dựa trên ID tài liệu và thời gian sửa đổi
                    $fileModTime = filemtime($filePath);
                    $cacheFilename = 'doc_' . $id . '_' . md5($filePath . $fileModTime) . '.html';
                    $cachePath = $cacheDir . '/' . $cacheFilename;
                    
                    // Kiểm tra xem cache có tồn tại không
                    if (file_exists($cachePath) && is_readable($cachePath)) {
                        Log::info('Using file cached HTML for document ID: ' . $id);
                        try {
                            $htmlContent = file_get_contents($cachePath);
                            if ($htmlContent === false) {
                                throw new \Exception("Không thể đọc file cache mặc dù file tồn tại");
                            }
                            
                            // Lưu vào Laravel Cache để truy cập nhanh hơn lần sau
                            $cacheKey = 'docx_html_' . md5($filePath . filemtime($filePath));
                            cache()->put($cacheKey, $htmlContent, now()->addDay());
                        } catch (\Exception $cacheReadException) {
                            Log::error('Lỗi đọc file cache: ' . $cacheReadException->getMessage(), [
                                'document_id' => $id,
                                'cache_path' => $cachePath,
                                'permissions' => file_exists($cachePath) ? decoct(fileperms($cachePath) & 0777) : 'N/A'
                            ]);
                            
                            // Xóa file cache bị lỗi 
                            @unlink($cachePath);
                            
                            // Chuyển sang tạo cache mới
                            Log::info('Tiến hành tạo cache mới sau khi xảy ra lỗi đọc cache');
                            $needToCreateCache = true;
                        }
                    } else {
                        $needToCreateCache = true;
                    }
                    
                    // Nếu cần tạo cache mới
                    if (isset($needToCreateCache) && $needToCreateCache) {
                        Log::info('Converting document to HTML for ID: ' . $id);
                        
                        // Sử dụng PHPWord để chuyển đổi
                        $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
                        $htmlWriter = new \PhpOffice\PhpWord\Writer\HTML($phpWord);
                        
                        // Lưu vào file tạm
                        $tempFile = storage_path('app/temp_' . uniqid() . '.html');
                        $htmlWriter->save($tempFile);
                        
                        // Đọc nội dung từ file tạm
                        $htmlContent = file_get_contents($tempFile);
                        
                        // Xóa file tạm
                        if (file_exists($tempFile)) {
                            unlink($tempFile);
                        }
                        
                        // Làm sạch và cải thiện HTML
                        $htmlContent = $this->cleanWordHtml($htmlContent);
                        
                        // Xử lý bổ sung để cải thiện bố cục
                        $htmlContent = $this->improveLayout($htmlContent);
                        
                        // Lưu kết quả vào cache
                        try {
                            file_put_contents($cachePath, $htmlContent);
                            Log::info('Saved HTML cache for document ID: ' . $id);
                            
                            // Đảm bảo quyền truy cập
                            @chmod($cachePath, 0644);
                            
                            // Lưu vào Laravel Cache
                            $cacheKey = 'docx_html_' . md5($filePath . filemtime($filePath));
                            cache()->put($cacheKey, $htmlContent, now()->addDay());
                        } catch (\Exception $cacheWriteException) {
                            Log::error('Lỗi khi ghi file cache: ' . $cacheWriteException->getMessage(), [
                                'document_id' => $id,
                                'cache_path' => $cachePath
                            ]);
                        }
                        
                        // Xóa các file cache cũ
                        $this->cleanOldCacheFiles($cacheDir, 'doc_' . $id . '_', 5);
                    }
                    
                    // Trả về view với nội dung HTML và các tham số trích dẫn
                    return view('documents.view_word', [
                        'document' => $document,
                        'htmlContent' => $htmlContent,
                        'page' => $page,
                        'highlight' => $highlight,
                        'citation_text' => $citationText
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error converting Word document: ' . $e->getMessage(), [
                        'document_id' => $id,
                        'file_path' => $document->file_path
                    ]);
                    
                    return redirect()->route('documents.show', $id)
                        ->with('error', 'Không thể xử lý tài liệu Word: ' . $e->getMessage());
                }
            }
            
            // Nếu không phải file Word, chuyển hướng đến phương thức hiển thị thông thường
            return redirect()->route('documents.show', $id);
            
        } catch (\Exception $e) {
            Log::error('Error in viewDocument: ' . $e->getMessage(), [
                'document_id' => $id
            ]);
            
            return redirect()->route('documents.index')
                ->with('error', 'Có lỗi xảy ra khi xử lý tài liệu: ' . $e->getMessage());
        }
    }
    
    /**
     * Phương pháp trích xuất nội dung DOCX cải tiến
     * 
     * @param string $filePath
     * @return string
     */
    private function extractDocxContentAlt($filePath)
    {
        try {
            // Thử phương pháp cải tiến trước
            // Kiểm tra xem có tồn tại cache không
            $cacheKey = 'docx_html_' . md5($filePath . filemtime($filePath));
            if (cache()->has($cacheKey)) {
                Log::info('Using Laravel cache for DOCX extraction: ' . $filePath);
                return cache()->get($cacheKey);
            }
            
            // Phương pháp sử dụng PHPWord
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
            $objWriter = new \PhpOffice\PhpWord\Writer\HTML($phpWord);
            
            // Lưu HTML vào file tạm
            $tempFile = storage_path('app/temp_' . uniqid() . '.html');
            $objWriter->save($tempFile);
            
            // Đọc nội dung từ file tạm
            $htmlContent = file_get_contents($tempFile);
            
            // Xóa file tạm
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            // Làm sạch và cải thiện HTML
            $htmlContent = $this->cleanWordHtml($htmlContent);
            
            // Lưu vào Laravel cache với thời gian 24 giờ
            try {
                cache()->put($cacheKey, $htmlContent, 60 * 24);
                Log::info('Saved DOCX HTML to Laravel cache: ' . $filePath);
            } catch (\Exception $e) {
                Log::error('Could not save to Laravel cache: ' . $e->getMessage(), [
                    'file_path' => $filePath
                ]);
            }
            
            return $htmlContent;
        } catch (\Exception $e) {
            Log::error('Error in extractDocxContentAlt: ' . $e->getMessage(), [
                'file_path' => $filePath,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Giải nén thủ công nếu PHPWord thất bại
            try {
                return $this->manualDocxExtraction($filePath);
            } catch (\Exception $e2) {
                Log::error('Manual extraction also failed: ' . $e2->getMessage());
                
                // Trả về thông báo lỗi dạng HTML
                return $this->getErrorHtml('Không thể xử lý tài liệu Word');
            }
        }
    }
    
    /**
     * Phương pháp giải nén thủ công và trích xuất nội dung Word
     * 
     * @param string $filePath
     * @return string
     */
    private function manualDocxExtraction($filePath)
    {
        try {
            // Tạo thư mục tạm
            $tempDir = storage_path('app/temp_' . uniqid());
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            // Giải nén DOCX (thực chất là tệp ZIP)
            $zip = new \ZipArchive();
            if ($zip->open($filePath) === true) {
                $zip->extractTo($tempDir);
                $zip->close();
                
                // Đọc document.xml
                $xmlContent = file_get_contents($tempDir . '/word/document.xml');
                
                // Tạo HTML đơn giản từ nội dung
                $html = '<div class="word-document">';
                
                // Sử dụng SimpleXML để phân tích
                $xml = simplexml_load_string($xmlContent);
                $namespaces = $xml->getNamespaces(true);
                
                // Trích xuất văn bản từ XML
                $body = $xml->xpath('//w:body')[0] ?? null;
                
                if ($body) {
                    // Lấy tất cả các đoạn
                    $paragraphs = $body->xpath('.//w:p');
                    foreach ($paragraphs as $paragraph) {
                        $html .= '<p>';
                        $textRuns = $paragraph->xpath('.//w:t');
                        $paragraphText = '';
                        
                        foreach ($textRuns as $textRun) {
                            $paragraphText .= htmlspecialchars((string)$textRun);
                        }
                        
                        $html .= $paragraphText ?: '&nbsp;';
                        $html .= '</p>';
                    }
                }
                
                $html .= '</div>';
                
                // Xóa thư mục tạm
                $this->removeTempDir($tempDir);
                
                return $this->cleanWordHtml($html);
            }
            
            throw new \Exception('Không thể giải nén tệp DOCX');
        } catch (\Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Tạo HTML thông báo lỗi
     * 
     * @param string $message
     * @return string
     */
    private function getErrorHtml($message)
    {
        return '<div class="error-container" style="padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; text-align: center;">
            <h3 style="margin-bottom: 15px;"><i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i>Không thể hiển thị tài liệu</h3>
            <p style="margin-bottom: 15px;">' . htmlspecialchars($message) . '</p>
            <p>Vui lòng tải xuống tài liệu để xem nội dung đầy đủ.</p>
        </div>';
    }
    
    /**
     * Xóa thư mục tạm và tất cả nội dung bên trong
     * 
     * @param string $dir
     * @return void
     */
    private function removeTempDir($dir)
    {
        if (!file_exists($dir) || !is_dir($dir)) {
            return;
        }
        
        try {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    $fullPath = $dir . DIRECTORY_SEPARATOR . $object;
                    
                    // Kiểm tra quyền truy cập
                    if (!is_readable($fullPath)) {
                        Log::warning("Cannot read path during cleanup: {$fullPath}, skipping");
                        continue;
                    }
                    
                    if (is_dir($fullPath)) {
                        // Xử lý đệ quy cho thư mục con
                        $this->removeTempDir($fullPath);
                    } else {
                        // Kiểm tra quyền xóa tệp
                        if (!is_writable($fullPath)) {
                            Log::warning("Cannot delete file, no write permission: {$fullPath}");
                            continue;
                        }
                        
                        try {
                            if (!unlink($fullPath)) {
                                Log::warning("Failed to delete file: {$fullPath}");
                            }
                        } catch (\Exception $e) {
                            Log::warning("Error deleting file: {$fullPath}, message: {$e->getMessage()}");
                        }
                    }
                }
            }
            
            // Kiểm tra quyền xóa thư mục
            if (!is_writable($dir)) {
                Log::warning("Cannot remove directory, no write permission: {$dir}");
                return;
            }
            
            try {
                if (!rmdir($dir)) {
                    Log::warning("Failed to remove directory: {$dir}");
                }
            } catch (\Exception $e) {
                Log::warning("Error removing directory: {$dir}, message: {$e->getMessage()}");
            }
        } catch (\Exception $e) {
            Log::error("Error in removeTempDir: {$e->getMessage()}", [
                'dir' => $dir
            ]);
        }
    }
    
    /**
     * Làm sạch và cải thiện HTML từ PHPWord
     *
     * @param string $html
     * @return string
     */
    private function cleanWordHtml($html)
    {
        // Thêm CSS cơ bản cho giao diện đẹp hơn
        $styles = '<style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; padding: 20px; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
            table, th, td { border: 1px solid #ddd; }
            th, td { padding: 8px; text-align: left; }
            .word-document, .word-content { max-width: 900px; margin: 0 auto; }
            img { max-width: 100%; height: auto; }
            h1, h2, h3, h4, h5, h6 { margin-top: 1.5rem; margin-bottom: 1rem; color: #4098e5; }
            /* Giảm khoảng cách giữa các đoạn */
            p { margin-bottom: 0.5rem; }
            /* Ẩn các thẻ p trống không có nội dung */
            p:empty { display: none; }
            /* Cải thiện các định dạng đặc biệt của Word */
            span.MsoHyperlink { color: #0563c1; text-decoration: underline; }
            p.MsoNormal { margin: 0.3rem 0; }
            .highlight { background-color: yellow; }
        </style>';
        
        // Kiểm tra xem HTML có chứa nội dung không
        if (empty(trim(strip_tags($html)))) {
            return '<html><head>' . $styles . '</head><body><div class="word-document">
                <div style="padding: 15px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;">
                    <h3>Tài liệu trống</h3>
                    <p>Rất tiếc, không tìm thấy nội dung trong tài liệu này. Vui lòng tải xuống để xem đầy đủ.</p>
                </div>
            </div></body></html>';
        }
        
        // Xử lý các vấn đề xuống dòng quá nhiều
        // 1. Thay thế nhiều thẻ p trống liên tiếp thành một thẻ duy nhất
        $html = preg_replace('/<p[^>]*>(\s|&nbsp;)*<\/p>(\s*<p[^>]*>(\s|&nbsp;)*<\/p>)+/', '<p>&nbsp;</p>', $html);
        
        // 2. Loại bỏ các khoảng trắng thừa trong thẻ
        $html = preg_replace('/<p[^>]*>\s+/', '<p>', $html);
        $html = preg_replace('/\s+<\/p>/', '</p>', $html);
        
        // 3. Loại bỏ nhiều thẻ br liên tiếp
        $html = preg_replace('/(<br\s*\/?>){2,}/', '<br/>', $html);
        
        // Thêm doctype và charset nếu chưa có
        if (strpos($html, '<!DOCTYPE') === false) {
            $html = '<!DOCTYPE html>' . $html;
        }
        
        if (strpos($html, '<meta charset') === false && strpos($html, '<head>') !== false) {
            $html = str_replace('<head>', '<head><meta charset="UTF-8">', $html);
        }
        
        // Chèn CSS vào phần head
        if (strpos($html, '<head>') !== false) {
            $html = str_replace('<head>', '<head>' . $styles, $html);
        } else {
            $html = '<!DOCTYPE html><html><head>' . $styles . '</head><body>' . $html . '</body></html>';
        }
        
        // Bao bọc nội dung trong một div có class để dễ tùy chỉnh CSS
        if (strpos($html, '<body>') !== false && strpos($html, 'class="word-document"') === false && strpos($html, 'class="word-content"') === false) {
            $html = str_replace('<body>', '<body><div class="word-document">', $html);
            $html = str_replace('</body>', '</div></body>', $html);
        }
        
        return $html;
    }

    /**
     * Xóa các file cache cũ, chỉ giữ lại số lượng tối đa xác định
     * 
     * @param string $cacheDir
     * @param string $prefix
     * @param int $maxFiles
     * @return void
     */
    private function cleanOldCacheFiles($cacheDir, $prefix, $maxFiles = 5)
    {
        try {
            // Lấy danh sách tất cả các file cache có prefix chỉ định
            $files = glob($cacheDir . '/' . $prefix . '*.html');
            
            // Sắp xếp theo thời gian sửa đổi (mới nhất sau cùng)
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Nếu số lượng file vượt quá giới hạn, xóa các file cũ nhất
            $fileCount = count($files);
            if ($fileCount > $maxFiles) {
                $filesToDelete = array_slice($files, 0, $fileCount - $maxFiles);
                foreach ($filesToDelete as $file) {
                    unlink($file);
                    Log::info('Deleted old cache file: ' . basename($file));
                }
            }
        } catch (\Exception $e) {
            Log::error('Error cleaning old cache files: ' . $e->getMessage());
        }
    }

    /**
     * Xóa cache của tài liệu và tạo lại
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reloadDocCache($id)
    {
        try {
            // Tìm tài liệu trong database
            $document = Document::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();
            
            // Đường dẫn đến file
            $filePath = storage_path('app/public/' . $document->file_path);
            
            if (!file_exists($filePath)) {
                return redirect()->route('documents.show', $id)
                    ->with('error', 'Không tìm thấy tài liệu');
            }
            
            // Xác định loại file
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            // Chỉ xử lý với file docx
            if (in_array($extension, ['docx'])) {
                // Xóa cache Laravel
                $cacheKey = 'docx_html_' . md5($filePath . filemtime($filePath));
                cache()->forget($cacheKey);
                
                // Xóa file cache
                $cacheDir = storage_path('app/document_cache');
                $cachePattern = 'doc_' . $id . '_*.html';
                $cacheFiles = glob($cacheDir . '/' . $cachePattern);
                
                foreach ($cacheFiles as $cacheFile) {
                    try {
                        if (file_exists($cacheFile) && is_writable($cacheFile)) {
                            unlink($cacheFile);
                            Log::info('Deleted document cache file: ' . basename($cacheFile));
                        } else {
                            Log::warning('Cannot delete cache file: ' . $cacheFile);
                        }
                    } catch (\Exception $e) {
                        Log::error('Error deleting cache file: ' . $e->getMessage(), [
                            'file' => $cacheFile
                        ]);
                    }
                }
                
                Log::info('Document cache cleared for ID: ' . $id);
                
                // Chuyển hướng đến phương thức xem tài liệu để tạo cache mới
                return redirect()->route('documents.view', $id)
                    ->with('success', 'Đã làm mới cache tài liệu');
            }
            
            return redirect()->route('documents.show', $id)
                ->with('error', 'Chỉ hỗ trợ làm mới cache cho tài liệu DOCX');
            
        } catch (\Exception $e) {
            Log::error('Error in reloadDocCache: ' . $e->getMessage(), [
                'document_id' => $id
            ]);
            
            return redirect()->route('documents.index')
                ->with('error', 'Có lỗi xảy ra khi làm mới cache: ' . $e->getMessage());
        }
    }

    /**
     * Thực hiện xử lý bổ sung để cải thiện bố cục HTML
     * 
     * @param string $html
     * @return string
     */
    private function improveLayout($html)
    {
        // 1. Loại bỏ các thẻ p trống liên tiếp
        $html = preg_replace('/<p[^>]*>(&nbsp;|\s)*<\/p>\s*<p[^>]*>/', '<p>', $html);
        
        // 2. Loại bỏ các thẻ div trống
        $html = preg_replace('/<div[^>]*>(\s|&nbsp;)*<\/div>/', '', $html);
        
        // 3. Thay thế nhiều khoảng trắng liên tiếp bằng một khoảng trắng
        $html = preg_replace('/\s{2,}/', ' ', $html);
        
        // 4. Xử lý các trường hợp xuống dòng đặc biệt từ Word
        $html = str_replace('<p><o:p>&nbsp;</o:p></p>', '', $html);
        $html = str_replace('<o:p>&nbsp;</o:p>', '', $html);
        
        // 5. Loại bỏ các thuộc tính không cần thiết
        $html = preg_replace('/(<[^>]+) style="[^"]*"/', '$1', $html);
        $html = preg_replace('/(<[^>]+) class="Mso[^"]*"/', '$1', $html);
        
        // 6. Loại bỏ thẻ div trống giữa các đoạn văn
        $html = preg_replace('/<\/p>\s*<div[^>]*>\s*<\/div>\s*<p/', '</p><p', $html);
        
        // 7. Xử lý các thẻ span không cần thiết
        $html = preg_replace('/<span[^>]*>([^<]*)<\/span>/', '$1', $html);
        
        // 8. Loại bỏ các đoạn trùng lặp
        $html = preg_replace('/(<p[^>]*>[^<]+<\/p>)(\s*\1)+/', '$1', $html);
        
        // 9. Xóa các tham chiếu và định dạng đặc biệt của MS Word
        $html = preg_replace('/<\/?o:[^>]+>/', '', $html);  // Loại bỏ các thẻ 'o:*'
        $html = preg_replace('/<\/?w:[^>]+>/', '', $html);  // Loại bỏ các thẻ 'w:*'
        $html = preg_replace('/<\/?m:[^>]+>/', '', $html);  // Loại bỏ các thẻ 'm:*'
        
        // 10. Xử lý các trường hợp xuống dòng quá nhiều trong văn bản
        $html = preg_replace('/<\/p>\s*<p[^>]*>\s*<\/p>\s*<p/', '</p><p', $html);
        
        // 11. Ghép các đoạn bị tách quá nhiều
        $html = preg_replace('/<\/p>\s*<p[^>]*>([a-z0-9,;])/', '$1', $html);
        
        return $html;
    }

    /**
     * API endpoint để lấy thông tin về document cho Python backend
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDocumentInfo($id)
    {
        try {
            Log::info("[API] Yêu cầu lấy thông tin document_id={$id} từ Python backend");
            
            // Tìm document (không cần xác thực user vì đây là API nội bộ)
            $document = Document::find($id);
            
            if (!$document) {
                Log::warning("[API] Không tìm thấy document_id={$id}");
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found'
                ], 404);
            }
            
            // Trả về thông tin cần thiết
            return response()->json([
                'success' => true,
                'document' => [
                    'id' => $document->id,
                    'user_id' => $document->user_id,
                    'title' => $document->title,
                    'file_name' => $document->file_name,
                    'file_type' => $document->file_type,
                    'file_path' => $document->file_path,
                    'vector_status' => $document->vector_status,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("[API] Lỗi khi lấy thông tin document: " . $e->getMessage(), [
                'document_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error getting document info: ' . $e->getMessage()
            ], 500);
        }
    }
}
