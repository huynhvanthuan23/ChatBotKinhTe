<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Document;

class CitationController extends Controller
{
    /**
     * Lấy nội dung chi tiết của trích dẫn dựa trên document_id và page
     * 
     * @param int $docId
     * @param int $page
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCitationContent($docId, $page)
    {
        try {
            Log::info("[CITATION-DETAIL] Yêu cầu xem chi tiết trích dẫn: document_id={$docId}, page={$page}, user_id=" . Auth::id());
            
            // Xác minh quyền truy cập tài liệu - chỉ kiểm tra user_id, bỏ điều kiện is_public
            try {
                $document = Document::where('id', $docId);
                
                // Nếu user đã đăng nhập, thêm điều kiện là chủ sở hữu
                if (Auth::check()) {
                    $document = $document->where('user_id', Auth::id());
                }
                
                $document = $document->firstOrFail();
                
                Log::info("[CITATION-DETAIL] Xác minh quyền truy cập tài liệu thành công: document_id={$docId}, title={$document->title}");
            } catch (\Exception $e) {
                Log::error("[CITATION-DETAIL] Không thể tìm thấy tài liệu hoặc không có quyền truy cập: {$e->getMessage()}");
                
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy tài liệu hoặc bạn không có quyền truy cập',
                    'error_type' => 'document_not_found'
                ], 404);
            }
            
            // Kiểm tra file type
            $fileExtension = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));
            $isTextFile = in_array($fileExtension, ['txt', 'md']);
            
            Log::info("[CITATION-DETAIL] Loại file: {$fileExtension}, isTextFile: " . ($isTextFile ? 'true' : 'false'));
            
            // Đường dẫn tới file original_text.json
            $vectorDir = "vector_db/uploads/{$document->user_id}/{$docId}";
            $textPath = "{$vectorDir}/original_text.json";
            
            Log::info("[CITATION-DETAIL] Tìm kiếm nội dung tại đường dẫn: {$textPath}");
            
            if (!Storage::exists($textPath)) {
                Log::error("[CITATION-DETAIL] Không tìm thấy file original_text.json: {$textPath}");
                
                // Với file text, thử đọc trực tiếp từ file gốc
                if ($isTextFile && Storage::exists($document->file_path)) {
                    Log::info("[CITATION-DETAIL] Đọc nội dung trực tiếp từ file text gốc: {$document->file_path}");
                    
                    $content = Storage::get($document->file_path);
                    $paragraphs = preg_split('/\n\s*\n/', $content);
                    
                    // Lấy đoạn theo chỉ số highlight (nếu có)
                    $highlightIndex = request()->get('highlight');
                    $highlightIndex = $highlightIndex !== null ? (int) $highlightIndex : null;
                    
                    if ($highlightIndex !== null && isset($paragraphs[$highlightIndex])) {
                        $pageContent = $paragraphs[$highlightIndex];
                        Log::info("[CITATION-DETAIL] Đã lấy đoạn văn thứ {$highlightIndex} từ file text");
                    } else {
                        // Nếu không có chỉ số highlight hoặc không hợp lệ, trả về toàn bộ nội dung
                        $pageContent = $content;
                        Log::info("[CITATION-DETAIL] Trả về toàn bộ nội dung text (không có highlight index hợp lệ)");
                    }
                    
                    return response()->json([
                        'success' => true,
                        'content' => nl2br(htmlspecialchars($pageContent)),
                        'page' => $page,
                        'file_type' => $fileExtension,
                        'document' => [
                            'id' => $document->id,
                            'title' => $document->title,
                            'metadata' => [
                                'filename' => $document->file_name,
                                'extension' => $fileExtension
                            ]
                        ]
                    ]);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy nội dung tài liệu'
                ]);
            }
            
            // Đọc file original_text.json
            $originalText = json_decode(Storage::get($textPath), true);
            Log::info("[CITATION-DETAIL] Đã đọc file original_text.json, tìm thấy " . count($originalText) . " phân đoạn");
            
            // Tìm nội dung tương ứng với trang
            $pageContent = null;
            foreach ($originalText as $item) {
                if (isset($item['page_num']) && $item['page_num'] == $page) {
                    $pageContent = $item['text'];
                    Log::info("[CITATION-DETAIL] Đã tìm thấy nội dung cho trang {$page} với page_num={$item['page_num']}");
                    break;
                }
            }
            
            // Thử tìm lại với các trường khác nếu không tìm thấy
            if ($pageContent === null) {
                Log::warning("[CITATION-DETAIL] Không tìm thấy theo page_num, thử tìm theo các trường khác");
                
                foreach ($originalText as $item) {
                    // Thử với trường 'page' nếu có
                    if (isset($item['page']) && $item['page'] == $page) {
                        $pageContent = $item['text'];
                        Log::info("[CITATION-DETAIL] Đã tìm thấy nội dung với trường 'page'");
                        break;
                    }
                }
            }
            
            if ($pageContent === null) {
                Log::warning("[CITATION-DETAIL] Không tìm thấy nội dung cho trang {$page} trong tài liệu {$docId}");
                
                // Nếu chỉ có một trang, sử dụng trang đó
                if (count($originalText) === 1) {
                    $pageContent = $originalText[0]['text'];
                    Log::info("[CITATION-DETAIL] Sử dụng trang duy nhất vì tài liệu chỉ có một trang");
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không tìm thấy nội dung trang ' . $page
                    ]);
                }
            }
            
            Log::info("[CITATION-DETAIL] Đã tìm thấy nội dung trang {$page}, độ dài: " . strlen($pageContent) . " ký tự");
            
            // Đọc metadata để lấy thông tin tài liệu
            $metadataPath = "{$vectorDir}/document_metadata.json";
            $metadata = [];
            if (Storage::exists($metadataPath)) {
                $metadata = json_decode(Storage::get($metadataPath), true);
                Log::info("[CITATION-DETAIL] Đã đọc metadata tài liệu: " . json_encode(array_keys($metadata)));
            } else {
                Log::warning("[CITATION-DETAIL] Không tìm thấy file metadata: {$metadataPath}");
            }
            
            Log::info("[CITATION-DETAIL] Trả về nội dung trích dẫn thành công: document_id={$docId}, page={$page}");
            
            return response()->json([
                'success' => true,
                'content' => nl2br(htmlspecialchars($pageContent)),
                'page' => $page,
                'document' => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'metadata' => $metadata
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("[CITATION-DETAIL] Lỗi khi truy xuất nội dung trích dẫn: {$e->getMessage()}", [
                'doc_id' => $docId,
                'page' => $page,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy nội dung trích dẫn: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy thông tin loại tài liệu
     * 
     * @param int $docId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDocumentType($docId)
    {
        try {
            Log::info("[CITATION-DOCTYPE] Yêu cầu xem loại tài liệu: document_id={$docId}, user_id=" . Auth::id());
            
            // Xác minh quyền truy cập tài liệu - chỉ kiểm tra user_id, bỏ điều kiện is_public
            try {
                $document = Document::where('id', $docId);
                
                // Nếu user đã đăng nhập, thêm điều kiện là chủ sở hữu
                if (Auth::check()) {
                    $document = $document->where('user_id', Auth::id());
                }
                
                $document = $document->first();
                
                if (!$document) {
                    Log::error("[CITATION-DOCTYPE] Không tìm thấy tài liệu: document_id={$docId}");
                    return response()->json([
                        'success' => false,
                        'message' => 'Không tìm thấy tài liệu hoặc bạn không có quyền truy cập',
                        'error_type' => 'document_not_found'
                    ], 404);
                }
            } catch (\Exception $e) {
                Log::error("[CITATION-DOCTYPE] Không thể tìm thấy tài liệu hoặc không có quyền truy cập: {$e->getMessage()}");
                
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy tài liệu hoặc bạn không có quyền truy cập',
                    'error_type' => 'document_not_found'
                ], 404);
            }
            
            // Lấy extension của file
            $fileName = $document->file_name;
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            
            Log::info("[CITATION-DOCTYPE] Tài liệu {$docId} có loại file: {$fileExtension}");
            
            return response()->json([
                'success' => true,
                'document_id' => $docId,
                'document_user_id' => $document->user_id,
                'file_type' => $fileExtension,
                'file_name' => $fileName,
                'title' => $document->title
            ]);
            
        } catch (\Exception $e) {
            Log::error("[CITATION-DOCTYPE] Lỗi khi lấy thông tin tài liệu: {$e->getMessage()}", [
                'doc_id' => $docId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy thông tin tài liệu: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy nội dung trích dẫn từ tài liệu với tham số trang và highlight
     * 
     * @param int $docId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCitationDocument($docId)
    {
        try {
            $page = request()->get('page', 1);
            $highlight = request()->get('highlight');
            
            Log::info("[CITATION-DOC] Yêu cầu xem chi tiết trích dẫn document: document_id={$docId}, page={$page}, highlight={$highlight}, user_id=" . Auth::id());
            
            // Xác minh quyền truy cập tài liệu - chỉ kiểm tra user_id, bỏ điều kiện is_public
            try {
                $document = Document::where('id', $docId);
                
                // Nếu user đã đăng nhập, thêm điều kiện là chủ sở hữu
                if (Auth::check()) {
                    $document = $document->where('user_id', Auth::id());
                }
                
                $document = $document->firstOrFail();
                
                Log::info("[CITATION-DOC] Xác minh quyền truy cập tài liệu thành công: document_id={$docId}, title={$document->title}");
            } catch (\Exception $e) {
                Log::error("[CITATION-DOC] Không thể tìm thấy tài liệu hoặc không có quyền truy cập: {$e->getMessage()}");
                
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy tài liệu hoặc bạn không có quyền truy cập',
                    'error_type' => 'document_not_found'
                ], 404);
            }
            
            // Kiểm tra file type
            $fileExtension = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));
            $isTextFile = in_array($fileExtension, ['txt', 'md']);
            $isPdfFile = $fileExtension === 'pdf';
            
            Log::info("[CITATION-DOC] Loại file: {$fileExtension}, isTextFile: " . ($isTextFile ? 'true' : 'false') . ", isPdfFile: " . ($isPdfFile ? 'true' : 'false'));
            
            // Đường dẫn tới file original_text.json
            $vectorDir = "vector_db/uploads/{$document->user_id}/{$docId}";
            $textPath = "{$vectorDir}/original_text.json";
            
            Log::info("[CITATION-DOC] Tìm kiếm nội dung tại đường dẫn: {$textPath}");
            
            // Xử lý đặc biệt cho file PDF nếu không tìm thấy file text
            if ($isPdfFile && !Storage::exists($textPath)) {
                // Tìm kiếm các tệp khác có thể chứa nội dung
                $pdfTextFile = "{$vectorDir}/pdf_content.json";
                $pdfTextFileTxt = "{$vectorDir}/pdf_content.txt";
                
                if (Storage::exists($pdfTextFile)) {
                    Log::info("[CITATION-DOC] PDF: Đọc nội dung từ file pdf_content.json");
                    $pdfContent = json_decode(Storage::get($pdfTextFile), true);
                    
                    // Tìm nội dung trang
                    $pageContent = null;
                    if (isset($pdfContent['pages']) && isset($pdfContent['pages'][$page])) {
                        $pageContent = $pdfContent['pages'][$page];
                    } elseif (isset($pdfContent[$page])) {
                        $pageContent = $pdfContent[$page];
                    }
                    
                    if ($pageContent) {
                        // Tạo HTML từ nội dung trang
                        $paragraphs = preg_split('/\n\s*\n/', $pageContent);
                        $formattedContent = '';
                        
                        foreach ($paragraphs as $i => $para) {
                            $highlightClass = ($i == (int)$highlight) ? 'highlight-pulse' : '';
                            $formattedContent .= "<p class='{$highlightClass}'>" . nl2br(htmlspecialchars($para)) . "</p>\n";
                        }
                        
                        return response()->json([
                            'success' => true,
                            'content' => $formattedContent,
                            'page' => $page,
                            'highlight' => $highlight,
                            'document' => [
                                'id' => $document->id,
                                'title' => $document->title
                            ]
                        ]);
                    }
                } elseif (Storage::exists($pdfTextFileTxt)) {
                    Log::info("[CITATION-DOC] PDF: Đọc nội dung từ file pdf_content.txt");
                    $content = Storage::get($pdfTextFileTxt);
                    
                    // Trả về toàn bộ nội dung với thông báo
                    return response()->json([
                        'success' => true,
                        'content' => '<div class="alert alert-info mb-3">Không thể xác định chính xác vị trí trích dẫn. Dưới đây là nội dung toàn văn:</div>' . 
                                    '<div class="pdf-text-content">' . nl2br(htmlspecialchars($content)) . '</div>',
                        'page' => $page,
                        'highlight' => $highlight,
                        'document' => [
                            'id' => $document->id,
                            'title' => $document->title
                        ]
                    ]);
                }
                
                // Tạo nội dung giả cho PDF nếu không tìm thấy dữ liệu
                Log::warning("[CITATION-DOC] PDF: Không tìm thấy nội dung văn bản, hiển thị thông báo");
                return response()->json([
                    'success' => true,
                    'content' => '
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Thông báo:</strong> Đang xem trang ' . $page . ' của tài liệu PDF "' . $document->title . '".
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Không thể trích xuất nội dung văn bản chính xác từ tài liệu PDF này. Vui lòng xem trực tiếp trang PDF trên màn hình chính.
                        </div>
                    ',
                    'page' => $page,
                    'highlight' => $highlight,
                    'file_type' => 'pdf',
                    'document' => [
                        'id' => $document->id,
                        'title' => $document->title
                    ]
                ]);
            }
            
            // Xử lý cho các loại tệp khác hoặc PDF có dữ liệu text
            if (!Storage::exists($textPath)) {
                Log::error("[CITATION-DOC] Không tìm thấy file original_text.json: {$textPath}");
                
                // Với file text, thử đọc trực tiếp từ file gốc
                if ($isTextFile && Storage::exists($document->file_path)) {
                    Log::info("[CITATION-DOC] Đọc nội dung trực tiếp từ file text gốc: {$document->file_path}");
                    
                    $content = Storage::get($document->file_path);
                    $paragraphs = preg_split('/\n\s*\n/', $content);
                    
                    // Lấy đoạn theo chỉ số highlight (nếu có)
                    if ($highlight !== null && isset($paragraphs[(int)$highlight])) {
                        $pageContent = $paragraphs[(int)$highlight];
                        Log::info("[CITATION-DOC] Đã lấy đoạn văn thứ {$highlight} từ file text");
                    } else {
                        // Nếu không có chỉ số highlight hoặc không hợp lệ, trả về toàn bộ nội dung
                        $pageContent = $content;
                        Log::info("[CITATION-DOC] Trả về toàn bộ nội dung text (không có highlight index hợp lệ)");
                    }
                    
                    return response()->json([
                        'success' => true,
                        'content' => nl2br(htmlspecialchars($pageContent)),
                        'page' => $page,
                        'highlight' => $highlight,
                        'file_type' => $fileExtension,
                        'document' => [
                            'id' => $document->id,
                            'title' => $document->title,
                            'metadata' => [
                                'filename' => $document->file_name,
                                'extension' => $fileExtension
                            ]
                        ]
                    ]);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy nội dung tài liệu'
                ]);
            }
            
            try {
                // Đọc file original_text.json
                $originalTextContent = Storage::get($textPath);
                $originalText = json_decode($originalTextContent, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error("[CITATION-DOC] Lỗi khi parse JSON: " . json_last_error_msg());
                    return response()->json([
                        'success' => false,
                        'message' => 'Định dạng dữ liệu không hợp lệ: ' . json_last_error_msg()
                    ]);
                }
                
                Log::info("[CITATION-DOC] Đã đọc file original_text.json, tìm thấy " . count($originalText) . " phân đoạn");
            } catch (\Exception $jsonEx) {
                Log::error("[CITATION-DOC] Lỗi khi đọc hoặc phân tích file JSON: " . $jsonEx->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi khi đọc dữ liệu: ' . $jsonEx->getMessage()
                ]);
            }
            
            // Tìm nội dung tương ứng với trang
            $pageContent = null;
            
            foreach ($originalText as $index => $item) {
                // Tìm trang đúng
                if (isset($item['page_num']) && $item['page_num'] == $page) {
                    $pageContent = $item['text'];
                    Log::info("[CITATION-DOC] Đã tìm thấy nội dung cho trang {$page}");
                    break;
                }
            }
            
            // Thử tìm lại với các trường khác nếu không tìm thấy
            if ($pageContent === null) {
                Log::warning("[CITATION-DOC] Không tìm thấy theo page_num, thử tìm theo các trường khác");
                
                foreach ($originalText as $item) {
                    // Thử với trường 'page' nếu có
                    if (isset($item['page']) && $item['page'] == $page) {
                        $pageContent = $item['text'];
                        Log::info("[CITATION-DOC] Đã tìm thấy nội dung với trường 'page'");
                        break;
                    }
                }
            }
            
            if ($pageContent === null) {
                Log::warning("[CITATION-DOC] Không tìm thấy nội dung cho trang {$page} trong tài liệu {$docId}");
                
                // Nếu chỉ có một trang, sử dụng trang đó
                if (count($originalText) === 1) {
                    $pageContent = $originalText[0]['text'];
                    Log::info("[CITATION-DOC] Sử dụng trang duy nhất vì tài liệu chỉ có một trang");
                } else {
                    // Đối với PDF, trả về thông báo thay vì lỗi
                    if ($isPdfFile) {
                        return response()->json([
                            'success' => true,
                            'content' => '
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Thông báo:</strong> Không tìm thấy nội dung chính xác cho trang ' . $page . ' của tài liệu PDF.
                                </div>
                            ',
                            'page' => $page,
                            'highlight' => $highlight,
                            'file_type' => 'pdf',
                            'document' => [
                                'id' => $document->id,
                                'title' => $document->title
                            ]
                        ]);
                    }
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Không tìm thấy nội dung trang ' . $page
                    ]);
                }
            }
            
            Log::info("[CITATION-DOC] Đã tìm thấy nội dung trang {$page}, độ dài: " . strlen($pageContent) . " ký tự");
            
            // Đọc metadata để lấy thông tin tài liệu
            $metadataPath = "{$vectorDir}/document_metadata.json";
            $metadata = [];
            if (Storage::exists($metadataPath)) {
                $metadata = json_decode(Storage::get($metadataPath), true);
                Log::info("[CITATION-DOC] Đã đọc metadata tài liệu: " . json_encode(array_keys($metadata)));
            } else {
                Log::warning("[CITATION-DOC] Không tìm thấy file metadata: {$metadataPath}");
            }
            
            Log::info("[CITATION-DOC] Trả về nội dung trích dẫn thành công: document_id={$docId}, page={$page}");
            
            // Định dạng nội dung trang - luôn trả về toàn bộ văn bản của trang
            $formattedContent = '';
            
            // Tách thành các đoạn để dễ đọc
            $paragraphs = preg_split('/\n\s*\n/', $pageContent);
            foreach ($paragraphs as $para) {
                if (trim($para) !== '') {
                    $formattedContent .= "<p>" . nl2br(htmlspecialchars($para)) . "</p>\n";
                }
            }
            
            return response()->json([
                'success' => true,
                'content' => $formattedContent,
                'page' => $page,
                'highlight' => $highlight,
                'document' => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'metadata' => $metadata
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("[CITATION-DOC] Lỗi khi truy xuất nội dung trích dẫn: {$e->getMessage()}", [
                'doc_id' => $docId,
                'page' => request()->get('page', 1),
                'highlight' => request()->get('highlight'),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy nội dung trích dẫn: ' . $e->getMessage(),
                'error_details' => [
                    'type' => get_class($e),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }
} 