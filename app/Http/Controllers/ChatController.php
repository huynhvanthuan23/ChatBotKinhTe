<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;

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
            // Sử dụng API endpoint từ .env để kiểm tra kết nối
            $pingUrl = env('CHATBOT_API_URL', 'http://localhost:8080/api/v1/chat/ping');
            
            $client = new Client([
                'timeout' => 30,
                'connect_timeout' => 5,
            ]);
            
            try {
                // Thử kiểm tra kết nối trước bằng ping
                $pingResponse = $client->get($pingUrl, [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ]
                ]);
                
                // Nếu ping thành công, xử lý chat thực sự
                $chatUrl = str_replace('/ping', '/chat-model', $pingUrl);
                
                Log::info('Sending chat request to: ' . $chatUrl . ' with message: ' . $request->message);
                
                $response = $client->post($chatUrl, [
                    'body' => json_encode([
                        'message' => $request->message,
                        'user_id' => Auth::id() ?? null
                    ]),
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ]
                ]);
                
                $result = json_decode($response->getBody()->getContents(), true);
                
                Log::info('Chat API Response: ' . json_encode($result));
                
                $responseText = '';
                if (isset($result['response'])) {
                    $responseText = $result['response'];
                } elseif (isset($result['answer'])) {
                    $responseText = $result['answer'];
                } else {
                    Log::warning('Unexpected chat response structure: ' . json_encode($result));
                    $responseText = 'Xin lỗi, tôi không thể xử lý yêu cầu của bạn lúc này.';
                }
                
                return response()->json([
                    'message' => $responseText
                ]);
            } catch (RequestException $e) {
                if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 405) {
                    // Method Not Allowed - có thể do sử dụng phương thức không đúng
                    Log::error('Method Not Allowed: ' . $e->getMessage());
                    return response()->json([
                        'message' => 'Lỗi kết nối đến Chatbot API: Phương thức yêu cầu không được chấp nhận.'
                    ], 500);
                }
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Chat Processing Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Không thể kết nối với chatbot API. Vui lòng kiểm tra lại kết nối hoặc liên hệ quản trị viên.'
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
            $response = $client->get('http://localhost:8080/api/v1/chat/ping', [
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

    public function sendChat(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept' => 'application/json',
            ])->post('http://localhost:8080/chat/send', [
                'query' => $request->input('query'),
                'user_id' => $request->input('user_id', 1)
            ]);

            if ($response->successful()) {
                return $response->json();
            } else {
                return response()->json([
                    'success' => false,
                    'answer' => 'Error from chat service',
                    'error' => $response->body()
                ], $response->status());
            }
        } catch (\Exception $e) {
            \Log::error('Chat error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'answer' => 'Error connecting to chat service',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 