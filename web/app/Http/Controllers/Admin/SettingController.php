<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

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
        try {
            // Lấy giá trị các trường
            $siteName = $request->input('site_name');
            $siteDescription = $request->input('site_description');
            
            // Xác thực dữ liệu
            if (empty($siteName)) {
                return redirect()->back()
                    ->withErrors(['site_name' => 'Tên website là bắt buộc'])
                    ->withInput();
            }
            
            // Lưu vào database
            $setting1 = Setting::updateOrCreate(
                ['key' => 'site_name'],
                [
                    'value' => $siteName,
                    'group' => 'general',
                    'type' => 'string',
                    'description' => 'Tên website'
                ]
            );
            
            $setting2 = Setting::updateOrCreate(
                ['key' => 'site_description'],
                [
                    'value' => $siteDescription,
                    'group' => 'general',
                    'type' => 'string',
                    'description' => 'Mô tả website'
                ]
            );

        return redirect()->route('admin.settings.index')
            ->with('success', 'Cấu hình chung đã được cập nhật thành công.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Đã xảy ra lỗi: ' . $e->getMessage())
                ->withInput();
        }
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
            'business_hours' => 'nullable|string',
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

        // Xử lý từng URL một, cho phép giá trị null hoặc trống
        foreach (['facebook_url', 'twitter_url', 'instagram_url', 'youtube_url', 'linkedin_url'] as $key) {
            // Lấy giá trị từ request, nếu không có thì đặt là null
            $value = $request->has($key) ? $request->input($key) : null;
            
            // Nếu input rỗng, đặt giá trị là null
            if (empty($value)) {
                $value = null;
            }
            
            // Cập nhật cài đặt với giá trị mới (có thể là null)
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
            // Xóa logo cũ nếu tồn tại
            $oldLogo = Setting::getValue('site_logo');
            if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
                Storage::disk('public')->delete($oldLogo);
            }
            
            $logoPath = $request->file('site_logo')->store('logos', 'public');
            Setting::setValue('site_logo', $logoPath, 'general');
        }

        if ($request->hasFile('site_favicon')) {
            // Xóa favicon cũ nếu tồn tại
            $oldFavicon = Setting::getValue('site_favicon');
            if ($oldFavicon && Storage::disk('public')->exists($oldFavicon)) {
                Storage::disk('public')->delete($oldFavicon);
            }
            
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

        // SEO settings
        Setting::setValue('meta_keywords', 'kinh tế, việt nam, AI, chatbot', 'seo', 'string', 'Từ khóa Meta');
        Setting::setValue('meta_description', 'ChatBot Kinh Tế - Nguồn thông tin kinh tế Việt Nam', 'seo', 'string', 'Mô tả Meta');
        
        // Contact settings
        Setting::setValue('contact_address', 'Cần Thơ, Việt Nam', 'contact', 'string', 'Địa chỉ liên hệ');
        Setting::setValue('contact_phone', '(+84) 123 456 789', 'contact', 'string', 'Số điện thoại liên hệ');
        Setting::setValue('contact_email', 'contact@example.com', 'contact', 'string', 'Email liên hệ');
        Setting::setValue('business_hours', 'Thứ 2 - Thứ 6: 8h00 - 17h30', 'contact', 'string', 'Giờ làm việc');
        
        // Social settings
        Setting::setValue('facebook_url', 'https://facebook.com/', 'social', 'string', 'URL Facebook');
        Setting::setValue('twitter_url', 'https://twitter.com/', 'social', 'string', 'URL Twitter');
        Setting::setValue('instagram_url', 'https://instagram.com/', 'social', 'string', 'URL Instagram');
        Setting::setValue('youtube_url', 'https://youtube.com/', 'social', 'string', 'URL Youtube');
        Setting::setValue('linkedin_url', 'https://linkedin.com/', 'social', 'string', 'URL LinkedIn');

        return redirect()->route('admin.settings.index')
            ->with('success', 'Cấu hình mặc định đã được khởi tạo thành công.');
    }
}
