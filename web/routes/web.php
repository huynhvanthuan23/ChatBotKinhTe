<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\ChatController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/dashboard', function () {
    return view('dashboard');
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
});

// API route cho media manager
Route::get('admin/api/media', [Admin\MediaController::class, 'getMedia'])->name('admin.api.media');

// Cập nhật route dashboard nếu chưa có
Route::get('/admin', [DashboardController::class, 'index'])->middleware(['auth'])->name('admin.dashboard');

// Route chat
Route::middleware('auth')->group(function () {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    Route::post('/chat/send', [ChatController::class, 'sendMessage'])->name('chat.send');
});

// Frontend page routes
Route::get('/page/{slug}', [App\Http\Controllers\PageController::class, 'show'])->name('pages.show');

require __DIR__.'/auth.php';


  
