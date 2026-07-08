<?php

namespace Chanthoeun\FilamentCustomForms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFormAuthorization extends Model
{
    use HasFactory;

    protected $table = 'custom_form_authorizations';

    protected $fillable = [
        'custom_form_id',
        'panel',
        'allowed_users',
        'allowed_roles',
        'isolate_user_data',
        'permissions',
    ];

    protected $casts = [
        'allowed_users' => 'array',
        'allowed_roles' => 'array',
        'isolate_user_data' => 'boolean',
        'permissions' => 'array',
    ];

    public function customForm(): BelongsTo
    {
        return $this->belongsTo(CustomForm::class, 'custom_form_id');
    }

    public static function checkPermission(\Illuminate\Contracts\Auth\Authenticatable $user, string $permission, ?int $formId = null, ?CustomFormEntry $entry = null): ?bool
    {
        if (! $formId && $entry) {
            $formId = $entry->custom_form_id;
        }

        if (! $formId) {
            $formId = request()->input('tableFilters.custom_form_id.value')
                ?? data_get(request()->query('tableFilters'), 'custom_form_id.value')
                ?? request()->query('form_id')
                ?? request()->input('custom_form_id');
        }

        if (! $formId) {
            return null;
        }

        $userType = strtolower((string) ($user->registration_type ?? ''));

        $authorizations = self::where('custom_form_id', $formId)
            ->where('panel', $userType)
            ->get();

        if ($authorizations->isEmpty()) {
            return null;
        }

        foreach ($authorizations as $auth) {
            $userAllowed = true;
            if (! empty($auth->allowed_users)) {
                $userAllowed = in_array((string) $user->id, array_map('strval', $auth->allowed_users), true);
            }

            $roleAllowed = true;
            if (! empty($auth->allowed_roles)) {
                $userRoles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [];
                $roleAllowed = ! empty(array_intersect($userRoles, $auth->allowed_roles));
            }

            if ($userAllowed && $roleAllowed) {
                if ($auth->isolate_user_data && $entry) {
                    $ownerColumn = 'created_by';
                    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('custom_form_entries');
                    foreach (['created_by', 'user_id', 'created_by_id'] as $col) {
                        if (in_array($col, $columns, true)) {
                            $ownerColumn = $col;
                            break;
                        }
                    }
                    if ((string) $entry->$ownerColumn !== (string) $user->id) {
                        return false;
                    }
                }

                $perms = $auth->permissions ?? [];
                if (in_array($permission, $perms, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
