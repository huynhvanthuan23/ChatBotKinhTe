@extends('layouts.app')

@section('title', $homepage->meta_title ?: $homepage->title)

@section('meta')
<meta name="description" content="{{ $homepage->meta_description }}">
@if($homepage->meta_keywords)
<meta name="keywords" content="{{ $homepage->meta_keywords }}">
@endif
@endsection

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="homepage-content mb-5">
                <h1 class="mb-4">{{ $homepage->title }}</h1>
                
                <div class="page-content">
                    {!! $homepage->content !!}
                </div>
            </div>
            
            @if($latestPosts->count() > 0)
            <div class="latest-posts py-4">
                <h2 class="mb-4">Bài viết mới nhất</h2>
                
                <div class="row">
                    @foreach($latestPosts as $post)
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            @if($post->image)
                            <img src="{{ Storage::url($post->image) }}" class="card-img-top" alt="{{ $post->title }}" style="height: 200px; object-fit: cover;">
                            @endif
                            <div class="card-body">
                                <h5 class="card-title">{{ $post->title }}</h5>
                                <p class="card-text">{{ Str::limit(strip_tags($post->content), 100) }}</p>
                                <a href="{{ route('posts.show', $post->slug) }}" class="btn btn-primary">Đọc tiếp</a>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                
                <div class="text-center mt-4">
                    <a href="{{ route('posts.index') }}" class="btn btn-outline-primary">Xem tất cả bài viết</a>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection 