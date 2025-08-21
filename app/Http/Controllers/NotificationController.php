<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Models\Notification;

class NotificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        $roleIds = $user->roles->pluck('id'); 

    $notifications = Notification::whereHas('roles', function ($query) use ($roleIds) {
    $query->whereIn('roles.id', $roleIds);
    })->latest()->get();

    return view('notifications', data: [
        'notifications' => $notifications,
    ]);
    }
}
