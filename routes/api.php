// Chatbot routes
Route::prefix('chatbot')->group(function () {
    Route::get('/chat', 'App\Http\Controllers\Api\ChatbotController@chat');
    Route::post('/chat', 'App\Http\Controllers\Api\ChatbotController@chat');
    Route::get('/health', 'App\Http\Controllers\Api\ChatbotController@healthCheck');
    Route::get('/service-info', 'App\Http\Controllers\Api\ChatbotController@serviceInfo');
}); 