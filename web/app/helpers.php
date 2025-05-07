<?php

/**
 * Định dạng kích thước file thành định dạng dễ đọc (KB, MB, GB)
 * 
 * @param int $bytes Kích thước file tính bằng bytes
 * @param int $decimals Số chữ số thập phân
 * @return string Kích thước file đã định dạng
 */
function human_filesize($bytes, $decimals = 2)
{
    $size = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
}

/**
 * Trả về thông tin website cấu hình từ database
 * 
 * @return array Mảng thông tin website
 */
function InfoWeb() {
    return [
        \App\Models\Setting::getValue('site_logo', ''),           // [0] Logo
        \App\Models\Setting::getValue('site_favicon', ''),        // [1] Favicon
        \App\Models\Setting::getValue('site_name', 'ChatBot Kinh Tế'), // [2] Tên site
        \App\Models\Setting::getValue('facebook_url', ''),        // [3] Facebook
        \App\Models\Setting::getValue('twitter_url', ''),         // [4] Twitter
        \App\Models\Setting::getValue('instagram_url', ''),       // [5] Instagram
        \App\Models\Setting::getValue('linkedin_url', ''),        // [6] LinkedIn
        \App\Models\Setting::getValue('site_description', ''),    // [7] Mô tả site
        \App\Models\Setting::getValue('meta_keywords', ''),       // [8] Keywords
        \App\Models\Setting::getValue('meta_description', ''),    // [9] Meta Description 
        \App\Models\Setting::getValue('google_analytics_id', ''), // [10] Analytics ID
        \App\Models\Setting::getValue('google_site_verification', '') // [11] Site Verification
    ];
} 