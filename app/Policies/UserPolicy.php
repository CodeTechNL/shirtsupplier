<?php

namespace App\Policies;

use App\Models\User;
use Filament\Support\Authorization\DenyResponse;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Super admins may only be deleted by other super admins.
     */
    public function delete(User $user, User $model): Response|bool
    {
        if (! $model->isSuperAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        return DenyResponse::make(
            'cannot_delete_super_admin',
            message: function (int $failureCount, int $totalCount): string {
                if ($failureCount === 1 && $totalCount === 1) {
                    return 'You cannot delete a super admin.';
                }

                if ($failureCount === $totalCount) {
                    return 'All selected users were super admins and cannot be deleted.';
                }

                if ($failureCount === 1) {
                    return 'One of the selected users was a super admin and cannot be deleted.';
                }

                return "{$failureCount} of the selected users were super admins and cannot be deleted.";
            },
        );
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * Super admins may only be force deleted by other super admins.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return ! $model->isSuperAdmin() || $user->isSuperAdmin();
    }
}
