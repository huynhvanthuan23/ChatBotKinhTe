<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Đăng ký thư mục components cho namespace admin
        Blade::componentNamespace('App\\View\\Components\\Admin', 'admin');

        // Đảm bảo yêu cầu AJAX luôn nhận được phản hồi JSON
        $this->app->afterResolving(\Illuminate\Contracts\Debug\ExceptionHandler::class, function ($handler) {
            $handler->renderable(function (\Throwable $e, Request $request) {
                if ($request->expectsJson() || $request->isXmlHttpRequest()) {
                    return response()->json([
                        'message' => 'Lỗi xử lý yêu cầu: ' . $e->getMessage(),
                    ], 500);
                }
            });
        });
    }
}
