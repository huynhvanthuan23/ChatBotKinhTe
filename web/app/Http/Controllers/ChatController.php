<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Validation\ValidationException;

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
        try {
            $validated = $request->validate([
                'message' => 'required|string|max:1000',
            ]);

            // Sử dụng API endpoint đúng từ .env với port 8080
            $baseUrl = rtrim(env('CHATBOT_API_URL', 'http://localhost:8080/api/v1/chat'), '/');
            $apiUrl = $baseUrl . '/chat-direct';
            
            // Log thông tin gửi đi
            Log::info('Sending message to API URL: ' . $apiUrl . ' with message: ' . $request->message);
            
            $client = new Client([
                'timeout' => 60,  // Tăng timeout lên để chờ model xử lý
                'connect_timeout' => 10,
            ]);
            
            // Gọi API chat-direct
            $response = $client->post($apiUrl, [
                'json' => [
                    'message' => $request->message,
                    'user_id' => Auth::id() ? (string)Auth::id() : null
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);
            
            $responseBody = $response->getBody()->getContents();
            Log::info('Raw API Response: ' . $responseBody);
            
            $result = json_decode($responseBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON decode error: ' . json_last_error_msg() . ', Raw response: ' . substr($responseBody, 0, 500));
                return response()->json([
                    'message' => 'Lỗi phân tích dữ liệu từ server. Vui lòng thử lại sau.'
                ], 500);
            }
            
            Log::info('Parsed Chat API Response: ' . json_encode($result));
            
            // Trả về kết quả từ API - kiểm tra các trường có thể có
            if (isset($result['response']) && !empty(trim($result['response']))) {
                return response()->json([
                    'message' => $result['response']
                ]);
            } elseif (isset($result['answer']) && !empty(trim($result['answer']))) {
                return response()->json([
                    'message' => $result['answer']
                ]);
            } elseif (isset($result['result']) && !empty(trim($result['result']))) {
                return response()->json([
                    'message' => $result['result']
                ]);
            } else {
                // Phản hồi mặc định nếu không có nội dung hợp lệ
                Log::warning('Empty or unexpected API response structure: ' . $responseBody);
                return response()->json([
                    'message' => 'Tôi không thể trả lời câu hỏi này lúc này. Vui lòng thử lại với cách diễn đạt khác.'
                ]);
            }
        } catch (Exception $e) {
            // Xử lý lỗi
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
    public function testConnection(Request $request)
    {
        try {
            // Make sure to use the correct port 8080 where our API is running
            $baseUrl = rtrim(env('CHATBOT_API_URL', 'http://localhost:8080/api/v1/chat'), '/');
            $apiUrl = $baseUrl . '/ping';
            
            // Log the URL we're trying to connect to for debugging
            Log::info('Testing connection to API URL: ' . $apiUrl);
            
            $client = new Client(['timeout' => 5]);
            $response = $client->get($apiUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            Log::info('API Test Response: ' . json_encode($result));
            
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
        // Use the environment variable for API URL
        $baseUrl = rtrim(env('CHATBOT_API_URL', 'http://localhost:8080/api/v1/chat/'), '/');
        $apiUrl = $baseUrl . '/chat-direct';
        
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
        // Use the environment variable for API URL
        $baseUrl = rtrim(env('CHATBOT_API_URL', 'http://localhost:8080/api/v1/chat/'), '/');
        $apiUrl = $baseUrl . '/chat-direct';
        
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
