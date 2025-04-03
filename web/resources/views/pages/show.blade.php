@extends('layouts.app')

@section('title', $page->meta_title ?: $page->title)

@section('meta')
<meta name="description" content="{{ $page->meta_description }}">
@if($page->meta_keywords)
<meta name="keywords" content="{{ $page->meta_keywords }}">
@endif
@endsection

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            @if($page->parent)
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Trang chủ</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('pages.show', $page->parent->slug) }}">{{ $page->parent->title }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $page->title }}</li>
                </ol>
            </nav>
            @else
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Trang chủ</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $page->title }}</li>
                </ol>
            </nav>
            @endif
            
            <div class="card">
                <div class="card-body">
                    <h1 class="card-title mb-4">{{ $page->title }}</h1>
                    
                    <div class="page-content">
                        {!! $page->content !!}
                    </div>
                    
                    @if($page->children->count() > 0)
                    <div class="mt-5">
                        <h3>Trang liên quan</h3>
                        <div class="row">
                            @foreach($page->children as $childPage)
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">{{ $childPage->title }}</h5>
                                        <p class="card-text">{{ Str::limit(strip_tags($childPage->content), 100) }}</p>
                                        <a href="{{ route('pages.show', $childPage->slug) }}" class="btn btn-primary">Xem thêm</a>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 