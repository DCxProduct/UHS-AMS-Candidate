<?php

namespace App\Support;

use App\Filament\Admin\Resources\AuditLogs\AuditLogResource;
use App\Filament\Admin\Resources\CandidateLists\CandidateListResource;
use App\Filament\Admin\Resources\CandidatePaymentLists\CandidatePaymentListResource;
use App\Filament\Admin\Resources\CandidateRequested\CandidateRequestedResource;
use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ExitExamResults\ExitExamResultResource;
use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Filament\Admin\Resources\SystemUsers\SystemUserResource;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class FilamentActionPermissions
{
    public static function all(): array
    {
        return array_values(array_unique(array_merge(
            self::permissionsForResource(PaymentResource::class, [
                'view_slip',
                'download_excel',
                'clear_data',
            ]),
            self::permissionsForResource(CandidatePaymentListResource::class, [
                'pay',
                'download_excel',
                'clear_data',
            ]),
            self::permissionsForResource(AuditLogResource::class, [
                'clear_data',
            ]),
            self::permissionsForResource(CandidateListResource::class, [
                'activate_account',
                'deactivate_account',
            ]),
            self::permissionsForResource(SystemUserResource::class, [
                'activate_account',
                'deactivate_account',
            ]),
            self::permissionsForResource(CandidateRequestedResource::class, [
                'passed',
                'pending',
                'bulk_passed',
                'bulk_pending',
                'download_excel',
                'clear_data',
            ]),
            self::permissionsForResource(ExamResultResource::class, [
                'notify_student',
                'notify_all_students',
                'download_excel',
                'clear_data',
            ]),
            self::permissionsForResource(ExitExamResultResource::class, [
                'notify_student',
                'notify_all_students',
                'download_excel',
                'clear_data',
            ]),
        )));
    }

    public static function permission(string $action, string $subject): string
    {
        $actionName = Str::of($action)
            ->replace(['-', '_'], ' ')
            ->title()
            ->replace(' ', '')
            ->toString();

        return "{$actionName}:{$subject}";
    }

    public static function permissionForResource(string $resourceClass, string $action): string
    {
        return self::permission($action, class_basename($resourceClass::getModel()));
    }

    public static function can(string $permission): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can($permission);
    }

    public static function canForResource(string $resourceClass, string $action): bool
    {
        return self::can(self::permissionForResource($resourceClass, $action));
    }

    public static function abortUnlessCan(string $permission): void
    {
        abort_unless(self::can($permission), 403);
    }

    public static function abortUnlessCanForResource(string $resourceClass, string $action): void
    {
        self::abortUnlessCan(self::permissionForResource($resourceClass, $action));
    }

    public static function sync(): void
    {
        try {
            $permissionTable = config('permission.table_names.permissions', 'permissions');

            if (! Schema::hasTable($permissionTable)) {
                return;
            }

            $guardName = config('auth.defaults.guard', 'web');

            foreach (self::all() as $permission) {
                Permission::findOrCreate($permission, $guardName);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable) {
            // Allow the panel to boot even if the database is temporarily unavailable.
        }
    }

    protected static function permissionsForResource(string $resourceClass, array $actions): array
    {
        return array_map(
            fn (string $action): string => self::permissionForResource($resourceClass, $action),
            $actions,
        );
    }
}
