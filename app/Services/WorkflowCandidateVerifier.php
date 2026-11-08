<?php

namespace App\Services;

use App\Enums\WorkflowStageType;
use App\Models\WorkflowNotification;
use App\Support\AuditLogger;
use App\Support\FilamentActionPermissions;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class WorkflowCandidateVerifier
{
    public function canVerify(CustomFormEntry $entry): bool
    {
        $entry->loadMissing('customForm');

        return $entry->exists
            && $entry->customForm?->is_active
            && ! $entry->customForm->isProfileForm()
            && $this->isChecked($entry)
            && (WorkflowStageMessages::hasConfiguredWorkflow($entry)
                ? (WorkflowStageProgression::isCurrent($entry, WorkflowStageType::Verification)
                    && (bool) data_get($entry->data, 'workflow_progress.reviewed', false))
                : blank(data_get($entry->data, 'application_received_at')));
    }

    /** Registrar receipt confirmation and ID issuance share one application lock and transaction. */
    public function verify(CustomFormEntry $entry): ?string
    {
        return DB::transaction(function () use ($entry): ?string {
            $current = CustomFormEntry::query()->lockForUpdate()->findOrFail($entry->getKey());
            abort_unless(CustomFormEntryResource::currentUserCanManageForms(), 403);
            Gate::authorize('view', $current);

            $workflow = WorkflowNotification::forEntry($current);
            if ($workflow === null) {
                FilamentActionPermissions::abortUnlessCanForResource(CustomFormEntryResource::class, 'verify');

                return $this->receiveStaticApplication($entry, $current);
            }

            abort_unless(WorkflowStageProgression::enabled($current)
                && $workflow->hasStageType(WorkflowStageType::Verification)
                && $this->isChecked($current), 409);
            abort_unless($this->hasResponsibleRole($current), 403);

            if (data_get($current->data, 'workflow_progress.verification_completed') === true
                && filled($current->candidate_id)) {
                $entry->refresh();

                return (string) $current->candidate_id;
            }

            abort_unless($this->canVerify($current), 409);
            $oldId = $current->candidate_id;
            $candidateId = filled($oldId) ? (string) $oldId : app(CandidateIdGenerator::class)->assignToEntry($current);
            if ($candidateId === null) {
                return null;
            }

            abort_unless(WorkflowStageProgression::verified($current), 409);
            $entry->refresh();

            AuditLogger::log(
                action: 'verified',
                auditable: $entry,
                oldValues: ['candidate_id' => $oldId, 'verification_completed' => false],
                newValues: ['candidate_id' => $candidateId, 'verification_completed' => true],
                description: 'Application receipt confirmed by registrar and candidate ID assigned',
                metadata: ['module' => 'Custom Form Entries', 'workflow_stage' => WorkflowStageType::Verification->value],
            );

            DB::afterCommit(fn () => WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Verification));

            return $candidateId;
        });
    }

    private function receiveStaticApplication(CustomFormEntry $entry, CustomFormEntry $current): ?string
    {
        $current->loadMissing('customForm');
        abort_unless($current->customForm?->is_active
            && ! $current->customForm->isProfileForm() && $this->isChecked($current), 409);

        if (filled(data_get($current->data, 'application_received_at')) && filled($current->candidate_id)) {
            $entry->refresh();

            return (string) $current->candidate_id;
        }

        $oldId = $current->candidate_id;
        $candidateId = filled($oldId) ? (string) $oldId : app(CandidateIdGenerator::class)->assignToEntry($current);
        if ($candidateId === null) {
            return null;
        }

        $data = is_array($current->data) ? $current->data : [];
        $data['application_received_at'] = now()->toIso8601String();
        $data['application_received_by'] = auth()->id();
        DB::table($current->getTable())->where($current->getKeyName(), $current->getKey())
            ->update(['data' => json_encode($data, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
        $entry->refresh();

        AuditLogger::log(
            action: 'verified',
            auditable: $entry,
            oldValues: ['candidate_id' => $oldId, 'application_received_at' => null],
            newValues: [
                'candidate_id' => $candidateId,
                'application_received_at' => $data['application_received_at'],
                'application_received_by' => $data['application_received_by'],
            ],
            description: 'Static application receipt confirmed and candidate ID assigned',
            metadata: ['module' => 'Custom Form Entries'],
        );

        return $candidateId;
    }

    private function isChecked(CustomFormEntry $entry): bool
    {
        $statuses = array_filter([
            strtolower(trim((string) $entry->review_status)),
            strtolower(trim((string) data_get($entry->data, 'registration_status'))),
        ], fn (string $status): bool => $status !== '');

        return $statuses !== [] && array_diff($statuses, ['approved', 'accepted', 'passed']) === [];
    }

    private function hasResponsibleRole(CustomFormEntry $entry): bool
    {
        $stage = collect(WorkflowNotification::forEntry($entry)?->stages ?? [])
            ->map(fn (array $stage): array => $stage['data'] ?? $stage)
            ->first(fn (array $stage): bool => ($stage['stage_type'] ?? null) === WorkflowStageType::Verification->value);
        $roles = WorkflowNotification::normalizeResponsibleRoles($stage['responsible_role'] ?? null);
        $user = auth()->user();

        return $user !== null && $roles !== [] && (
            (in_array('admin', array_map(strtolower(...), $roles), true) && $user->registration_type === 'admin')
            || (method_exists($user, 'hasEffectiveRole') && $user->hasEffectiveRole($roles))
        );
    }
}
