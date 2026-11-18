<?php

namespace App\Models\Concerns;

use Illuminate\Validation\ValidationException;

/** Retain student identities, login credentials and application ownership. */
trait PreventsStudentAccountDeletion
{
    public function delete()
    {
        throw ValidationException::withMessages([
            'account' => __('new_student_accounts.notifications.deletion_blocked'),
        ]);
    }

    public function forceDelete()
    {
        return $this->delete();
    }

    public function forceDeleteQuietly()
    {
        return $this->delete();
    }
}
