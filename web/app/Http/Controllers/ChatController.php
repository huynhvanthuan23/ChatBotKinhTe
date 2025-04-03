<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Middleware auth sẽ được áp dụng trong route definition
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
        ]);

        // Gọi API FastAPI của bạn ở đây
        $response = $this->callChatbotAPI($request->message);
        
        return response()->json([
            'message' => $response
        ]);
    }

    /**
     * Call the chatbot API
     * 
     * @param string $message
     * @return string
     */
    private function callChatbotAPI($message)
    {
        // Thay thế URL bằng URL thực của chatbot API
        $apiUrl = env('CHATBOT_API_URL', 'http://localhost:8000/api/chat');
        
        $client = new \GuzzleHttp\Client();
        
        try {
            $response = $client->post($apiUrl, [
                'json' => [
                    'message' => $message,
                    'user_id' => Auth::id()
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            return $result['response'] ?? 'Xin lỗi, tôi không thể xử lý yêu cầu của bạn lúc này.';
        } catch (\Exception $e) {
            // Log lỗi
            \Log::error('Chatbot API Error: ' . $e->getMessage());
            return 'Xin lỗi, có lỗi trong quá trình kết nối với chatbot.';
        }
    }
}
