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

            // Use the full URL directly
            $apiUrl = env('CHATBOT_API_URL', 'http://localhost:8080/api/v1/chat/chat-direct');
            
            // Log request information for debugging
            Log::info('Sending message to ChatBot API', [
                'url' => $apiUrl,
                'message' => $request->message,
                'user_id' => Auth::id()
            ]);
            
            $client = new Client([
                'timeout' => 90,  // Increased timeout for longer model processing
                'connect_timeout' => 10,
            ]);
            
            // Send request to the FastAPI backend
            $response = $client->post($apiUrl, [
                'json' => [
                    'message' => $request->message,
                    'user_id' => Auth::id() ?: null
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);
            
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON decode error: ' . json_last_error_msg() . ', Raw response: ' . substr($responseBody, 0, 500));
                return response()->json([
                    'success' => false,
                    'message' => 'Có lỗi khi phân tích dữ liệu từ server. Vui lòng thử lại sau.'
                ], 500);
            }
            
            // Find the answer field in the response, whatever it's called
            $answer = null;
            if (isset($result['answer'])) {
                $answer = $result['answer'];
            } elseif (isset($result['response'])) {
                $answer = $result['response'];
            } elseif (isset($result['result'])) {
                $answer = $result['result'];
            } elseif (isset($result['message'])) {
                $answer = $result['message'];
            }
            
            if (!empty($answer)) {
                return response()->json([
                    'success' => true,
                    'message' => $answer
                ]);
            }
            
            // Default response if no valid content found
            Log::warning('Empty or unexpected API response structure', ['response' => $result]);
            return response()->json([
                'success' => false,
                'message' => 'Tôi không thể trả lời câu hỏi này lúc này. Vui lòng thử lại với câu hỏi khác.'
            ]);
        } catch (RequestException $e) {
            // Handle Guzzle request exceptions
            $errorMessage = 'Xin lỗi, không thể kết nối với chatbot.';
            $statusCode = 500;
            
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $errorBody = $e->getResponse()->getBody()->getContents();
                
                Log::error('Chatbot API error', [
                    'status' => $statusCode,
                    'error' => $e->getMessage(),
                    'response' => $errorBody
                ]);
                
                // Try to extract error message from response
                $errorData = json_decode($errorBody, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($errorData['detail'])) {
                    $errorMessage .= ' Chi tiết: ' . $errorData['detail'];
                }
            } else {
                Log::error('Chatbot connection error: ' . $e->getMessage());
            }
            
            return response()->json([
                'success' => false,
                'message' => $errorMessage
            ], $statusCode);
        } catch (\Exception $e) {
            // Handle general exceptions
            Log::error('Chat Processing Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Xin lỗi, có lỗi xảy ra: ' . $e->getMessage()
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
            // Use direct health endpoint
            $apiUrl = 'http://localhost:8080/health';
            
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
    private function callChatAPI($message)
    {
        try {
            // Use the full URL from env
            $apiUrl = env('CHATBOT_API_URL', 'http://localhost:8080/api/v1/chat/chat-direct');
            
            $client = new Client([
                'timeout' => 90,
                'connect_timeout' => 5,
                'http_errors' => false
            ]);

            $response = $client->post($apiUrl, [
                'json' => [
                    'message' => $message,
                    'user_id' => Auth::id() ?: null
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
