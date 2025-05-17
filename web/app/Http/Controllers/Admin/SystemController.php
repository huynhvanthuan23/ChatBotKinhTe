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
            // Sử dụng URL từ cấu hình
            $healthUrl = env('CHATBOT_API_URL', 'http://localhost:55050') . "/health";
            
            $client = new Client(['timeout' => 5]);
            $response = $client->get($healthUrl);
            
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
            // Sử dụng URL API được cấu hình trong env
            $apiUrl = env('CHATBOT_API_URL', 'http://localhost:55050/api/v1/chat/chat-direct');
            
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

    /**
     * Display the API configuration page
     * 
     * @return \Illuminate\Http\Response
     */
    public function showApiConfig()
    {
        // Đọc cấu hình API hiện tại từ .env
        $apiConfig = [
            'api_type' => env('API_TYPE', 'google'),
            'google_api_key' => env('GOOGLE_API_KEY', ''),
            'google_model' => env('GOOGLE_MODEL', 'gemini-1.5-pro'),
            'openai_api_key' => env('OPENAI_API_KEY', ''),
            'openai_model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        ];

        // Danh sách các models có sẵn
        $availableModels = [
            'google' => [
                'gemini-1.5-pro' => 'Gemini 1.5 Pro (mặc định)',
                'gemini-2.0-flash' => 'Gemini 2.0 Flash (nhanh hơn)',
                'gemini-2.0-pro' => 'Gemini 2.0 Pro (phiên bản mới)'
            ],
            'openai' => [
                'gpt-4o-mini' => 'GPT-4o Mini (mặc định)',
                'gpt-4o' => 'GPT-4o (tốt nhất)',
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo (nhanh)',
                'gpt-4-turbo' => 'GPT-4 Turbo'
            ]
        ];
        
        return view('admin.system.api-config', compact('apiConfig', 'availableModels'));
    }
    
    /**
     * Update the API configuration
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateApiConfig(Request $request)
    {
        $request->validate([
            'api_type' => 'required|in:google,openai',
            'google_api_key' => 'nullable|string',
            'google_model' => 'nullable|string',
            'openai_api_key' => 'nullable|string',
            'openai_model' => 'nullable|string',
        ]);
        
        try {
            // Đọc file .env (sửa đường dẫn)
            $envFile = base_path('.env');
            $envContent = file_get_contents($envFile);
            
            // Cập nhật API_TYPE
            $envContent = $this->updateEnvVariable($envContent, 'API_TYPE', $request->api_type);
            
            // Cập nhật Google API Key
            if ($request->filled('google_api_key')) {
                $envContent = $this->updateEnvVariable($envContent, 'GOOGLE_API_KEY', $request->google_api_key);
            }
            
            // Cập nhật Google Model
            if ($request->filled('google_model')) {
                $envContent = $this->updateEnvVariable($envContent, 'GOOGLE_MODEL', $request->google_model);
            }
            
            // Cập nhật OpenAI API Key
            if ($request->filled('openai_api_key')) {
                $envContent = $this->updateEnvVariable($envContent, 'OPENAI_API_KEY', $request->openai_api_key);
            }
            
            // Cập nhật OpenAI Model
            if ($request->filled('openai_model')) {
                $envContent = $this->updateEnvVariable($envContent, 'OPENAI_MODEL', $request->openai_model);
            }
            
            // Lưu lại file .env
            file_put_contents($envFile, $envContent);
            
            // Tự động gọi API để tải lại cấu hình với các giá trị mới
            $reloadSuccess = true;
            $reloadMessage = '';
            
            try {
                // Truyền dữ liệu cấu hình trực tiếp đến phương thức reloadApiConfig
                $reloadResponse = $this->reloadApiConfig($request);
                $reloadResult = json_decode($reloadResponse->getContent(), true);
                
                $reloadSuccess = $reloadResult['success'] ?? false;
                $reloadMessage = $reloadResult['message'] ?? 'Không có thông báo từ server';
            } catch (\Exception $e) {
                $reloadSuccess = false;
                $reloadMessage = 'Lỗi khi tải lại cấu hình API: ' . $e->getMessage();
            }
            
            $successMessage = 'Cấu hình API đã được cập nhật thành công!';
            if ($reloadSuccess) {
                $successMessage .= ' ' . $reloadMessage;
            } else {
                $successMessage .= ' Tuy nhiên, ' . $reloadMessage;
            }
            
            return redirect()
                ->route('admin.system.api-config')
                ->with('success', $successMessage);
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.system.api-config')
                ->with('error', 'Lỗi khi cập nhật cấu hình API: ' . $e->getMessage());
        }
    }
    
    /**
     * Update a variable value in the env file
     * 
     * @param string $envContent
     * @param string $key
     * @param string $value
     * @return string
     */
    private function updateEnvVariable($envContent, $key, $value)
    {
        // Nếu biến đã tồn tại
        if (preg_match("/^{$key}=.*/m", $envContent)) {
            // Thay thế giá trị hiện tại
            $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $envContent);
        } else {
            // Thêm biến mới vào cuối file
            $envContent .= "\n{$key}={$value}";
        }
        
        return $envContent;
    }
    
    /**
     * Test API connection with the current configuration
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function testApiConnection()
    {
        $apiType = env('API_TYPE', 'google');
        $apiKey = ($apiType == 'google') 
            ? env('GOOGLE_API_KEY', '') 
            : env('OPENAI_API_KEY', '');
        $model = ($apiType == 'google') 
            ? env('GOOGLE_MODEL', 'gemini-1.5-pro') 
            : env('OPENAI_MODEL', 'gpt-4o-mini');
            
        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'API key không được cấu hình.'
            ]);
        }
        
        try {
            // Kiểm tra kết nối API
            $serviceInfoUrl = env('CHATBOT_API_URL', 'http://localhost:55050') . "/api/v1/chat/service-info";
            
            $client = new Client(['timeout' => 10]);
            $response = $client->get($serviceInfoUrl);
            
            if ($response->getStatusCode() == 200) {
                $result = json_decode($response->getBody()->getContents(), true);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Kết nối API thành công!',
                    'data' => $result
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Không thể kết nối đến API.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi kiểm tra kết nối API: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Reload API configuration on the API server
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function reloadApiConfig(Request $request = null)
    {
        try {
            // Chuẩn bị dữ liệu cấu hình để gửi trực tiếp
            $configData = [
                'api_type' => env('API_TYPE', 'google'),
                'google_api_key' => env('GOOGLE_API_KEY', ''),
                'google_model' => env('GOOGLE_MODEL', 'gemini-1.5-pro'),
                'openai_api_key' => env('OPENAI_API_KEY', ''),
                'openai_model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            ];
            
            // Nếu có dữ liệu từ request, ưu tiên sử dụng chúng
            if ($request && $request->has('api_type')) {
                $configData = [
                    'api_type' => $request->input('api_type'),
                    'google_api_key' => $request->input('google_api_key', $configData['google_api_key']),
                    'google_model' => $request->input('google_model', $configData['google_model']),
                    'openai_api_key' => $request->input('openai_api_key', $configData['openai_api_key']),
                    'openai_model' => $request->input('openai_model', $configData['openai_model']),
                ];
            }
            
            // Gọi endpoint reload-config trên API server
            $reloadUrl = env('CHATBOT_API_URL', 'http://localhost:55050') . "/api/v1/admin/reload-config";
            
            $client = new Client(['timeout' => 15]);
            $response = $client->post($reloadUrl, [
                'json' => $configData,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ]);
            
            if ($response->getStatusCode() == 200) {
                $result = json_decode($response->getBody()->getContents(), true);
                
                return response()->json([
                    'success' => $result['success'],
                    'message' => $result['message'] ?? 'Cấu hình API đã được tải lại',
                    'data' => $result
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Không thể tải lại cấu hình API.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải lại cấu hình API: ' . $e->getMessage()
            ]);
        }
    }
} 