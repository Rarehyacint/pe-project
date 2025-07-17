<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(class: RolesAndPermissionSeeder::class);
        $this->call(class: CourseSeeder::class);
        $this->call(class: SuperAdminSeeder::class);

        User::create([
            'first_name' => 'Faculty',
            'last_name' => 'One',
            'email' => 'faculty@email.com',
            'password' => bcrypt('testing1234'),
        ])->assignRole('faculty')->courses()->attach(1);

        User::create([
            'first_name' => 'Department',
            'last_name' => 'Head',
            'email' => 'deparmentHead@email.com',
            'password' => bcrypt('testing1234'),
        ])->assignRole('department_head')->courses()->attach(1);

        User::create([
            'first_name' => 'College',
            'last_name' => 'Dean',
            'email' => 'collegeDean@email.com',
            'password' => bcrypt('testing1234'),
        ])->assignRole('college_dean')->courses()->attach(1);

        User::create([
            'first_name' => 'Student',
            'last_name' => 'Example',
            'email' => 'student@email.com',
            'password' => bcrypt('testing1234'),
        ])->assignRole('student')->courses()->attach(1);

        $this->call(FakeDataSeeder::class);
    }
}
