<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    protected $apiBaseUrl;
    
    public function __construct()
    {
        // URL của Python API
        $this->apiBaseUrl = env('CHATBOT_API_URL', 'http://localhost:5000');
    }
    
    public function chat(Request $request)
    {
        try {
            // Lấy câu hỏi từ request
            $query = $request->input('query');
            
            if (empty($query)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng nhập câu hỏi',
                ], 400);
            }
            
            // Gọi API Python với endpoint mới
            $response = Http::timeout(30)->get("{$this->apiBaseUrl}/api/v1/chat/chat", [
                'query' => $query,
            ]);
            
            // Kiểm tra response
            if ($response->successful()) {
                $data = $response->json();
                
                return response()->json([
                    'success' => true,
                    'data' => $data,
                ]);
            } else {
                // Log lỗi
                Log::error('Error from Python API: ' . $response->body());
                
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể kết nối đến chatbot API',
                    'error' => $response->body(),
                ], 500);
            }
        } catch (\Exception $e) {
            // Log lỗi
            Log::error('Exception in ChatbotController: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi khi xử lý yêu cầu',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function healthCheck()
    {
        try {
            // Gọi API Python để kiểm tra health với endpoint mới
            $response = Http::timeout(5)->get("{$this->apiBaseUrl}/api/v1/chat/health");
            
            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Chatbot API đang hoạt động bình thường',
                    'data' => $response->json(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Chatbot API không hoạt động',
                    'error' => $response->body(),
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể kết nối đến chatbot API',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function serviceInfo()
    {
        try {
            // Gọi API để lấy thông tin service
            $response = Http::timeout(5)->get("{$this->apiBaseUrl}/api/v1/chat/service-info");
            
            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể lấy thông tin service',
                    'error' => $response->body(),
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể kết nối đến chatbot API',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
} 