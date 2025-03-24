<?php

// Admin routes
Route::group(['prefix' => 'admin', 'middleware' => 'auth'], function () {
    // Dashboard
    Route::get('/', 'Admin\DashboardController@index')->name('admin.dashboard');
    
    // User Management
    Route::resource('users', 'Admin\UserController');
    
    // Page Management
    Route::resource('pages', 'Admin\PageController');
    
    // Post Management
    Route::resource('posts', 'Admin\PostController');
    
    // Media Library
    Route::get('media', 'Admin\MediaController@index')->name('admin.media.index');
    Route::get('media/upload', 'Admin\MediaController@create')->name('admin.media.create');
    Route::post('media/upload', 'Admin\MediaController@store')->name('admin.media.store');
    Route::delete('media/{id}', 'Admin\MediaController@destroy')->name('admin.media.destroy');
});

// Authentication
Route::get('admin/login', 'Admin\AuthController@showLoginForm')->name('admin.login');
Route::post('admin/login', 'Admin\AuthController@login');
Route::post('admin/logout', 'Admin\AuthController@logout')->name('admin.logout'); 