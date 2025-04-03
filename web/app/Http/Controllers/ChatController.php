<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ChatController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Middleware auth sẽ được áp dụng trong định nghĩa route
        // KHÔNG gọi $this->middleware() ở đây
    }

    /**
     * Show the chat interface.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('chat.index');
    }

    /**
     * Process a chat message and return the response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'conversation_id' => 'nullable|string',
        ]);

        try {
            // Sử dụng API endpoint từ .env
            $apiUrl = env('CHATBOT_API_URL', 'http://localhost:8080/api/v1/chat/ping');
            
            $client = new Client([
                'timeout' => 30,
                'connect_timeout' => 5,
            ]);
            
            // Kiểm tra kết nối trước
            $pingResponse = $client->get($apiUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);
            
            $pingResult = json_decode($pingResponse->getBody()->getContents(), true);
            Log::info('API Ping: ' . json_encode($pingResult));
            
            // Hiển thị thông báo kết nối thành công thay vì xử lý chat thực sự
            return response()->json([
                'message' => 'Kết nối thành công với Chatbot API! Phản hồi: ' . ($pingResult['message'] ?? 'API hoạt động')
            ]);
        } catch (\Exception $e) {
            Log::error('Chat Processing Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Xin lỗi, có lỗi xảy ra trong quá trình xử lý tin nhắn của bạn: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test connection to chatbot API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testConnection()
    {
        try {
            $client = new Client(['timeout' => 5]);
            $response = $client->get(env('CHATBOT_API_URL', 'http://localhost:8080/api/v1/chat/ping'), [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            
            return response()->json([
                'success' => true,
                'message' => 'Kết nối thành công với Chatbot API.',
                'response' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Chatbot API Test Connection Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Không thể kết nối với Chatbot API.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Call the chatbot API
     * 
     * @param string $message
     * @return string
     */
    private function callChatbotAPI($message)
    {
        // Sử dụng endpoint đúng của FastAPI
        $apiUrl = 'http://localhost:8000/api/v1/chat/chat-direct';
        
        $client = new Client([
            'timeout' => 30,
            'connect_timeout' => 5,
        ]);
        
        try {
            // Ghi nhật ký yêu cầu để gỡ lỗi
            Log::info('Sending chat request to API: ' . $apiUrl . ' with message: ' . $message);
            
            // Gửi yêu cầu POST đến API
            $response = $client->post($apiUrl, [
                'json' => [
                    'message' => $message
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            
            Log::info('API Response: ' . json_encode($result));
            
            // Kiểm tra cấu trúc phản hồi từ API
            if (isset($result['response'])) {
                return $result['response'];
            } elseif (isset($result['answer'])) {
                return $result['answer'];
            } else {
                Log::warning('Unexpected API response structure: ' . json_encode($result));
                return 'Xin lỗi, tôi không thể xử lý yêu cầu của bạn lúc này.';
            }
        } catch (RequestException $e) {
            // Log lỗi chi tiết
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                Log::error("Chatbot API Error: Status Code: {$statusCode}, Message: {$e->getMessage()}, Response: {$responseBody}");
                
                // Kiểm tra lỗi cụ thể
                if ($statusCode == 422) {
                    Log::error("Validation error in request format. Trying with different format.");
                    return $this->callChatbotAPIAlternative($message);
                }
            } else {
                Log::error('Chatbot API Error: ' . $e->getMessage());
            }
            return 'Xin lỗi, có lỗi trong quá trình kết nối với chatbot.';
        } catch (\Exception $e) {
            // Log lỗi
            Log::error('Chatbot API Error: ' . $e->getMessage());
            return 'Xin lỗi, có lỗi không xác định trong quá trình xử lý yêu cầu của bạn.';
        }
    }

    /**
     * Alternative method to call the chatbot API with a different request format
     * 
     * @param string $message
     * @return string
     */
    private function callChatbotAPIAlternative($message)
    {
        $apiUrl = 'http://localhost:8000/api/v1/chat/chat-direct';
        
        $client = new Client([
            'timeout' => 30,
            'connect_timeout' => 5,
        ]);
        
        try {
            Log::info('Trying alternative API request format to: ' . $apiUrl);
            
            // Sử dụng body trực tiếp thay vì json
            $response = $client->post($apiUrl, [
                'body' => json_encode(['message' => $message]),
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Alternative API Response: ' . json_encode($result));
            
            if (isset($result['response'])) {
                return $result['response'];
            } elseif (isset($result['answer'])) {
                return $result['answer'];
            } else {
                Log::warning('Unexpected API response structure: ' . json_encode($result));
                return 'Xin lỗi, tôi không thể xử lý yêu cầu của bạn lúc này.';
            }
        } catch (\Exception $e) {
            Log::error('Alternative Chatbot API Error: ' . $e->getMessage());
            return 'Xin lỗi, có lỗi trong quá trình kết nối với chatbot.';
        }
    }

    /**
     * Process chat with actual chatbot
     * 
     * @param  string  $message
     * @return string
     */
    private function processWithChatbot($message)
    {
        // URL cho endpoint chat thực sự - sẽ được dùng sau khi xác nhận kết nối ổn định
        $chatUrl = str_replace('/ping', '/chat-model', env('CHATBOT_API_URL'));
        
        $client = new Client([
            'timeout' => 60, // Thời gian timeout lâu hơn cho xử lý chat
            'connect_timeout' => 10,
        ]);
        
        try {
            Log::info('Sending chat request to: ' . $chatUrl . ' with message: ' . $message);
            
            $response = $client->post($chatUrl, [
                'body' => json_encode([
                    'message' => $message,
                    'user_id' => Auth::id() ?? null
                ]),
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Chat API Response: ' . json_encode($result));
            
            if (isset($result['response'])) {
                return $result['response'];
            } elseif (isset($result['answer'])) {
                return $result['answer'];
            } else {
                Log::warning('Unexpected chat response structure: ' . json_encode($result));
                return 'Xin lỗi, tôi không thể xử lý yêu cầu của bạn lúc này.';
            }
        } catch (\Exception $e) {
            Log::error('Chat Processing Error: ' . $e->getMessage());
            return 'Xin lỗi, có lỗi xảy ra trong quá trình xử lý tin nhắn của bạn.';
        }
    }
}
