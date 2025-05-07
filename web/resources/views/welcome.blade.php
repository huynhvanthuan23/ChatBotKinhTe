<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ \App\Models\Setting::getValue('site_name', 'Laravel') }}</title>

        <!-- Favicon -->
        @if(\App\Models\Setting::getValue('site_favicon'))
        <link rel="icon" type="image/x-icon" href="{{ asset('storage/' . \App\Models\Setting::getValue('site_favicon')) }}">
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <style>
            .main-menu {
                display: flex;
                justify-content: center;
                gap: 0;
                margin-bottom: 1rem;
                background-color: rgba(255, 255, 255, 0.05);
                border-radius: 8px;
                overflow: hidden;
            }
            
            .menu-item {
                text-decoration: none;
                color: #4b5563;
                font-weight: 500;
                padding: 0.75rem 1.25rem;
                transition: all 0.2s ease;
                position: relative;
            }
            
            .menu-item:hover {
                color: #2563eb;
                background-color: rgba(255, 255, 255, 0.1);
            }
            
            .dark .menu-item {
                color:rgb(99, 161, 255);
            }
            
            .dark .menu-item:hover {
                color: #60a5fa;
                background-color: rgba(255, 255, 255, 0.05);
            }
            
            .menu-item::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 50%;
                transform: translateX(-50%);
                width: 0;
                height: 2px;
                background-color: #2563eb;
                transition: width 0.3s ease;
            }
            
            .menu-item:hover::after {
                width: 80%;
            }
            
            .dark .menu-item::after {
                background-color: #60a5fa;
            }
            
            /* Posts section */
            .posts-section {
                margin-top: 3rem;
            }
            
            .section-title {
                position: relative;
                display: inline-block;
                margin-bottom: 1.5rem;
            }
            
            .section-title::after {
                content: '';
                position: absolute;
                bottom: -0.5rem;
                left: 0;
                width: 50%;
                height: 3px;
                background-color: #2563eb;
                border-radius: 3px;
            }
            
            .dark .section-title::after {
                background-color: #60a5fa;
            }
            
            .post-card {
                height: 100%;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }
            
            .post-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            }
            
            .post-img-container {
                position: relative;
                padding-top: 56.25%; /* 16:9 Aspect Ratio */
                overflow: hidden;
            }
            
            .post-img {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform 0.5s ease;
            }
            
            .post-card:hover .post-img {
                transform: scale(1.05);
            }
            
            .post-content {
                flex: 1;
                display: flex;
                flex-direction: column;
            }
            
            .post-title {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            
            .post-excerpt {
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                overflow: hidden;
                flex: 1;
            }
            
            /* Responsive adjustments */
            @media (max-width: 640px) {
                .main-menu {
                    flex-wrap: wrap;
                }
                
                .menu-item {
                    flex: 1 1 auto;
                    text-align: center;
                    padding: 0.5rem 0.75rem;
                    font-size: 0.875rem;
                }
            }
        </style>
    </head>
    <body class="antialiased">
        <div class="relative sm:flex sm:justify-center sm:items-center min-h-screen bg-dots-darker bg-center bg-gray-100 dark:bg-dots-lighter dark:bg-gray-900 selection:bg-red-500 selection:text-white">
            @if (Route::has('login'))
                <div class="sm:fixed sm:top-0 sm:right-0 p-6 text-right z-10">
                    @auth
                        <a href="{{ route('chat') }}" class="font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white focus:outline focus:outline-2 focus:rounded-sm focus:outline-red-500">
                            Chatbot
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white focus:outline focus:outline-2 focus:rounded-sm focus:outline-red-500">
                            Đăng nhập
                        </a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="ml-4 font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white focus:outline focus:outline-2 focus:rounded-sm focus:outline-red-500">
                                Đăng ký
                            </a>
                        @endif
                    @endauth
                </div>
            @endif

            <div class="max-w-7xl mx-auto p-6 lg:p-8">
                <div class="flex justify-center">
                    @if(\App\Models\Setting::getValue('site_logo'))
                        <img src="{{ asset('storage/' . \App\Models\Setting::getValue('site_logo')) }}" alt="{{ \App\Models\Setting::getValue('site_name', 'ChatBot Kinh Tế') }}" class="h-16 mb-3">
                    @else
                        <h1 class="text-4xl font-bold text-gray-900 dark:text-white">{{ \App\Models\Setting::getValue('site_name', 'ChatBot Kinh Tế') }}</h1>
                    @endif
                </div>
                
                <!-- Thanh menu ngang -->
                @php
                $menuPages = \App\Models\Page::where('show_in_menu', true)
                                            ->where('status', 'published')
                                            ->whereNull('parent_id')
                                            ->orderBy('order')
                                            ->get()
                                            ->unique('title');
                @endphp
                
                @if($menuPages->count() > 0)
                <div class="mt-6">
                    <div class="main-menu">
                        @foreach($menuPages as $page)
                            <a href="{{ route('pages.show', $page->slug) }}" class="menu-item">{{ $page->title }}</a>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="mt-12">
                    <div class="grid grid-cols-1 md:grid-cols-1 gap-6 lg:gap-8">
                        <div class="scale-100 p-6 bg-white dark:bg-gray-800/50 dark:bg-gradient-to-bl from-gray-700/50 via-transparent dark:ring-1 dark:ring-inset dark:ring-white/5 rounded-lg shadow-2xl shadow-gray-500/20 dark:shadow-none flex motion-safe:hover:scale-[1.01] transition-all duration-250 focus:outline focus:outline-2 focus:outline-red-500">
                            <div>
                                <h2 class="mt-6 text-xl font-semibold text-gray-900 dark:text-white">Chào mừng đến với {{ \App\Models\Setting::getValue('site_name', 'ChatBot Kinh Tế') }}</h2>
                                <p class="mt-4 text-gray-500 dark:text-gray-400 text-sm leading-relaxed">
                                    {{ \App\Models\Setting::getValue('site_description', 'Hệ thống chatbot thông minh cung cấp thông tin và tư vấn về kinh tế, tài chính và đầu tư.') }}
                                </p>
                                
                                <div class="mt-8">
                                    @auth
                                        <a href="{{ route('chat') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Vào trang Chatbot</a>
                                    @else
                                        <a href="{{ route('login') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Đăng nhập ngay</a>
                                    @endauth
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Posts Section -->
                @if(isset($latestPosts) && $latestPosts->count() > 0)
                <div class="posts-section py-8">
                    <h2 class="section-title text-2xl font-bold text-gray-900 dark:text-white">Bài viết mới nhất</h2>
                    
                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($latestPosts as $post)
                        <a href="{{ route('posts.show', $post->slug) }}" class="block group">
                            <div class="bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 group-hover:-translate-y-1 h-full flex flex-col">
                                <div class="p-5 flex-1 flex flex-col">
                                    <h3 class="font-semibold text-gray-900 dark:text-white text-lg mb-3 leading-tight line-clamp-2">
                                        {{ $post->title }}
                                    </h3>
                                    
                                    <p class="text-gray-600 dark:text-gray-300 text-sm flex-1 leading-relaxed line-clamp-3">
                                        {{ strip_tags($post->content) }}
                                    </p>
                                    
                                    <div class="mt-4 flex items-center justify-between">
                                        <div class="text-blue-600 dark:text-blue-400 font-medium text-sm flex items-center">
                                            Đọc tiếp
                                            <svg class="ml-1 w-4 h-4 group-hover:translate-x-1 transition-transform" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                        
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $post->created_at->format('d/m/Y') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                        @endforeach
                    </div>
                    
                    <div class="mt-8 text-center">
                        <a href="{{ route('posts.index') }}" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg inline-flex items-center transition-colors">
                            Xem tất cả bài viết
                            <svg class="ml-1 w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" d="M12.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </a>
                    </div>
                </div>
                @endif

                <div class="mt-8 text-center">
                    @guest
                        <a href="{{ route('register') }}" class="px-6 py-3 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition duration-200">
                            Đăng ký ngay
                        </a>
                    @endguest
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        @include('components.footer')
    </body>
</html>
