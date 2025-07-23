<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Notification;
use Spatie\Permission\Models\Role;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         // Make sure the role exists FIRST
            $superadminRole = Role::firstOrCreate(['name' => 'superadmin']);

        // Create notification
            $notif = Notification::create([
        'title' => 'Hello Superadmin',
        'message' => 'This is a test notification for the superadmin role.',
    ]);

    // Attach role
    $notif->roles()->attach($superadminRole->id);
    }
    
}
