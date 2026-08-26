<?php

namespace App\Support;

use App\Filament\Admin\Resources\AuditLogs\AuditLogResource;
use App\Filament\Admin\Resources\CandidateLists\CandidateListResource;
use App\Filament\Admin\Resources\CandidatePaymentLists\CandidatePaymentListResource;
use App\Filament\Admin\Resources\CandidateRequested\CandidateRequestedResource;
use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\CandidateSubmitPopupSettingResource;
use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ExchangeRates\ExchangeRateResource;
use App\Filament\Admin\Resources\ExitExamResults\ExitExamResultResource;
use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Filament\Admin\Resources\SystemUsers\SystemUserResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\CustomFormResource;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class FilamentActionPermissions
{
    public static function resourceActions(): array
    {
        return [
            PaymentResource::class => [
                'view_slip',
                'download_excel',
                'clear_data',
            ],
            CandidatePaymentListResource::class => [
                'pay',
                'download_excel',
                'clear_data',
            ],
            AuditLogResource::class => [
                'clear_data',
            ],
            CandidateRequestedResource::class => [
                'passed',
                'pending',
                'bulk_passed',
                'bulk_pending',
                'download_excel',
                'clear_data',
            ],
            CustomFormEntryResource::class => [
                'download_excel',
                'clear_data',
                'edit_review_note',
                'view_pdf',
                'accepted',
                'rejected',
                'download_pdf',
            ],
            CustomFormResource::class => [
                'edit_template',
            ],
            DocumentTemplateResource::class => [
            ],
            ExamResultResource::class => [
                'notify_student',
                'notify_all_students',
                'download_excel',
                'clear_data',
            ],
            ExitExamResultResource::class => [
                'notify_student',
                'notify_all_students',
                'download_excel',
                'clear_data',
            ],
        ];
    }

    public static function resourcePolicyMethods(): array
    {
        return collect(self::resourcePermissions())
            ->map(fn (array $permissions): array => collect($permissions)
                ->map(fn (string $permission): string => Str::camel($permission))
                ->unique()
                ->values()
                ->all())
            ->toArray();
    }

    public static function all(): array
    {
        return collect(self::resourceActions())
            ->flatMap(
                fn (array $actions, string $resourceClass): array => self::permissionsForResource($resourceClass, $actions)
            )
            ->unique()
            ->values()
            ->all();
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
        return self::can(self::permissionForResource($resourceClass, $action))
            || self::can(self::legacyPermissionForResource($resourceClass, $action));
    }

    public static function abortUnlessCan(string $permission): void
    {
        abort_unless(self::can($permission), 403);
    }

    public static function abortUnlessCanForResource(string $resourceClass, string $action): void
    {
        abort_unless(self::canForResource($resourceClass, $action), 403);
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

    protected static function resourcePermissions(): array
    {
        return [
            CandidatePaymentListResource::class => [
                'viewAny',
                'pay',
                'download_excel',
                'clear_data',
            ],
            PaymentResource::class => [
                'viewAny',
                'update',
                'view_slip',
                'download_excel',
                'clear_data',
            ],
            CandidateRequestedResource::class => [
                'viewAny',
                'passed',
                'pending',
                'bulk_passed',
                'bulk_pending',
                'download_excel',
                'clear_data',
            ],
            ExamResultResource::class => [
                'viewAny',
                'notify_student',
                'notify_all_students',
                'download_excel',
                'clear_data',
            ],
            ExitExamResultResource::class => [
                'viewAny',
                'notify_student',
                'notify_all_students',
                'download_excel',
                'clear_data',
            ],
            DocumentTemplateResource::class => [
                'viewAny',
                'update',
                'delete',
            ],
            SystemUserResource::class => [
                'viewAny',
                'create',
                'update',
                'delete',
            ],
            CandidateListResource::class => [
                'viewAny',
                'create',
                'update',
                'delete',
            ],
            ExchangeRateResource::class => [
                'viewAny',
                'update',
            ],
            CandidateSubmitPopupSettingResource::class => [
                'viewAny',
                'update',
            ],
            AuditLogResource::class => [
                'viewAny',
                'clear_data',
            ],
        ] + collect(self::resourceActions())
            ->map(fn (array $actions, string $resource): array => array_merge(['viewAny', 'create', 'update', 'delete'], $actions))
            ->toArray();
    }

    protected static function legacyPermissionForResource(string $resourceClass, string $action): string
    {
        $subject = class_basename($resourceClass::getModel());

        if ($subject === 'CandidateRequested') {
            $subject = 'ReviewApplication';
        }

        return self::permission($action, $subject);
    }
}
