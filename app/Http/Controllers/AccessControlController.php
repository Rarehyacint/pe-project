<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\User;
use App\Rules\NoAcademicYearCollisions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Validator;

class AccessControlController extends Controller
{

    public function indexUsers(){
        $currentUser = request()->user();
        $users = User::paginate(10);
        $header = ['ID', 'Name', 'email', 'Date Created'];
        $rows = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->getFullName(),
                'email' => $user->email,
                'Date Created' => Carbon::parse($user->created_at)->format('M d Y')
            ];
        });

        $data = [
            'models' => $users, 
            'currentUser' => $currentUser,
            'header' => $header,
            'rows' => $rows,
            'url' => 'users'
        ];

        return view('admins/access-control', $data);
    }

    public function redirect(){
        return redirect('/admins/users');
    }

    public function indexRoles(){
        $roles = Role::paginate(10);
        $header = ['ID', 'Name', 'Date Created'];
        $rows = $roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'Date Created' => Carbon::parse($role->created_at)->format('M d Y')
            ];
        });

        $data = [
            'models' => $roles, 
            'header' => $header,
            'rows' => $rows,
            'url' => 'roles'
        ];

        return view('admins/roles', $data);
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

        return redirect()->route('admin.roles.show', $role);
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

        return redirect()->route('admin.roles.show', $role);    
    }

    public function destroyRole(Role $role){
        // authorize

        $role->delete();

        return redirect()->route('admin.roles.index');
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

    public function indexAcademicYear(){
        $header = ['Academic Year Label', 'Start Date', 'End Date'];

        $academic_year = AcademicYear::orderBy('start_date', 'desc')
                        ->orderBy('end_date', 'desc')
                        ->get();
        $current_academic_year = AcademicYear::current();
        $rows = $academic_year->map(function ($year) {
            return [
                'id' => $year->id,
                'year_label' => $year->year_label,
                'start_date' => Carbon::parse($year->start_date)->format('M d Y'),
                'end_date' => Carbon::parse($year->end_date)->format('M d Y'),
                'is_current' => $year->isCurrent(),
                'is_locked' => $year->is_locked
            ];
        });

        $data = [
            'header' => $header,
            'rows' => $rows,
            'current_academic_year' => $current_academic_year
        ];
        return view('admins/academic-year', $data);
    }

    public function createAcademicYear(){
        return view('academic-year/create');
    }

    public function storeAcademicYear(){
        $validator = Validator::make(request()->post(), [
             'year_label' => [
                'required',
                'string',
                'max:9',
                Rule::unique('academic_years', 'year_label'),
            ],
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => [
                'required',
                'date',
                'after:start_date',
                new NoAcademicYearCollisions(request()->input('start_date')),
            ],
        ]);

        if ($validator->fails()) {
            return response()->view('academic-year/create', [
                'errors' => $validator->errors(),
                'old' => request()->all()]);
        }

        $validated = $validator->validate();

        $academic_year = AcademicYear::create($validated);

        session()->flash('toast', json_encode([
            'status' => 'Created!',
            'message' => 'Academic Year: ' . $academic_year->year_label,
            'type' => 'success'
        ]));
        return response('', 200)->header('HX-Redirect', route('admin.academic-year.index'));
    }

    public function editAcademicYear(AcademicYear $academic_year){
        $academic_year['is_current'] = $academic_year->isCurrent();
        return view('academic-year/edit', $academic_year);
    }

    public function updateAcademicYear(AcademicYear $academic_year){
        if ($academic_year->is_locked) {
            session()->flash('toast', json_encode([
                'status' => 'UPDATING NOT ALLOWED',
                'message' => 'Academic Year: ' . $academic_year->year_label,
                'type' => 'warning'
            ]));
            return response('', 200)->header('HX-Redirect', route('admin.academic-year.index'));
        }
        $validator = Validator::make(request()->post(), [
            'year_label' => [
                'required',
                'string',
                'max:9',
                Rule::unique('academic_years', 'year_label')->ignore($academic_year->id),
            ],
            'start_date' => 'required|date|after_or_equal:today|sometimes',
            'end_date' => [
                'required',
                'date',
                'after:start_date',
                new NoAcademicYearCollisions(request()->input('start_date'), $academic_year->id),
            ],
        ]);

        if ($validator->fails()) {
            return response()->view('academic-year/edit', [
                'id' => $academic_year->id,
                'errors' => $validator->errors(),
                'old' => request()->all()]
            );
        }

        $validated = $validator->validate();

        $academic_year->update($validated);

        session()->flash('toast', json_encode([
            'status' => 'Created!',
            'message' => 'Academic Year: ' . $academic_year->year_label,
            'type' => 'success'
        ]));
        return response('', 200)->header('HX-Redirect', route('admin.academic-year.index'));
    }


    public function destroyFormAcademicYear(AcademicYear $academic_year){
        return view('academic-year/destroy', $academic_year);
    }
     public function destroyAcademicYear(AcademicYear $academic_year){

        //  $this->authorize('delete', $academic_year);

        if ($academic_year->isCurrent() || $academic_year->is_locked) {
            session()->flash('toast', json_encode([
                'status' => 'DESTROYING NOT ALLOWED',
                'message' => 'Academic Year: ' . $academic_year->year_label,
                'type' => 'warning'
            ]));
            return response('', 200)->header('HX-Redirect', route('admin.academic-year.index'));
        }

        session()->flash('toast', json_encode([
            'status' => 'Destroyed!',
            'message' => 'Academic Year: ' . $academic_year->year_label,
            'type' => 'warning'
        ]));

        $academic_year->delete();

        return response('', 200)->header('HX-Redirect', route('admin.academic-year.index'));
    }
}
