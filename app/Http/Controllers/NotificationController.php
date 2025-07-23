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
        $roleIds = $user->roles->pluck('id'); // assuming Spatie is used

    $notifications = Notification::whereHas('roles', function ($q) use ($roleIds) {
    $q->whereIn('roles.id', $roleIds);
})->with('roles')->get();

    return view('notification.twig', [
        'notifications' => $notifications,
    ]);
    }
}
