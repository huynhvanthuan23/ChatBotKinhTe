@extends('layouts.app')

@section('title', $page->meta_title ?: $page->title)

@section('meta')
<meta name="description" content="{{ $page->meta_description }}">
@if($page->meta_keywords)
<meta name="keywords" content="{{ $page->meta_keywords }}">
@endif
@endsection

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Breadcrumb -->
    <nav class="flex mb-5 text-sm text-gray-500 dark:text-gray-400" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="{{ route('home') }}" class="hover:text-blue-600 dark:hover:text-blue-400">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                    </svg>
                    Trang chủ
                </a>
            </li>
            
            @if($page->parent)
            <li>
                <div class="flex items-center">
                    <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <a href="{{ route('pages.show', $page->parent->slug) }}" class="ml-1 hover:text-blue-600 dark:hover:text-blue-400 md:ml-2">{{ $page->parent->title }}</a>
                </div>
            </li>
            @endif
            
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="ml-1 md:ml-2 font-medium text-gray-600 dark:text-gray-300">{{ $page->title }}</span>
                </div>
            </li>
        </ol>
    </nav>
    
    <article class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="p-6 md:p-8">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-6">{{ $page->title }}</h1>
            
            <div class="prose prose-lg max-w-none dark:prose-invert page-content">
                {!! $page->content !!}
            </div>
            
            @if($page->children->isNotEmpty())
            <div class="mt-12 pt-6 border-t border-gray-200 dark:border-gray-700">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Trang liên quan</h2>
                
                <div class="grid gap-6 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($page->children as $childPage)
                        <a href="{{ route('pages.show', $childPage->slug) }}" class="block transition-transform duration-300 hover:-translate-y-1">
                            <div class="h-full bg-gray-50 dark:bg-gray-700 rounded-lg p-6 border border-gray-200 dark:border-gray-600 hover:border-blue-500 dark:hover:border-blue-400 hover:shadow-md transition-all duration-300">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">{{ $childPage->title }}</h3>
                                <p class="text-gray-600 dark:text-gray-300">{{ Str::limit(strip_tags($childPage->content), 120) }}</p>
                                <div class="mt-4 text-blue-600 dark:text-blue-400 inline-flex items-center">
                                    Xem thêm
                                    <svg class="ml-1 w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </article>
</div>
@endsection

@push('styles')
<style>
    /* Cải thiện định dạng nội dung */
    .page-content img {
        max-width: 100%;
        height: auto;
        border-radius: 0.375rem;
        margin: 1.5rem 0;
    }
    
    .page-content h2 {
        margin-top: 2rem;
        margin-bottom: 1rem;
        font-size: 1.75rem;
        font-weight: 700;
        color: #1f2937;
    }
    
    .page-content h3 {
        margin-top: 1.5rem;
        margin-bottom: 0.75rem;
        font-size: 1.5rem;
        font-weight: 600;
        color: #1f2937;
    }
    
    .page-content p {
        margin-bottom: 1.25rem;
        line-height: 1.7;
        text-align: justify;
    }
    
    .page-content ul, .page-content ol {
        margin: 1rem 0;
        padding-left: 1.5rem;
    }
    
    .page-content li {
        margin-bottom: 0.5rem;
    }
    
    .page-content blockquote {
        border-left: 4px solid #e5e7eb;
        padding-left: 1rem;
        margin: 1.5rem 0;
        color: #4b5563;
        font-style: italic;
    }
    
    .page-content a {
        color: #2563eb;
        text-decoration: underline;
    }
    
    .page-content a:hover {
        color: #1d4ed8;
    }
    
    /* Định dạng đặc biệt cho nội dung từ file text */
    .page-content p {
        text-indent: 1.5rem;
    }
    
    .page-content p + p {
        margin-top: -0.5rem;
    }
    
    /* Giúp hiển thị tên người */
    .page-content strong, 
    .page-content b {
        color: #111827;
        font-weight: 600;
    }
    
    /* Dark mode */
    .dark .page-content h2,
    .dark .page-content h3 {
        color: #f3f4f6;
    }
    
    .dark .page-content blockquote {
        border-left-color: #4b5563;
        color: #d1d5db;
    }
    
    .dark .page-content a {
        color: #3b82f6;
    }
    
    .dark .page-content a:hover {
        color: #60a5fa;
    }
    
    .dark .page-content strong,
    .dark .page-content b {
        color: #f3f4f6;
    }
</style>
@endpush 