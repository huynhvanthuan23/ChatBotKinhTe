@php
    $siteName = \App\Models\Setting::getValue('site_name', 'ChatBot Kinh Tế');
    $contactAddress = \App\Models\Setting::getValue('contact_address');
    $contactPhone = \App\Models\Setting::getValue('contact_phone');
    $contactEmail = \App\Models\Setting::getValue('contact_email');
    $contactHotline = \App\Models\Setting::getValue('contact_hotline');
    $businessHours = \App\Models\Setting::getValue('business_hours');
    $facebookUrl = \App\Models\Setting::getValue('facebook_url');
    $twitterUrl = \App\Models\Setting::getValue('twitter_url');
    $instagramUrl = \App\Models\Setting::getValue('instagram_url');
    $youtubeUrl = \App\Models\Setting::getValue('youtube_url');
    $linkedinUrl = \App\Models\Setting::getValue('linkedin_url');
    $logo = \App\Models\Setting::getValue('site_logo');
    
    $hasSocial = !empty($facebookUrl) || !empty($twitterUrl) || !empty($instagramUrl) || !empty($youtubeUrl) || !empty($linkedinUrl);
@endphp

<footer class="bg-gray-800 text-white py-10 mt-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Thông tin website -->
            <div>
                <h3 class="text-xl font-bold mb-4">{{ $siteName }}</h3>
                @if($logo)
                    <img src="{{ asset('storage/' . $logo) }}" alt="{{ $siteName }}" class="h-12 mb-4">
                @endif
                <p class="text-gray-300 text-sm mt-2">
                    {{ \App\Models\Setting::getValue('site_description', 'Hệ thống chatbot thông minh cung cấp thông tin và tư vấn về kinh tế.') }}
                </p>
            </div>

            <!-- Thông tin liên hệ -->
            <div>
                <h3 class="text-xl font-bold mb-4">Thông tin liên hệ</h3>
                <ul class="space-y-2 text-gray-300">
                    @if($contactAddress)
                        <li class="flex items-center">
                            <i class="fas fa-map-marker-alt w-6"></i>
                            <span>{{ $contactAddress }}</span>
                        </li>
                    @endif
                    
                    @if($contactPhone)
                        <li class="flex items-center">
                            <i class="fas fa-phone w-6"></i>
                            <span>{{ $contactPhone }}</span>
                        </li>
                    @endif
                    
                    @if($contactEmail)
                        <li class="flex items-center">
                            <i class="fas fa-envelope w-6"></i>
                            <span>{{ $contactEmail }}</span>
                        </li>
                    @endif
                    
                    @if($businessHours)
                        <li class="flex items-center">
                            <i class="fas fa-clock w-6"></i>
                            <span>{{ $businessHours }}</span>
                        </li>
                    @endif
                </ul>
            </div>

            <!-- Mạng xã hội -->
            @if($hasSocial)
            <div>
                <h3 class="text-xl font-bold mb-4">Kết nối với chúng tôi</h3>
                <div class="flex space-x-8 flex-wrap">
                    @if(!empty($facebookUrl))
                        <a href="{{ $facebookUrl }}" target="_blank" class="text-white hover:text-blue-400 transition-colors mb-4 text-3xl">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    @endif
                    
                    @if(!empty($twitterUrl))
                        <a href="{{ $twitterUrl }}" target="_blank" class="text-white hover:text-blue-400 transition-colors mb-4 text-3xl">
                            <i class="fab fa-twitter"></i>
                        </a>
                    @endif
                    
                    @if(!empty($instagramUrl))
                        <a href="{{ $instagramUrl }}" target="_blank" class="text-white hover:text-blue-400 transition-colors mb-4 text-3xl">
                            <i class="fab fa-instagram"></i>
                        </a>
                    @endif
                    
                    @if(!empty($youtubeUrl))
                        <a href="{{ $youtubeUrl }}" target="_blank" class="text-white hover:text-blue-400 transition-colors mb-4 text-3xl">
                            <i class="fab fa-youtube"></i>
                        </a>
                    @endif
                    
                    @if(!empty($linkedinUrl))
                        <a href="{{ $linkedinUrl }}" target="_blank" class="text-white hover:text-blue-400 transition-colors mb-4 text-3xl">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    @endif
                </div>
            </div>
            @endif
        </div>
        
        <div class="border-t border-gray-700 mt-8 pt-6 text-center text-gray-400 text-sm">
            <p>&copy; {{ date('Y') }} {{ $siteName }}. Tất cả các quyền được bảo lưu.</p>
        </div>
    </div>
</footer> 