<?php

if (!function_exists('InfoWeb')) {
    /**
     * Trả về thông tin website cấu hình từ database
     * 
     * @return array Mảng thông tin website
     * [0] => logo, [1] => favicon, [2] => site_name, [7] => site_description
     */
    function InfoWeb() {
        return [
            \App\Models\Setting::getValue('site_logo', ''),
            \App\Models\Setting::getValue('site_favicon', ''),
            \App\Models\Setting::getValue('site_name', 'ChatBot Kinh Tế'),
            \App\Models\Setting::getValue('facebook_url', ''),
            \App\Models\Setting::getValue('twitter_url', ''),
            \App\Models\Setting::getValue('instagram_url', ''),
            \App\Models\Setting::getValue('linkedin_url', ''),
            \App\Models\Setting::getValue('site_description', ''),
            \App\Models\Setting::getValue('meta_keywords', ''),
            \App\Models\Setting::getValue('meta_description', ''),
            \App\Models\Setting::getValue('google_analytics_id', ''),
            \App\Models\Setting::getValue('google_site_verification', '')
        ];
    }
} 