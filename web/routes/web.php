<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CitationController;

Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/dashboard', function () {
    if (Auth::user() && Auth::user()->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }
    return redirect()->route('chat');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin routes
Route::middleware(['auth', \App\Http\Middleware\AdminMiddleware::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('users', UserController::class);
    Route::resource('posts', \App\Http\Controllers\Admin\PostController::class);
    Route::resource('pages', \App\Http\Controllers\Admin\PageController::class);
    Route::resource('media', \App\Http\Controllers\Admin\MediaController::class);
    Route::post('media/upload-from-editor', [\App\Http\Controllers\Admin\MediaController::class, 'uploadFromEditor'])->name('media.upload-from-editor');
    Route::post('posts/check-title', [App\Http\Controllers\Admin\PostController::class, 'checkTitle'])->name('posts.checkTitle');
    Route::post('pages/check-title', [App\Http\Controllers\Admin\PageController::class, 'checkTitle'])->name('pages.checkTitle');
    Route::post('pages/{page}/set-homepage', [App\Http\Controllers\Admin\PageController::class, 'setHomepage'])->name('pages.set-homepage');
    
    // System status routes
    Route::get('/system', [\App\Http\Controllers\Admin\SystemController::class, 'index'])->name('system.index');
    Route::post('/system/test-chatbot', [\App\Http\Controllers\Admin\SystemController::class, 'testChatbot'])->name('system.test-chatbot');
    Route::get('/system/api-docs', function () {
        return view('admin.system.api-docs');
    })->name('system.api-docs');
    
    // API Configuration routes
    Route::get('/system/api-config', [\App\Http\Controllers\Admin\SystemController::class, 'showApiConfig'])->name('system.api-config');
    Route::post('/system/api-config', [\App\Http\Controllers\Admin\SystemController::class, 'updateApiConfig'])->name('system.update-api-config');
    Route::post('/system/test-api-connection', [\App\Http\Controllers\Admin\SystemController::class, 'testApiConnection'])->name('system.test-api-connection');
    Route::post('/system/reload-api-config', [\App\Http\Controllers\Admin\SystemController::class, 'reloadApiConfig'])->name('system.reload-api-config');
    
    // Website settings routes
    Route::get('/settings', [\App\Http\Controllers\Admin\SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings/general', [\App\Http\Controllers\Admin\SettingController::class, 'updateGeneral'])->name('settings.update-general');
    Route::post('/settings/seo', [\App\Http\Controllers\Admin\SettingController::class, 'updateSeo'])->name('settings.update-seo');
    Route::post('/settings/contact', [\App\Http\Controllers\Admin\SettingController::class, 'updateContact'])->name('settings.update-contact');
    Route::post('/settings/social', [\App\Http\Controllers\Admin\SettingController::class, 'updateSocial'])->name('settings.update-social');
    Route::post('/settings/logo', [\App\Http\Controllers\Admin\SettingController::class, 'updateLogo'])->name('settings.update-logo');
    Route::post('/settings/initialize', [\App\Http\Controllers\Admin\SettingController::class, 'initializeDefaults'])->name('settings.initialize');
    
    // Thêm route chat trong admin
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
});

// API route cho media manager
Route::get('admin/api/media', [Admin\MediaController::class, 'getMedia'])->middleware(['auth', \App\Http\Middleware\AdminMiddleware::class])->name('admin.api.media');

// Cập nhật route dashboard nếu chưa có
Route::get('/admin', function () {
    if (!Auth::user() || !Auth::user()->isAdmin()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('admin.dashboard');
})->middleware(['auth', \App\Http\Middleware\AdminMiddleware::class])->name('admin.index');

// Route chat
Route::middleware(['auth'])->group(function () {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    Route::post('/chat/send', [ChatController::class, 'sendMessage'])->name('chat.send');
    Route::get('/chat/test-connection', [ChatController::class, 'testConnection'])->name('chat.test-connection');

    // Thêm các route mới cho quản lý cuộc trò chuyện
    Route::post('/chat/create-conversation', [ChatController::class, 'createConversation'])->name('chat.create-conversation');
    Route::post('/chat/save-message', [ChatController::class, 'saveMessage'])->name('chat.save-message');
    Route::get('/chat/conversations', [ChatController::class, 'getConversations'])->name('chat.conversations');
    Route::get('/chat/conversations/{id}/messages', [ChatController::class, 'getMessages'])->name('chat.messages');
    Route::delete('/chat/conversations/{id}', [ChatController::class, 'deleteConversation'])->name('chat.delete-conversation');
    
    // Route cho API trích dẫn
    Route::get('/api/citation/{docId}/{page}', [CitationController::class, 'getCitationContent'])->name('api.citation.content');
    Route::get('/api/citation-document-type/{docId}', [CitationController::class, 'getDocumentType'])->name('api.citation.document-type');
    Route::get('/api/citation-document/{docId}', [CitationController::class, 'getCitationDocument'])->name('api.citation.document');
    
    // Thêm routes cho quản lý tài liệu
    Route::prefix('documents')->group(function () {
        Route::get('/', [App\Http\Controllers\DocumentController::class, 'index'])->name('documents.index');
        Route::get('/create', [App\Http\Controllers\DocumentController::class, 'create'])->name('documents.create');
        Route::post('/', [App\Http\Controllers\DocumentController::class, 'store'])->name('documents.store');
        Route::get('/{id}', [App\Http\Controllers\DocumentController::class, 'show'])->name('documents.show');
        Route::delete('/{id}', [App\Http\Controllers\DocumentController::class, 'destroy'])->name('documents.destroy');
        
        // Xem tài liệu Word dưới dạng HTML
        Route::get('/{id}/view', [App\Http\Controllers\DocumentController::class, 'viewDocument'])->name('documents.view');
        
        // Reload cache của tài liệu
        Route::get('/{id}/reload-cache', [App\Http\Controllers\DocumentController::class, 'reloadDocCache'])->name('documents.reload-cache');
        
        // Chat với document
        Route::get('/{id}/chat', [App\Http\Controllers\DocumentController::class, 'showChat'])->name('documents.chat');
        Route::post('/ask-selected', [App\Http\Controllers\DocumentController::class, 'askSelected'])->name('documents.ask-selected');
        Route::post('/save-selection', [App\Http\Controllers\DocumentController::class, 'saveSelection'])->name('documents.save-selection');
        
        // Vector creation routes
        Route::post('/{id}/create-vector', [App\Http\Controllers\DocumentController::class, 'createVector'])->name('documents.createVector');
        Route::post('/create-all-vectors', [App\Http\Controllers\DocumentController::class, 'createAllVectors'])->name('documents.create-all-vectors');
    });
});

// API Callback route for vector processing (doesn't require auth as it's from our backend)
Route::post('/api/document/update-vector-status', [App\Http\Controllers\DocumentController::class, 'updateVectorStatus'])->name('api.document.update-vector-status');

// Frontend page routes
Route::get('/page/{slug}', [App\Http\Controllers\PageController::class, 'show'])->name('pages.show');

// Route cho hiển thị chi tiết bài viết
Route::get('/post/{slug}', [App\Http\Controllers\PostController::class, 'show'])->name('posts.show');

// Route cho danh sách tất cả bài viết
Route::get('/posts', [App\Http\Controllers\PostController::class, 'index'])->name('posts.index');

require __DIR__.'/auth.php';


  
