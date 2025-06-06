<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name', 'ChatBot Kinh Tế'))</title>

        <!-- Favicon -->
        @if(\App\Models\Setting::getValue('site_favicon'))
            <link rel="icon" type="image/x-icon" href="{{ asset('storage/' . \App\Models\Setting::getValue('site_favicon')) }}">
        @else
            <link rel="icon" type="image/png" href="{{ asset('images/chatbot-logo.png') }}">
        @endif

        <!-- SEO Component -->
        <x-seo />

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            @auth
                @include('layouts.navigation')
            @endauth

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-white shadow dark:bg-gray-800">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Flash Messages -->
            <div class="max-w-7xl mx-auto mt-4 px-4 sm:px-6 lg:px-8">
                @if (session('success'))
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 dark:bg-green-800/30 dark:text-green-200 dark:border-green-600" role="alert">
                        <p class="font-bold">Thành công!</p>
                        <p>{{ session('success') }}</p>
                    </div>
                @endif

                @if (session('error'))
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 dark:bg-red-800/30 dark:text-red-200 dark:border-red-600" role="alert">
                        <p class="font-bold">Lỗi!</p>
                        <p>{{ session('error') }}</p>
                    </div>
                @endif

                @if (session('warning'))
                    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4 dark:bg-yellow-800/30 dark:text-yellow-200 dark:border-yellow-600" role="alert">
                        <p class="font-bold">Cảnh báo!</p>
                        <p>{{ session('warning') }}</p>
                    </div>
                @endif

                @if (session('info'))
                    <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4 dark:bg-blue-800/30 dark:text-blue-200 dark:border-blue-600" role="alert">
                        <p class="font-bold">Thông tin!</p>
                        <p>{{ session('info') }}</p>
                    </div>
                @endif
            </div>

            <!-- Page Content -->
            <main>
                @yield('content')
                {{ $slot ?? '' }}
            </main>
        </div>
        
        @stack('styles')
        @stack('scripts')
    </body>
</html>
