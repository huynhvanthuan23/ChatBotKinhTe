<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    /**
     * Display the settings page
     */
    public function index()
    {
        // Lấy tất cả settings phân nhóm
        $generalSettings = Setting::where('group', 'general')->get();
        $seoSettings = Setting::where('group', 'seo')->get();
        $contactSettings = Setting::where('group', 'contact')->get();
        $socialSettings = Setting::where('group', 'social')->get();
        
        return view('admin.settings.index', compact(
            'generalSettings', 
            'seoSettings', 
            'contactSettings', 
            'socialSettings'
        ));
    }

    /**
     * Update the general settings
     */
    public function updateGeneral(Request $request)
    {
        $data = $request->validate([
            'site_name' => 'required|string|max:255',
            'site_description' => 'nullable|string',
            'admin_email' => 'required|email',
            'items_per_page' => 'required|integer|min:5|max:100',
            'maintenance_mode' => 'boolean',
        ]);

        foreach ($data as $key => $value) {
            // Xác định kiểu dữ liệu
            $type = 'string';
            if (is_bool($value)) $type = 'boolean';
            if (is_int($value)) $type = 'integer';
            
            Setting::setValue($key, $value, 'general', $type);
        }

        return redirect()->route('admin.settings.index')
            ->with('success', 'Cấu hình chung đã được cập nhật thành công.');
    }

    /**
     * Update the SEO settings
     */
    public function updateSeo(Request $request)
    {
        $data = $request->validate([
            'meta_keywords' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'google_analytics_id' => 'nullable|string',
            'google_site_verification' => 'nullable|string',
        ]);

        foreach ($data as $key => $value) {
            Setting::setValue($key, $value, 'seo');
        }

        return redirect()->route('admin.settings.index')
            ->with('success', 'Cấu hình SEO đã được cập nhật thành công.');
    }

    /**
     * Update the contact settings
     */
    public function updateContact(Request $request)
    {
        $data = $request->validate([
            'contact_address' => 'nullable|string',
            'contact_phone' => 'nullable|string',
            'contact_email' => 'nullable|email',
            'google_map_embed' => 'nullable|string',
        ]);

        foreach ($data as $key => $value) {
            Setting::setValue($key, $value, 'contact');
        }

        return redirect()->route('admin.settings.index')
            ->with('success', 'Cấu hình thông tin liên hệ đã được cập nhật thành công.');
    }

    /**
     * Update the social settings
     */
    public function updateSocial(Request $request)
    {
        $data = $request->validate([
            'facebook_url' => 'nullable|url',
            'twitter_url' => 'nullable|url',
            'instagram_url' => 'nullable|url',
            'youtube_url' => 'nullable|url',
            'linkedin_url' => 'nullable|url',
        ]);

        foreach ($data as $key => $value) {
            Setting::setValue($key, $value, 'social');
        }

        return redirect()->route('admin.settings.index')
            ->with('success', 'Cấu hình mạng xã hội đã được cập nhật thành công.');
    }

    /**
     * Update logo settings
     */
    public function updateLogo(Request $request)
    {
        $request->validate([
            'site_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'site_favicon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,ico|max:1024',
        ]);

        if ($request->hasFile('site_logo')) {
            $logoPath = $request->file('site_logo')->store('logos', 'public');
            Setting::setValue('site_logo', $logoPath, 'general');
        }

        if ($request->hasFile('site_favicon')) {
            $faviconPath = $request->file('site_favicon')->store('logos', 'public');
            Setting::setValue('site_favicon', $faviconPath, 'general');
        }

        return redirect()->route('admin.settings.index')
            ->with('success', 'Logo và favicon đã được cập nhật thành công.');
    }

    /**
     * Initialize default settings
     */
    public function initializeDefaults()
    {
        // General settings
        Setting::setValue('site_name', 'ChatBot Kinh Tế', 'general', 'string', 'Tên website');
        Setting::setValue('site_description', 'Cổng thông tin kinh tế với trợ lý AI', 'general', 'string', 'Mô tả website');
        Setting::setValue('admin_email', 'admin@example.com', 'general', 'string', 'Email quản trị viên');
        Setting::setValue('items_per_page', 10, 'general', 'integer', 'Số mục trên mỗi trang');
        Setting::setValue('maintenance_mode', false, 'general', 'boolean', 'Chế độ bảo trì');

        // SEO settings
        Setting::setValue('meta_keywords', 'kinh tế, việt nam, AI, chatbot', 'seo', 'string', 'Từ khóa Meta');
        Setting::setValue('meta_description', 'ChatBot Kinh Tế - Nguồn thông tin kinh tế Việt Nam', 'seo', 'string', 'Mô tả Meta');
        
        // Contact settings
        Setting::setValue('contact_address', 'Hà Nội, Việt Nam', 'contact', 'string', 'Địa chỉ liên hệ');
        Setting::setValue('contact_phone', '(+84) 123 456 789', 'contact', 'string', 'Số điện thoại liên hệ');
        Setting::setValue('contact_email', 'contact@example.com', 'contact', 'string', 'Email liên hệ');
        
        // Social settings
        Setting::setValue('facebook_url', 'https://facebook.com/', 'social', 'string', 'URL Facebook');
        Setting::setValue('twitter_url', 'https://twitter.com/', 'social', 'string', 'URL Twitter');

        return redirect()->route('admin.settings.index')
            ->with('success', 'Cấu hình mặc định đã được khởi tạo thành công.');
    }
}
