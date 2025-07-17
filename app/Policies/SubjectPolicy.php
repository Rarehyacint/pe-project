<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SubjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Subject $subjects): bool
    {
        return $user->hasPermissionTo('view subjects');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create subjects');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Subject $subjects): bool
    {
        return $user->hasPermissionTo('update subjects');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Subject $subjects): bool
    {
        return $user->hasPermissionTo('destroy subjects');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Subject $subjects): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Subject $subjects): bool
    {
        return false;
    }
}
