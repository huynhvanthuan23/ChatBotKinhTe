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
    public function index(Request $request)
    {
        // Nếu có tham số clear=1, xóa session
        if ($request->has('clear') && $request->input('clear') == 1) {
            $request->session()->forget('selected_document_ids');
            Log::info('Cleared document selection from session', [
                'session_id' => $request->session()->getId()
            ]);
            return redirect()->route('chat');
        }
        
        // Kiểm tra xem có session selected_document_ids không
        $selectedDocumentIds = $request->session()->get('selected_document_ids', []);
        
        if (!empty($selectedDocumentIds)) {
            Log::info('Chat with selected documents', [
                'session_id' => $request->session()->getId(),
                'document_ids' => $selectedDocumentIds,
                'count' => count($selectedDocumentIds)
            ]);
        }
        
        return view('chat.index', compact('selectedDocumentIds'));
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
            $apiUrl = env('CHATBOT_API_URL', 'http://localhost:55050/api/v1/chat/simple-chat');
            
            // Lấy selected_document_ids từ session nếu có
            $selectedDocumentIds = $request->session()->get('selected_document_ids', []);
            
            // Kiểm tra xem có tham số doc_ids từ query string không
            $queryDocIds = $request->query('doc_ids');
            if ($queryDocIds) {
                // Nếu là chuỗi đơn, chuyển thành mảng
                if (!is_array($queryDocIds)) {
                    $queryDocIds = explode(',', $queryDocIds);
                }
                
                // Chuyển đổi từ string sang integer
                $queryDocIds = array_map('intval', $queryDocIds);
                
                // Nếu có document IDs từ query string, ưu tiên dùng chúng thay vì session
                $selectedDocumentIds = $queryDocIds;
                Log::info('Using document IDs from query string', [
                    'doc_ids' => $queryDocIds
                ]);
            }
            
            // Log request information for debugging
            Log::info('Sending message to ChatBot API', [
                'url' => $apiUrl,
                'message' => $request->message,
                'user_id' => Auth::id(),
                'selected_document_ids' => $selectedDocumentIds,
                'query_doc_ids' => $queryDocIds ?? null
            ]);
            
            // Test if API is accessible before sending actual request
            try {
                // Extract base URL without the endpoint part
                $baseUrl = preg_replace('#/api/v1/chat/.*$#', '', $apiUrl);
                $healthUrl = $baseUrl . '/api/v1/chat/health';
                
                Log::info('Checking health at: ' . $healthUrl);
                
                $healthClient = new Client(['timeout' => 3]);
                $healthResponse = $healthClient->get($healthUrl);
                Log::info('Health check response: ' . $healthResponse->getStatusCode());
            } catch (\Exception $e) {
                Log::error('Health check failed: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể kết nối với chatbot. Vui lòng kiểm tra xem máy chủ chatbot đã khởi động chưa.'
                ], 503);
            }
            
            $client = new Client([
                'timeout' => 90,  // Increased timeout for longer model processing
                'connect_timeout' => 10,
            ]);
            
            // Prepare request data
            $requestData = [
                'message' => $request->message,
                'user_id' => Auth::id() ?: null
            ];
            
            // Thêm document_ids vào request nếu có, sử dụng nhiều tham số khác nhau cho các model
            if (!empty($selectedDocumentIds)) {
                // Tham số chung
                $requestData['document_ids'] = $selectedDocumentIds;
                
                // Tham số cho Google PaLM/Gemini
                $requestData['documentIds'] = $selectedDocumentIds;
                
                // Tham số cho OpenAI
                $requestData['context_document_ids'] = $selectedDocumentIds;
                
                // Tham số cũ
                $requestData['support_doc_ids'] = $selectedDocumentIds;
                
                Log::info('Using selected documents for chat', [
                    'document_ids' => $selectedDocumentIds,
                    'count' => count($selectedDocumentIds)
                ]);
            }
            
            Log::info('Request data: ' . json_encode($requestData));
            
            // Send request to the FastAPI backend
            $response = $client->post($apiUrl, [
                'json' => $requestData,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);
            
            $responseBody = $response->getBody()->getContents();
            Log::info('Raw API Response: ' . $responseBody);
            $response->getBody()->rewind();
            $result = json_decode($responseBody, true);
            Log::info('Decoded JSON: ' . json_encode($result));
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON decode error: ' . json_last_error_msg() . ', Raw response: ' . substr($responseBody, 0, 500));
                return response()->json([
                    'success' => false,
                    'message' => 'Có lỗi khi phân tích dữ liệu từ server. Vui lòng thử lại sau.'
                ], 500);
            }
            
            // Find the answer field in the response, whatever it's called
            $answer = null;
            if (isset($result['response'])) {
                $answer = $result['response'];
            } elseif (isset($result['answer'])) {
                $answer = $result['answer'];
            } elseif (isset($result['result'])) {
                $answer = $result['result'];
            } elseif (isset($result['message'])) {
                $answer = $result['message'];
            } elseif (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                // Google API response format
                $answer = $result['candidates'][0]['content']['parts'][0]['text'];
            } elseif (isset($result['error'])) {
                // Return error as answer for debugging
                $answer = 'API error: ' . $result['error'];
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
            // Use health endpoint for testing connection
            $apiUrl = env('CHATBOT_API_URL', 'http://localhost:55050/api/v1/chat/simple-chat');
            // Extract base URL without the endpoint part
            $baseUrl = preg_replace('#/api/v1/chat/.*$#', '', $apiUrl); 
            $healthUrl = $baseUrl . '/health';
            $apiHealthUrl = $baseUrl . '/api/v1/chat/health';
            
            // Log the URLs we're trying to connect to for debugging
            Log::info('Testing connection to API URLs:', [
                'baseUrl' => $baseUrl,
                'healthUrl' => $healthUrl,
                'apiHealthUrl' => $apiHealthUrl
            ]);
            
            $client = new Client(['timeout' => 5]);
            $results = [];
            $success = false;
            
            // Try the root health endpoint
            try {
                $response = $client->get($healthUrl);
                $result = json_decode($response->getBody()->getContents(), true);
                Log::info('Root health endpoint response: ' . json_encode($result));
                $results['root_health'] = $result;
                $success = true;
            } catch (\Exception $e) {
                Log::warning('Root health endpoint error: ' . $e->getMessage());
                $results['root_health_error'] = $e->getMessage();
            }
            
            // Try the API health endpoint
            try {
                $response = $client->get($apiHealthUrl);
            $result = json_decode($response->getBody()->getContents(), true);
                Log::info('API health endpoint response: ' . json_encode($result));
                $results['api_health'] = $result;
                $success = true;
            } catch (\Exception $e) {
                Log::warning('API health endpoint error: ' . $e->getMessage());
                $results['api_health_error'] = $e->getMessage();
            }
            
            if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Kết nối thành công với Chatbot API.',
                    'response' => $results
            ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể kết nối với bất kỳ endpoint nào của Chatbot API.',
                    'attempts' => $results
                ], 500);
            }
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
            // Sử dụng địa chỉ hardcoded để đảm bảo kết nối
            $apiUrl = 'http://localhost:55050/api/v1/chat/chat-direct';
            
            // Ghi log thông tin đầy đủ
            Log::info("Calling API at: " . $apiUrl);
            Log::info("Message: " . $message);
            
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

            // Ghi log phản hồi chi tiết
            $responseBody = $response->getBody()->getContents();
            Log::info("API Response raw: " . $responseBody);
            $response->getBody()->rewind();
            
            $result = json_decode($responseBody, true);
            
            Log::info('API Response decoded: ' . json_encode($result));
            
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
            Log::error('Chatbot API Error: ' . $e->getMessage());
            return 'Xin lỗi, có lỗi trong quá trình kết nối với chatbot: ' . $e->getMessage();
        } catch (\Exception $e) {
            // Log lỗi
            Log::error('Chatbot API Error: ' . $e->getMessage());
            return 'Xin lỗi, có lỗi không xác định: ' . $e->getMessage();
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
        // Đường dẫn API đã cấu hình trong file .env
        $apiUrl = env('CHATBOT_API_URL', 'http://localhost:55050/api/v1/chat/chat-direct');
        
        $client = new Client([
            'timeout' => 30,
            'connect_timeout' => 5,
        ]);
        
        try {
            Log::info('Calling chatbot API at: ' . $apiUrl . ' with message: ' . $message);
            
            $response = $client->post($apiUrl, [
                'body' => json_encode(['message' => $message]),
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);
            
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);
            
            Log::info('Raw API Response: ' . $responseBody);
            $response->getBody()->rewind();
            $result = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Decoded JSON: ' . json_encode($result));
            
            if (isset($result['response'])) {
                return $result['response'];
            } elseif (isset($result['answer'])) {
                return $result['answer'];
            } elseif (isset($result['result'])) {
                return $result['result'];
            } elseif (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                return $result['candidates'][0]['content']['parts'][0]['text'];
            } else {
                Log::warning('Unexpected API response structure: ' . json_encode($result));
                return 'Xin lỗi, tôi không thể xử lý yêu cầu của bạn lúc này.';
            }
        } catch (\Exception $e) {
            Log::error('Chatbot API Error: ' . $e->getMessage());
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
        $chatUrl = str_replace('/ping', '/chat-direct', env('CHATBOT_API_URL', 'http://localhost:55050/api/v1/chat/chat-direct'));
        
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
