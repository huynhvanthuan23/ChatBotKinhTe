<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

        <!-- Menu pages với cấu trúc đa cấp -->
        @if(isset($menuPages) && count($menuPages) > 0)
            @foreach($menuPages as $menuPage)
                @if($menuPage->children->count() > 0)
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->is('page/' . $menuPage->slug) || request()->is('page/' . $menuPage->slug . '/*') ? 'active' : '' }}" 
                           href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ $menuPage->title }}
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item {{ request()->is('page/' . $menuPage->slug) ? 'active' : '' }}" 
                                   href="{{ route('pages.show', $menuPage->slug) }}">
                                    {{ $menuPage->title }}
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            @foreach($menuPage->children as $childPage)
                                <li>
                                    <a class="dropdown-item {{ request()->is('page/' . $childPage->slug) ? 'active' : '' }}" 
                                       href="{{ route('pages.show', $childPage->slug) }}">
                                        {{ $childPage->title }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('page/' . $menuPage->slug) ? 'active' : '' }}" 
                           href="{{ $menuPage->is_homepage ? route('home') : route('pages.show', $menuPage->slug) }}">
                            {{ $menuPage->title }}
                        </a>
                    </li>
                @endif
            @endforeach
        @endif

        <!-- Thêm link chat trong phần menu -->
        <li class="nav-item">
            <a class="nav-link {{ Request::routeIs('chat') ? 'active' : '' }}" href="{{ route('chat') }}">
                <i class="fas fa-robot"></i> Chat với Bot
            </a>
        </li>
    </body>
</html>
