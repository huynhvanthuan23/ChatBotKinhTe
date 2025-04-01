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
            $menuPages = Page::where('show_in_menu', true)
                ->where('status', 'published')
                ->orderBy('order')
                ->get();
                
            $view->with('menuPages', $menuPages);
        });
    }
}
