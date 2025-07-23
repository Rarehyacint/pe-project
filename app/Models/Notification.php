<?php

namespace App\Models;

use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = ['title', 'message'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'notification_role', 'notification_id', 'role_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'notification')
                    ->withPivot('is_read')
                    ->withTimestamps();
    }
}
