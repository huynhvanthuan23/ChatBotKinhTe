<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-xl font-semibold mb-4">{{ __("Chào mừng, ") }} {{ Auth::user()->name }}!</h3>
                    <p>{{ __("Bạn đã đăng ký thành công tài khoản.") }}</p>
                </div>
            </div>
            
            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Thông tin cá nhân</h3>
                    
                    <div class="mb-4">
                        <strong>Họ và tên:</strong> {{ Auth::user()->name }}
                    </div>
                    
                    <div class="mb-4">
                        <strong>Email:</strong> {{ Auth::user()->email }}
                    </div>
                    
                    <div class="mb-4">
                        <strong>Số điện thoại:</strong> {{ Auth::user()->phone }}
                    </div>
                    
                    <div class="mt-6">
                        <a href="{{ route('profile.edit') }}" class="text-indigo-600 hover:text-indigo-900">
                            Chỉnh sửa thông tin cá nhân
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
