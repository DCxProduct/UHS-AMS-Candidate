<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\AuditLogs\AuditLogResource;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\CandidateEntranceStatisticResource;
use App\Filament\Admin\Resources\CandidateExitStatistics\CandidateExitStatisticResource;
use App\Filament\Admin\Resources\CandidateLists\CandidateListResource;
use App\Filament\Admin\Resources\ClosingDates\ClosingDateResource;
use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\CandidateSubmitPopupSettingResource;
use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ExchangeRates\ExchangeRateResource;
use App\Filament\Admin\Resources\ExitExamResults\ExitExamResultResource;
use App\Filament\Admin\Resources\MefInvoiceCodes\MefInvoiceCodeResource;
use App\Filament\Admin\Resources\MessagingSettings\MessagingSettingResource;
use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Filament\Admin\Resources\SystemUsers\SystemUserResource;
use App\Filament\Admin\Resources\WorkflowStageTypes\WorkflowStageTypeResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\CustomFormResource;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
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
            WorkflowStageTypeResource::class => [
                'view_any',
                'create',
                'update',
                'delete',
            ],
            ClosingDateResource::class => [
                'clear_data',
            ],
            PaymentResource::class => [
                'download_excel',
                'download_pdf',
                'clear_data',
            ],
            MefInvoiceCodeResource::class => [
                'import_excel',
                'clear_data',
            ],
            PaymentStatusListResource::class => [
                'pay',
                'download_excel',
                'download_pdf',
                'clear_data',
            ],
            AuditLogResource::class => [
                'clear_data',
            ],
            CandidateEntranceStatisticResource::class => [
                'passed',
                'edit_result',
                'pending',
                'bulk_passed',
                'bulk_pending',
                'download_excel',
                'clear_data',
            ],
            CandidateExitStatisticResource::class => [
                'passed',
                'edit_result',
                'pending',
                'bulk_passed',
                'bulk_pending',
                'download_excel',
                'clear_data',
            ],
            CustomFormEntryResource::class => [
                'view',
                'view_application_stage',
                'download_excel',
                'clear_data',
                'edit_review_note',
                'view_pdf',
                'accepted',
                'verify',
                'reject',
                'rejected',
                'download_pdf',
            ],
            CustomFormResource::class => [
                'edit_template',
            ],
            DocumentTemplateResource::class => [
                'preview_pdf',
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
        $can = self::can(self::permissionForResource($resourceClass, $action))
            || self::can(self::legacyPermissionForResource($resourceClass, $action));

        if ($resourceClass === CandidateEntranceStatisticResource::class) {
            $can = $can
                || self::can(self::permission($action, 'CandidateRequested'));
        }

        return $can;
    }

    public static function abortUnlessCan(string $permission): void
    {
        abort_unless(self::can($permission), 403);
    }

    public static function abortUnlessCanForResource(string $resourceClass, string $action): void
    {
        abort_unless(self::canForResource($resourceClass, $action), 403);
    }

    public static function canAccessWorkflowActions(string $resourceClass): bool
    {
        $user = auth()->user();
        if ($user === null || $user->registration_type === 'student') {
            return false;
        }

        return $resourceClass === CustomFormEntryResource::class
            ? CustomFormEntryResource::currentUserCanManageForms()
            : $resourceClass::canAccess();
    }

    public static function canForWorkflowStage(
        string $resourceClass,
        string $action,
        ?CustomFormEntry $entry,
        WorkflowStageType $stage,
        bool $allowFinishedFinalStage = false,
    ): bool {
        if (! self::canAccessWorkflowActions($resourceClass)) {
            return false;
        }

        if ($entry === null || ! WorkflowStageMessages::hasConfiguredWorkflow($entry)) {
            return self::canForResource($resourceClass, $action);
        }

        if (WorkflowStageMessages::canCurrentUserHandleStage($entry, $stage)) {
            return true;
        }

        // A workflow may end at Payment or Results without a separate notification stage.
        return $allowFinishedFinalStage
            && WorkflowStageProgression::finished($entry)
            && WorkflowStageProgression::finalStageType($entry) === $stage
            && WorkflowStageMessages::currentUserHasStageRole($entry, $stage);
    }

    public static function abortUnlessCanForWorkflowStage(
        string $resourceClass,
        string $action,
        ?CustomFormEntry $entry,
        WorkflowStageType $stage,
        bool $allowFinishedFinalStage = false,
    ): void {
        abort_unless(self::canForWorkflowStage($resourceClass, $action, $entry, $stage, $allowFinishedFinalStage), 403);
    }

    public static function sync(): void
    {
        try {
            $permissionTable = config('permission.table_names.permissions', 'permissions');

            if (! Schema::hasTable($permissionTable)) {
                return;
            }

            $guardName = config('auth.defaults.guard', 'web');

            // Retire this action's stored grants as well as its generated permission.
            foreach (Permission::query()->where('name', 'OriginalApplicationReceived:CustomFormEntry')->get() as $permission) {
                $permission->delete();
            }

            foreach (self::all() as $permission) {
                Permission::findOrCreate($permission, $guardName);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable) {

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
            PaymentStatusListResource::class => [
                'viewAny',
                'pay',
                'download_excel',
                'download_pdf',
                'clear_data',
            ],
            PaymentResource::class => [
                'viewAny',
                'update',
                'download_excel',
                'download_pdf',
                'clear_data',
            ],
            CandidateEntranceStatisticResource::class => [
                'viewAny',
                'passed',
                'edit_result',
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
                'preview_pdf',
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
            CandidateExitStatisticResource::class => [
                'viewAny',
                'passed',
                'edit_result',
                'pending',
                'bulk_passed',
                'bulk_pending',
                'download_excel',
                'clear_data',
            ],
            ExchangeRateResource::class => [
                'viewAny',
                'update',
            ],
            CandidateSubmitPopupSettingResource::class => [
                'viewAny',
                'create',
                'update',
                'delete',
            ],
            MessagingSettingResource::class => [
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

        if ($subject === 'CandidateEntranceStatistic') {
            $subject = 'ReviewApplication';
        }

        return self::permission($action, $subject);
    }
}
