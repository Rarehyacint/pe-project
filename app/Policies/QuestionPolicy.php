<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class QuestionPolicy
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
    public function view(User $user, Question $questions): bool
    {
        return $user->hasPermissionTo('view questions');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create questions');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Question $questions): bool
    {
        return $user->hasPermissionTo('update questions');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Question $questions): bool
    {
        return $user->hasPermissionTo('destroy questions');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Question $questions): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Question $questions): bool
    {
        return false;
    }
}
