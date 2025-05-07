@extends('layouts.admin')

@section('title', 'Cấu hình Website')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quản lý cấu hình website</h3>
                    <div class="card-tools">
                        <form action="{{ route('admin.settings.initialize') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-warning">
                                <i class="fas fa-sync-alt"></i> Khởi tạo cấu hình mặc định
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="general-tab" data-bs-toggle="tab" href="#general" role="tab" aria-controls="general" aria-selected="true">
                                <i class="fas fa-cog"></i> Cấu hình chung
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="seo-tab" data-bs-toggle="tab" href="#seo" role="tab" aria-controls="seo" aria-selected="false">
                                <i class="fas fa-search"></i> SEO
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="contact-tab" data-bs-toggle="tab" href="#contact" role="tab" aria-controls="contact" aria-selected="false">
                                <i class="fas fa-envelope"></i> Liên hệ
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="social-tab" data-bs-toggle="tab" href="#social" role="tab" aria-controls="social" aria-selected="false">
                                <i class="fas fa-share-alt"></i> Mạng xã hội
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="logo-tab" data-bs-toggle="tab" href="#logo" role="tab" aria-controls="logo" aria-selected="false">
                                <i class="fas fa-image"></i> Logo & Favicon
                            </a>
                        </li>
                    </ul>
                    
                    <div class="tab-content mt-4" id="settingsTabsContent">
                        <!-- Cấu hình chung -->
                        <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                            <form action="{{ route('admin.settings.update-general') }}" method="POST">
                                @csrf
                                <div class="form-group mb-3">
                                    <label for="site_name">Tên website <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('site_name') is-invalid @enderror" 
                                        id="site_name" name="site_name" 
                                        value="{{ old('site_name', \App\Models\Setting::getValue('site_name', 'ChatBot Kinh Tế')) }}">
                                    @error('site_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="site_description">Mô tả website</label>
                                    <textarea class="form-control @error('site_description') is-invalid @enderror" 
                                        id="site_description" name="site_description" rows="3">{{ old('site_description', \App\Models\Setting::getValue('site_description')) }}</textarea>
                                    @error('site_description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Lưu cấu hình chung
                                </button>
                            </form>
                        </div>
                        
                        <!-- Cấu hình SEO -->
                        <div class="tab-pane fade" id="seo" role="tabpanel" aria-labelledby="seo-tab">
                            <form action="{{ route('admin.settings.update-seo') }}" method="POST">
                                @csrf
                                
                               
                                <div class="form-group mb-3">
                                    <label for="meta_keywords">Meta Keywords</label>
                                    <textarea class="form-control @error('meta_keywords') is-invalid @enderror" 
                                        id="meta_keywords" name="meta_keywords" rows="2">{{ old('meta_keywords', \App\Models\Setting::getValue('meta_keywords')) }}</textarea>
                                    <small class="form-text text-muted">Các từ khóa được phân cách bằng dấu phẩy.</small>
                                    @error('meta_keywords')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label for="meta_description">Meta Description</label>
                                    <textarea class="form-control @error('meta_description') is-invalid @enderror" 
                                        id="meta_description" name="meta_description" rows="3">{{ old('meta_description', \App\Models\Setting::getValue('meta_description')) }}</textarea>
                                    @error('meta_description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="google_analytics_id">Google Analytics ID</label>
                                            <input type="text" class="form-control @error('google_analytics_id') is-invalid @enderror" 
                                                id="google_analytics_id" name="google_analytics_id" placeholder="UA-XXXXXXXXX-X hoặc G-XXXXXXXXXX"
                                                value="{{ old('google_analytics_id', \App\Models\Setting::getValue('google_analytics_id')) }}">
                                            @error('google_analytics_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="google_site_verification">Google Site Verification</label>
                                            <input type="text" class="form-control @error('google_site_verification') is-invalid @enderror" 
                                                id="google_site_verification" name="google_site_verification" 
                                                value="{{ old('google_site_verification', \App\Models\Setting::getValue('google_site_verification')) }}">
                                            @error('google_site_verification')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h5>Xem trước SEO component</h5>
                                    <div class="card">
                                        <div class="card-body bg-light">
                                            <h6 class="text-muted">Component seo.blade.php hiện tại:</h6>
                                            <pre class="border p-3 bg-white" style="max-height: 200px; overflow-y: auto;"><code>{{ file_get_contents(resource_path('views/components/seo.blade.php')) }}</code></pre>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Lưu cấu hình SEO
                                </button>
                            </form>
                        </div>
                        
                        <!-- Cấu hình liên hệ -->
                        <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
                            <form action="{{ route('admin.settings.update-contact') }}" method="POST">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="contact_address">Địa chỉ</label>
                                            <input type="text" class="form-control @error('contact_address') is-invalid @enderror" 
                                                id="contact_address" name="contact_address" 
                                                value="{{ old('contact_address', \App\Models\Setting::getValue('contact_address')) }}">
                                            @error('contact_address')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="contact_phone">Số điện thoại</label>
                                            <input type="text" class="form-control @error('contact_phone') is-invalid @enderror" 
                                                id="contact_phone" name="contact_phone" 
                                                value="{{ old('contact_phone', \App\Models\Setting::getValue('contact_phone')) }}">
                                            @error('contact_phone')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="contact_email">Email liên hệ</label>
                                            <input type="email" class="form-control @error('contact_email') is-invalid @enderror" 
                                                id="contact_email" name="contact_email" 
                                                value="{{ old('contact_email', \App\Models\Setting::getValue('contact_email')) }}">
                                            @error('contact_email')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            
                                            @error('contact_hotline')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group mb-3">
                                    
                                    @error('business_hours')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="form-group mb-3">
                                    
                                    @error('google_map_embed')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Lưu thông tin liên hệ
                                </button>
                            </form>
                        </div>
                        
                        <!-- Cấu hình mạng xã hội -->
                        <div class="tab-pane fade" id="social" role="tabpanel" aria-labelledby="social-tab">
                            <form action="{{ route('admin.settings.update-social') }}" method="POST">
                                @csrf
                                
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="facebook_url">URL Facebook</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fab fa-facebook"></i></span>
                                                <input type="url" class="form-control @error('facebook_url') is-invalid @enderror" 
                                                    id="facebook_url" name="facebook_url" 
                                                    value="{{ old('facebook_url', \App\Models\Setting::getValue('facebook_url')) }}">
                                                <button type="button" class="btn btn-outline-secondary clear-url" data-target="facebook_url">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            @error('facebook_url')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="twitter_url">URL Twitter</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fab fa-twitter"></i></span>
                                                <input type="url" class="form-control @error('twitter_url') is-invalid @enderror" 
                                                    id="twitter_url" name="twitter_url" 
                                                    value="{{ old('twitter_url', \App\Models\Setting::getValue('twitter_url')) }}">
                                                <button type="button" class="btn btn-outline-secondary clear-url" data-target="twitter_url">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            @error('twitter_url')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="instagram_url">URL Instagram</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fab fa-instagram"></i></span>
                                                <input type="url" class="form-control @error('instagram_url') is-invalid @enderror" 
                                                    id="instagram_url" name="instagram_url" 
                                                    value="{{ old('instagram_url', \App\Models\Setting::getValue('instagram_url')) }}">
                                                <button type="button" class="btn btn-outline-secondary clear-url" data-target="instagram_url">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            @error('instagram_url')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="youtube_url">URL Youtube</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fab fa-youtube"></i></span>
                                                <input type="url" class="form-control @error('youtube_url') is-invalid @enderror" 
                                                    id="youtube_url" name="youtube_url" 
                                                    value="{{ old('youtube_url', \App\Models\Setting::getValue('youtube_url')) }}">
                                                <button type="button" class="btn btn-outline-secondary clear-url" data-target="youtube_url">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            @error('youtube_url')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="linkedin_url">URL LinkedIn</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fab fa-linkedin"></i></span>
                                                <input type="url" class="form-control @error('linkedin_url') is-invalid @enderror" 
                                                    id="linkedin_url" name="linkedin_url" 
                                                    value="{{ old('linkedin_url', \App\Models\Setting::getValue('linkedin_url')) }}">
                                                <button type="button" class="btn btn-outline-secondary clear-url" data-target="linkedin_url">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            @error('linkedin_url')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Lưu cấu hình mạng xã hội
                                </button>
                            </form>
                        </div>
                        
                        <!-- Logo & Favicon -->
                        <div class="tab-pane fade" id="logo" role="tabpanel" aria-labelledby="logo-tab">
                            <form action="{{ route('admin.settings.update-logo') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="site_logo">Logo Website</label>
                                            <div class="input-group">
                                                <input type="file" class="form-control @error('site_logo') is-invalid @enderror" 
                                                    id="site_logo" name="site_logo" accept="image/*">
                                            </div>
                                            <small class="form-text text-muted">Kích thước đề xuất: 200x50 pixels.</small>
                                            @error('site_logo')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            
                                            @if(\App\Models\Setting::getValue('site_logo'))
                                                <div class="mt-2">
                                                    <p>Logo hiện tại:</p>
                                                    <img src="{{ asset('storage/' . \App\Models\Setting::getValue('site_logo')) }}" 
                                                        alt="Logo hiện tại" class="img-fluid" style="max-height: 50px;">
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="site_favicon">Favicon</label>
                                            <div class="input-group">
                                                <input type="file" class="form-control @error('site_favicon') is-invalid @enderror" 
                                                    id="site_favicon" name="site_favicon" accept="image/*">
                                            </div>
                                            <small class="form-text text-muted">Kích thước đề xuất: 32x32 pixels.</small>
                                            @error('site_favicon')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            
                                            @if(\App\Models\Setting::getValue('site_favicon'))
                                                <div class="mt-2">
                                                    <p>Favicon hiện tại:</p>
                                                    <img src="{{ asset('storage/' . \App\Models\Setting::getValue('site_favicon')) }}" 
                                                        alt="Favicon hiện tại" class="img-fluid" style="max-height: 32px;">
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Lưu logo và favicon
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Xử lý nút xóa URL
    document.querySelectorAll('.clear-url').forEach(function(button) {
        button.addEventListener('click', function() {
            var targetId = this.getAttribute('data-target');
            document.getElementById(targetId).value = '';
        });
    });
});
</script>
@endpush 