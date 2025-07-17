<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Validator;

class AccessControlController extends Controller
{

    public function index(){
        return view('admins/access-control');
    }

    public function redirect(){
        return redirect('/admins/access-control');
    }

    public function viewUsers(){
        $currentUser = request()->user();
        $users = User::all();
        $header = ['ID', 'Name', 'email', 'Date Created'];
        $rows = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->getFullName(),
                'email' => $user->email,
                'Date Created' => Carbon::parse($user->created_at)->format('m/d/Y')
            ];
        });

        $data = [
            'users' => $users, 
            'currentUser' => $currentUser,
            'header' => $header,
            'rows' => $rows,
            'url' => 'users',
            'paginates' => $users
        ];

        return view('/admins/load-table', $data);
    }

    public function viewRoles(){
        $roles = Role::all();
        $header = ['ID', 'Name', 'Date Created'];
        $rows = $roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'Date Created' => Carbon::parse($role->created_at)->format('m/d/Y')
            ];
        });

        $data = [
            'roles' => $roles, 
            'header' => $header,
            'rows' => $rows,
            'url' => 'roles',
            'paginates' => $roles
        ];

        return view('/admins/load-table', $data);
    }

    public function showRole(Role $role){
        $rolePermissions = $role->permissions;
        $permissions = Permission::all()->diff($rolePermissions);
        $data = [
            'role' => $role,
            'permissions' => $permissions,
            'role_permissions' => $rolePermissions
        ];

        return view('/roles/show', $data);
    }

    public function createRole(){
        $permissions = Permission::pluck('name', 'id');

        $data = [
            'permissions' => $permissions
        ];

        return view('/roles/create', $data);
    }

    public function storeRole(){
        $role_permissions = Permission::findMany(request('permissions'));
        $role = Role::create(['name' => request('name')]);

        $role->syncPermissions($role_permissions);

        return redirect('/admins/load-roles');
    }

    public function editRole(Role $role){
        $role_permissions = $role->permissions()->pluck('name', 'id');
        $permissions = Permission::all()->pluck('name', 'id')->diff($role_permissions);

        $data = [
            'role' => $role,
            'role_permissions' => $role_permissions,
            'permissions' => $permissions,
        ];

        return view('roles/edit', $data);
    }

    public function updateRole(Role $role) {
        $validator = Validator::make(request()->all(), [
            'name' => ['required'],
        ]);
    
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
    
        $data = $validator->validated();
    
        $role->update([
            'name' => $data['name'],
        ]);

        $role_permissions = Permission::findMany(request('permissions'));

        $role->syncPermissions($role_permissions);

        return redirect()->route('admin.roles.show', $role)
        ->with('success', 'User updated successfully.');    
    }

    public function destroyRole(Role $role){
        // authorize

        $role->delete();

        return redirect('/admins/load-roles');

    }

    public function viewPermissions(){
        $permissions = Permission::all();
        $header = ['ID', 'Name', 'Date Created'];
        $rows = $permissions->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'Date Created' => Carbon::parse($permission->created_at)->format('m/d/Y')
            ];
        });

        $data = [
            'permissions' => $permissions, 
            'header' => $header,
            'rows' => $rows,
            'url' => 'permissions'
        ];

        return view('admins/load-table', $data);
    }

    public function createPermission(){
        return view('permissions/create');
    }

    public function storePermission(){
        Permission::create(['name' => request('name')]);

        return redirect('/admins/load-permissions');
    }

    public function showPermission(Permission $permission){
        return view('/permissions/show', ['permission' => $permission]);
    }

    public function editPermission(Permission $permission){
        return view('permissions/edit', ['permission' => $permission]);
    }

    public function updatePermission(Permission $permission) {
        // Still need to authorize if the permission is within the CORE of the system (e.g., permission belongs to Model)
        $validator = Validator::make(request()->all(), [
            'name' => ['required'],
        ]);
    
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
    
        $data = $validator->validated();
    
        $permission->update([
            'name' => $data['name'],
        ]);

        return redirect()->route('admin.permissions.show', $permission)
        ->with('success', 'Permission updated successfully.');    
    }

    public function destroyPermission(Permission $permission){
        // authorize

        $permission->delete();

        return redirect('/admins/load-permissions');

    }

    public function loadRoleCheckbox(){
        
        $user_roles = request()->input('user_id') 
        ? User::findOrFail(request()->input('user_id'))->getRoleNames()
        : null;
    
        $search = request()->input('search');
        $roles = Role::where('name', 'like',  ['%' . strtolower($search) . '%'])->pluck('name', 'id');
        
        $data = [
            'roles' => $roles,
            'user_roles' => $user_roles
        ];

        return view('/roles/checkbox', $data);
    }
}
