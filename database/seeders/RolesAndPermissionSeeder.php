<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Access Control Permissions
        Permission::create(['name' => 'view access control']);
        Permission::create(['name' => 'view faculty']);
        Permission::create(['name' => 'view student']); 



        // Users Permissions
        Permission::create(['name' => 'create users']);
        Permission::create(['name' => 'view users']);
        Permission::create(['name' => 'update users']);
        Permission::create(['name' => 'destroy users']);

        // Roles Permissions 
        Permission::create(['name' => 'create roles']);
        Permission::create(['name' => 'view roles']);
        Permission::create(['name' => 'update roles']);
        Permission::create(['name' => 'destroy roles']);
        Permission::create(['name' => 'assign roles']);

        // Banks Permissions
        Permission::create(['name' => 'create courses']);
        Permission::create(['name' => 'view courses']);
        Permission::create(['name' => 'update courses']);
        Permission::create(['name' => 'destroy courses']);

        Permission::create(['name' => 'create subjects']);
        Permission::create(['name' => 'view subjects']);
        Permission::create(['name' => 'update subjects']);
        Permission::create(['name' => 'destroy subjects']);

        Permission::create(['name' => 'create topics']);
        Permission::create(['name' => 'view topics']);
        Permission::create(['name' => 'update topics']);
        Permission::create(['name' => 'destroy topics']);

        Permission::create(['name' => 'create questions']);
        Permission::create(['name' => 'view questions']);
        Permission::create(['name' => 'update questions']);
        Permission::create(['name' => 'destroy questions']);

        Permission::create(['name' => 'create exams']);
        Permission::create(['name' => 'view exams']);
        Permission::create(['name' => 'update exams']);
        Permission::create(['name' => 'destroy exams']);

        Permission::create(['name' => 'create reviewers']);
        Permission::create(['name' => 'view reviewers']);
        Permission::create(['name' => 'update reviewers']);
        Permission::create(['name' => 'destroy reviewers']);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        $faculty_default_permissions = [
            'view users',
            'view faculty',
            'view subjects',
            'create topics','view topics', 'update topics', 'destroy topics',
            'create questions','view questions', 'update questions', 'destroy questions',
            'create reviewers','view reviewers', 'update reviewers', 'destroy reviewers',
        ];

        $head_default_permissions = array_merge($faculty_default_permissions, [
            'create exams','view exams', 'update exams', 'destroy exams',
            'create courses','view courses', 'update courses', 'destroy courses',
            'create subjects', 'update subjects', 'destroy subjects',
        ]);

        $admin_default_permissions = array_merge($head_default_permissions, [
            'view access control',
            'create users','view users', 'update users', 'destroy users',
            'create roles','view roles', 'update roles', 'destroy roles',
        ]);

        $student_default_permissions = [
            'view student'
        ];

        // Default Roles
        Role::create(['name' => 'faculty'])->givePermissionTo($faculty_default_permissions);
        Role::create(['name' => 'department_head'])->givePermissionTo($head_default_permissions);
        Role::create(['name' => 'head_secretary'])->givePermissionTo($admin_default_permissions);
        Role::create(['name' => 'college_dean'])->givePermissionTo($admin_default_permissions);
        Role::create(['name' => 'student'])->givePermissionTo($student_default_permissions);
        Role::create(['name' => 'super_admin'])->givePermissionTo(Permission::all());

    }
}
