<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class RegisteredUserController extends Controller
{

    public function show(User $user){
        $user_courses = $user->courses()->get()->pluck('abbreviation', 'id');
        $userPermissions = $user->permissions()->pluck('name');
        $userAllPermissions = $user->getAllPermissions()->pluck('name');
        $role_permissions = $user->getPermissionsviaRoles()->pluck('name');
        $userRoles = $user->getRoleNames();
        $permissions = Permission::all()->pluck('name')->diff($userAllPermissions);
        
        $data = [
            'user' => $user,
            'permissions' => $permissions,
            'user_courses' => $user_courses,
            'user_roles' => $userRoles,
            'user_permissions' => $userPermissions,
            'role_permissions' => $role_permissions
        ];

        return view('users/show', $data);
    }


    public function create(){
        $courses = Course::pluck('abbreviation', 'id');
        $roles = Role::pluck('name', 'id');
        $permissions = Permission::pluck('name', 'id');

        $data = [
            'roles' => $roles,
            'permissions' => $permissions,
            'courses' => $courses
        ];

        return view('users/create', $data);
    }

    public function store(){
        $user_courses = Course::findMany(request('courses'))->pluck('id');
        $user_roles = Role::findMany(request('roles'));
        $user_permissions = Permission::findMany(request('permissions'));

        $validator = Validator::make(request()->all(), [
            'first_name' => ['required'],
            'last_name' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', Password::min(6), 'confirmed'],
            'courses' => ['required'],
            'roles' =>['required']
        ]);

        if ($validator->fails()) {
            return redirect('admins/users/create')
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => $data['email'],
            'password'   => bcrypt($data['password']), 
        ]);

        $user->courses()->sync($user_courses);
        $user->syncPermissions($user_permissions);
        $user->syncRoles($user_roles);

        return redirect('/admins/load-users');
    }

    public function edit(User $user){
        $courses = Course::pluck('abbreviation', 'id');
        $user_courses = $user->courses()->get()->pluck('abbreviation', 'id');
        $roles = Role::pluck('name', 'id');
        $userRoles = $user->getRoleNames();
        $userAllPermissions = $user->getAllPermissions()->pluck('name');
        $userPermissions = $user->permissions()->pluck('name', 'id');
        $role_permissions = $user->getPermissionsviaRoles()->pluck('name');
        $permissions = Permission::all()->pluck('name', 'id')->diff($userAllPermissions);

        $data = [
            'user' => $user,
            'roles' => $roles,
            'permissions' => $permissions,
            'courses' => $courses,
            'user_courses' => $user_courses,
            'user_roles' => $userRoles,
            'user_permissions' => $userPermissions,
            'role_permissions' => $role_permissions
        ];

        return view('users/edit', $data);
    }

    public function update(User $user) {
        $user_courses = Course::findMany(request('courses'))->pluck('id');
        $user_roles = Role::findMany(request('roles'));
        $user_permissions = Permission::findMany(request('permissions'));

        $validator = Validator::make(request()->all(), [
            'first_name' => ['required'],
            'last_name' => ['required'],
            'email' => ['required', 'email'],
            'courses' => ['required'],
            'roles' =>['required']
        ]);
    
        if ($validator->fails()) {
            return redirect()->route('admin.users.edit', $user)
                ->withErrors($validator)
                ->withInput();
        }
    
        $data = $validator->validated();
    
        $updateData = [
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email' => $data['email'],
        ];
    
        $user->update($updateData);
        $user->courses()->sync($user_courses);
        $user->syncPermissions($user_permissions);
        $user->syncRoles($user_roles);

        return redirect()->route('admin.users.show', $user);
    }

    public function destroy(User $user){

        $this->authorize('delete', $user);

        $user->delete();

        return redirect('/admins/load-users');

    }
}
