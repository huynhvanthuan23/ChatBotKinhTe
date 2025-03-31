<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // Đếm số lượng user (không bao gồm admin)
        $usersCount = User::where('role', 'user')->count();
        
        return view('admin.dashboard', [
            'usersCount' => $usersCount,
        ]);
    }
}
