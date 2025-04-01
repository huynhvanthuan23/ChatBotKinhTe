<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\MediaController;

Route::get('/', function () {
    return view('welcome');
});

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
    Route::resource('users', Admin\UserController::class);
    Route::resource('posts', \App\Http\Controllers\Admin\PostController::class);
    Route::resource('pages', \App\Http\Controllers\Admin\PageController::class);
    Route::resource('media', MediaController::class);
    Route::post('media/upload-from-editor', [MediaController::class, 'uploadFromEditor'])->name('media.upload-from-editor');
});

// API route cho media manager
Route::get('admin/api/media', [Admin\MediaController::class, 'getMedia'])->name('admin.api.media');

require __DIR__.'/auth.php';


  
