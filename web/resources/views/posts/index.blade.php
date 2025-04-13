@extends('layouts.app')

@section('title', 'Bài viết')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Bài viết</h1>
        
        @if($posts->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach($posts as $post)
                <a href="{{ route('posts.show', $post->slug) }}" class="block group">
                    <div class="bg-white rounded-lg shadow-md overflow-hidden transition-transform duration-300 group-hover:-translate-y-1 group-hover:shadow-lg h-full flex flex-col">
                        <div class="p-4 flex-1 flex flex-col">
                            <div class="text-xs text-gray-500 mb-2">{{ $post->created_at->format('d/m/Y') }}</div>
                            
                            <h2 class="font-semibold text-gray-900 text-lg mb-2 group-hover:text-blue-600 transition-colors line-clamp-2">
                                {{ $post->title }}
                            </h2>
                            
                            <p class="text-gray-600 text-sm flex-1 line-clamp-3">
                                {{ strip_tags($post->content) }}
                            </p>
                            
                            <div class="mt-3 text-blue-600 font-medium text-sm flex items-center">
                                Đọc tiếp
                                <svg class="ml-1 w-4 h-4 group-hover:translate-x-1 transition-transform" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
            
            <div class="mt-8">
                {{ $posts->links() }}
            </div>
        @else
            <div class="bg-white p-8 rounded-lg shadow-md text-center">
                <h2 class="text-xl font-semibold text-gray-700 mb-2">Chưa có bài viết nào</h2>
                <p class="text-gray-500">Các bài viết sẽ được hiển thị tại đây khi được đăng.</p>
            </div>
        @endif
    </div>
</div>
@endsection 