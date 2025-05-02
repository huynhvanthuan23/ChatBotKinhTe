<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReplaceApiUrl
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Lấy URL từ .env
        $apiUrl = env('CHATBOT_API_URL', 'http://localhost:55050/api/v1/chat/chat-direct');
        
        // Kiểm tra nếu URL chứa 0.0.0.0
        if (strpos($apiUrl, '0.0.0.0') !== false) {
            // Thay thế bằng localhost
            $fixedUrl = str_replace('0.0.0.0', 'localhost', $apiUrl);
            Log::info("Replaced API URL from $apiUrl to $fixedUrl");
            
            // Đặt vào config runtime
            config(['services.chatbot.url' => $fixedUrl]);
        } else {
            config(['services.chatbot.url' => $apiUrl]);
        }
        
        return $next($request);
    }
}
