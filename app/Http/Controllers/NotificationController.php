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
        //Para malaman anong role_id eto irrun yung may pluck
        // dd(Auth::user()->roles->pluck('id'));

        // dd($user->roles);
        
        $roleIds = $user->roles->pluck('id'); 

    //OLD
    // $notifications = Notification::whereHas('roles', function ($query) use ($roleIds) {
    //     $query->whereIn('roles.id', $roleIds);
    // })->latest()->get();

    //Test2
    // $isSuperadmin = $user->roles->contains('name', 'superadmin');

    // $notifications = Notification::when(!$isSuperadmin, function ($query) use ($roleIds) {
    //     $query->where(function ($q) use ($roleIds) {
    //         $q->whereHas('roles', function ($q2) use ($roleIds) {
    //             $q2->whereIn('roles.id', $roleIds);
    //         })->orWhereDoesntHave('roles'); // Public notifications
    //     });
    // })->latest()->get();

    //Test3 same code sa old
    $notifications = Notification::whereHas('roles', function ($query) use ($roleIds) {
    $query->whereIn('roles.id', $roleIds);
    })->latest()->get();
    // dd($notifications);



    // dd($notifications);

    return view('notifications', data: [
        'notifications' => $notifications,
    ]);
    }
}
