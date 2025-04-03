<?php

namespace App\Providers;

use App\Models\Page;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            // Lấy tất cả các trang gốc (không có parent) và hiển thị trong menu
            $menuPages = Page::where('show_in_menu', true)
                ->where('status', 'published')
                ->where(function($query) {
                    $query->whereNull('published_at')
                          ->orWhere('published_at', '<=', now());
                })
                ->whereNull('parent_id')
                ->orderBy('order')
                ->with(['children' => function($query) {
                    $query->where('show_in_menu', true)
                          ->where('status', 'published')
                          ->where(function($q) {
                              $q->whereNull('published_at')
                                ->orWhere('published_at', '<=', now());
                          })
                          ->orderBy('order');
                }])
                ->get();
                
            $view->with('menuPages', $menuPages);
        });
    }
}
