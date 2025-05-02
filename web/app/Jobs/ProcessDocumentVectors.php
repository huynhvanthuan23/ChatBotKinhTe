<?php

namespace App\Jobs;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessDocumentVectors implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $document;
    protected $maxTries = 3; // Số lần thử lại tối đa
    protected $timeout = 600; // Thời gian timeout (10 phút)

    /**
     * Create a new job instance.
     *
     * @param  Document  $document
     * @return void
     */
    public function __construct(Document $document)
    {
        $this->document = $document;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Log::info("Bắt đầu xử lý vector cho tài liệu #{$this->document->id}");

            // Cập nhật trạng thái
            $this->document->vector_status = 'processing';
            $this->document->save();

            // URL của API xử lý tài liệu
            $apiUrl = config('services.chatbot.process_url', 'http://localhost:55050/api/v1/documents/process');
            
            Log::info("Gọi API xử lý tài liệu: {$apiUrl}");

            // Đường dẫn tuyệt đối của tệp
            $absolutePath = Storage::disk('public')->path($this->document->file_path);
            Log::info("Đường dẫn tuyệt đối của tệp: {$absolutePath}");

            // Chuẩn bị dữ liệu để gửi
            $data = [
                'document_id' => $this->document->id,
                'file_path' => $this->document->file_path,
                'absolute_path' => $absolutePath, // Thêm đường dẫn tuyệt đối
                'title' => $this->document->title,
                'description' => $this->document->description,
                'file_type' => $this->document->file_type,
                'chunk_size' => 100, // Thiết lập kích thước chunk
                'chunk_overlap' => 50 // Thiết lập kích thước overlap
            ];

            // Gọi API xử lý tài liệu
            $response = Http::timeout(300)->post($apiUrl, $data);

            if ($response->successful()) {
                $result = $response->json();
                
                Log::info("Kết quả xử lý tài liệu #{$this->document->id}:", $result);
                
                if (isset($result['success']) && $result['success']) {
                    // Cập nhật trạng thái thành công
                    $this->document->vector_status = 'completed';
                    $this->document->processed_at = now();
                    
                    // Lưu đường dẫn vector nếu có
                    if (isset($result['vector_path'])) {
                        $this->document->vector_path = $result['vector_path'];
                    } else {
                        // Nếu không có vector_path, sử dụng định dạng mặc định
                        $this->document->vector_path = "doc_{$this->document->id}";
                    }
                    
                    // Thêm trường để lưu thông tin vector database
                    if (isset($result['vector_data'])) {
                        $this->document->vector_data = $result['vector_data'];
                    }
                    
                    $this->document->save();
                    
                    Log::info("Đã xử lý thành công vector cho tài liệu #{$this->document->id}");
                    
                    // Gọi thêm API integrate để tích hợp vectors vào cơ sở dữ liệu chính
                    $this->integrateVectors();
                } else {
                    // Xử lý thất bại
                    $this->document->vector_status = 'failed';
                    $this->document->save();
                    
                    Log::error("Xử lý vector thất bại cho tài liệu #{$this->document->id}: " . 
                              ($result['message'] ?? 'Không có thông báo lỗi'));
                }
            } else {
                // Xử lý lỗi từ API
                $this->document->vector_status = 'failed';
                $this->document->save();
                
                Log::error("API xử lý tài liệu trả về lỗi cho tài liệu #{$this->document->id}: " . 
                          $response->status() . ' - ' . $response->body());
            }
        } catch (\Exception $e) {
            // Xử lý lỗi ngoại lệ
            $this->document->vector_status = 'failed';
            $this->document->save();
            
            Log::error("Lỗi khi xử lý vector cho tài liệu #{$this->document->id}: " . $e->getMessage());
            
            throw $e; // Rethrow để Laravel Queue có thể xử lý retry
        }
    }
    
    /**
     * Gọi API để tích hợp vectors vào cơ sở dữ liệu chính
     */
    protected function integrateVectors()
    {
        try {
            Log::info("Bắt đầu tích hợp vector cho tài liệu #{$this->document->id}");
            
            // URL của API tích hợp tài liệu
            $apiUrl = config('services.chatbot.integrate_url', 'http://localhost:55050/api/v1/documents/integrate');
            
            // Gọi API tích hợp tài liệu
            $response = Http::timeout(180)->post($apiUrl . '?document_id=' . $this->document->id);
            
            if ($response->successful()) {
                $result = $response->json();
                
                Log::info("Kết quả tích hợp vector tài liệu #{$this->document->id}:", $result);
                
                if (isset($result['success']) && $result['success']) {
                    Log::info("Đã tích hợp thành công vector cho tài liệu #{$this->document->id}");
                    // Cập nhật trạng thái tích hợp
                    $this->document->is_integrated = true;
                    $this->document->save();
                } else {
                    Log::warning("Tích hợp vector không thành công cho tài liệu #{$this->document->id}: " . 
                                ($result['message'] ?? 'Không có thông báo lỗi'));
                }
            } else {
                Log::warning("API tích hợp vector trả về lỗi cho tài liệu #{$this->document->id}: " . 
                            $response->status() . ' - ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Lỗi khi tích hợp vector cho tài liệu #{$this->document->id}: " . $e->getMessage());
            // Không throw exception ở đây để không làm fail toàn bộ job
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        // Cập nhật trạng thái tài liệu khi job thất bại
        $this->document->vector_status = 'failed';
        $this->document->save();
        
        Log::error("Job xử lý vector thất bại cho tài liệu #{$this->document->id}: " . $exception->getMessage());
    }
}