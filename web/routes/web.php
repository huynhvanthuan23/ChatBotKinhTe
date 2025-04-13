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
use App\Http\Controllers\HomeController;

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
Route::prefix('admin')->middleware(['auth'])->name('admin.')->group(function () {
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
    
    // Thêm route chat trong admin
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
});

// API route cho media manager
Route::get('admin/api/media', [Admin\MediaController::class, 'getMedia'])->name('admin.api.media');

// Cập nhật route dashboard nếu chưa có
Route::get('/admin', function () {
    if (!Auth::user() || !Auth::user()->isAdmin()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('admin.dashboard');
})->middleware(['auth'])->name('admin.index');

// Route chat
Route::middleware(['auth'])->group(function () {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    Route::post('/chat/send', [ChatController::class, 'sendMessage'])->name('chat.send');
    Route::get('/chat/test-connection', [ChatController::class, 'testConnection'])->name('chat.test-connection');
});

// Frontend page routes
Route::get('/page/{slug}', [App\Http\Controllers\PageController::class, 'show'])->name('pages.show');

// Route cho hiển thị chi tiết bài viết
Route::get('/post/{slug}', [App\Http\Controllers\PostController::class, 'show'])->name('posts.show');

// Route cho danh sách tất cả bài viết
Route::get('/posts', [App\Http\Controllers\PostController::class, 'index'])->name('posts.index');

require __DIR__.'/auth.php';


  
