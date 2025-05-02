<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ChatbotService
{
    protected $client;
    protected $apiUrl;
    
    public function __construct()
    {
        $this->apiUrl = config('services.chatbot.url', 'http://localhost:55050/api/v1/chat/chat-direct');
        
        // Tạo client HTTP với cấu hình mặc định
        $this->client = new Client([
            'timeout' => 90,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
        
        Log::info("ChatbotService initialized with URL: {$this->apiUrl}");
    }
    
    public function sendMessage($message, $userId = null)
    {
        try {
            Log::info("Sending message to chatbot: {$message}");
            
            // Chuẩn bị cả hai format để tăng khả năng tương thích
            $payload = [
                'message' => $message,
                'query' => $message,   // Một số API có thể sử dụng query thay vì message
                'user_id' => $userId,
            ];
            
            $response = $this->client->post($this->apiUrl, [
                'json' => $payload,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);
            
            $statusCode = $response->getStatusCode();
            Log::info("Chatbot response status: {$statusCode}");
            
            if ($statusCode >= 200 && $statusCode < 300) {
                $responseBody = $response->getBody()->getContents();
                $result = json_decode($responseBody, true);
                
                Log::info("Chatbot response body: " . substr(json_encode($result), 0, 500) . "...");
                
                // Tìm answer trong response với ưu tiên
                if (isset($result['response'])) {
                    return ['success' => true, 'message' => $result['response']];
                } elseif (isset($result['answer'])) {
                    return ['success' => true, 'message' => $result['answer']];
                } elseif (isset($result['result'])) {
                    return ['success' => true, 'message' => $result['result']];
                } elseif (isset($result['message'])) {
                    return ['success' => true, 'message' => $result['message']];
                }
                
                // Nếu không tìm thấy trường hợp cụ thể
                Log::warning("Unexpected API response structure", ['result' => $result]);
                return [
                    'success' => false,
                    'message' => 'Không thể xử lý phản hồi từ chatbot.'
                ];
            }
            
            // Xử lý lỗi HTTP
            return [
                'success' => false,
                'message' => "Lỗi API: HTTP {$statusCode}"
            ];
            
        } catch (\Exception $e) {
            Log::error("Chatbot exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Lỗi kết nối: " . $e->getMessage()
            ];
        }
    }
}

