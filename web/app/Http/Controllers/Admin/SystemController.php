<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SystemController extends Controller
{
    /**
     * Display the system status dashboard
     * 
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $chatbotStatus = $this->getChatbotStatus();
        return view('admin.system.status', compact('chatbotStatus'));
    }
    
    /**
     * Check the status of the chatbot API
     * 
     * @return array
     */
    private function getChatbotStatus()
    {
        $status = [
            'online' => false,
            'api_version' => 'N/A',
            'resources' => [],
            'message' => 'Không thể kết nối đến Chatbot API',
            'last_checked' => now()->format('Y-m-d H:i:s')
        ];
        
        try {
            $client = new Client(['timeout' => 5]);
            $response = $client->get(env('CHATBOT_API_URL', 'http://localhost:8000/api/v1/chat/health'));
            
            if ($response->getStatusCode() == 200) {
                $result = json_decode($response->getBody()->getContents(), true);
                
                $status['online'] = true;
                $status['api_version'] = $result['api_version'] ?? 'Unknown';
                $status['resources'] = $result['resources'] ?? [];
                $status['message'] = 'Chatbot API hoạt động bình thường';
            }
        } catch (RequestException $e) {
            $status['message'] = 'Lỗi kết nối: ' . $e->getMessage();
        } catch (\Exception $e) {
            $status['message'] = 'Lỗi không xác định: ' . $e->getMessage();
        }
        
        return $status;
    }
    
    /**
     * Test sending a message to the chatbot API
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testChatbot(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000'
        ]);
        
        try {
            $apiUrl = env('CHATBOT_API_URL', 'http://localhost:8000/api/v1/chat/chat');
            $client = new Client(['timeout' => 30]);
            
            $response = $client->post($apiUrl, [
                'json' => [
                    'message' => $request->message
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            
            return response()->json([
                'success' => true,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi gửi tin nhắn đến Chatbot: ' . $e->getMessage()
            ], 500);
        }
    }
} 