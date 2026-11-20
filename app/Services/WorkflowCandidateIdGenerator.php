<?php

namespace App\Services;

use App\Enums\WorkflowStageType;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\AuditLogger;
use App\Support\CustomFormRoleAccess;
use App\Support\WorkflowButtons;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Models\CustomFormAuthorization;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Issue an ID only at the application's explicitly configured generation checkpoint. */
final class WorkflowCandidateIdGenerator
{
    public function canGenerate(CustomFormEntry $entry): bool
    {
        $entry->loadMissing('customForm');

        return $entry->exists
            && $this->canView($entry)
            && $entry->customForm?->is_active
            && ! $entry->customForm->isProfileForm()
            && WorkflowStageProgression::enabled($entry)
            && WorkflowStageProgression::isCurrent($entry, WorkflowStageType::CandidateIdGeneration)
            && WorkflowButtons::allows($entry, 'generate_candidate_id')
            && $this->hasResponsibleRole($entry);
    }

    public function generate(CustomFormEntry $entry): ?string
    {
        return DB::transaction(function () use ($entry): ?string {
            $current = CustomFormEntry::query()->lockForUpdate()->findOrFail($entry->getKey());
            abort_unless($this->canView($current), 403);
            $current->loadMissing('customForm');
            $workflow = WorkflowNotification::forEntry($current);
            abort_unless($current->customForm?->is_active
                && ! $current->customForm->isProfileForm()
                && WorkflowStageProgression::enabled($current)
                && $workflow?->hasStageType(WorkflowStageType::CandidateIdGeneration), 409);
            abort_unless(WorkflowButtons::allows($current, 'generate_candidate_id')
                && $this->hasResponsibleRole($current), 403);
            abort_if($this->hasBlockedStatus($current), 409);

            if (data_get($current->data, 'workflow_progress.candidate_id_generated') === true
                && filled($current->candidate_id)) {
                $entry->refresh();

                return (string) $current->candidate_id;
            }

            abort_unless($this->canGenerate($current), 409);
            $oldId = $current->candidate_id;
            $candidateId = filled($oldId) ? (string) $oldId : app(CandidateIdGenerator::class)->assignToEntry($current);
            if ($candidateId === null) {
                return null;
            }

            abort_unless(WorkflowStageProgression::candidateIdGenerated($current), 409);
            $entry->refresh();

            AuditLogger::log(
                action: 'candidate_id_generated',
                auditable: $entry,
                oldValues: ['candidate_id' => $oldId, 'candidate_id_generated' => false],
                newValues: ['candidate_id' => $candidateId, 'candidate_id_generated' => true],
                description: 'Candidate ID issued at its configured workflow checkpoint',
                metadata: ['module' => 'Custom Form Entries', 'workflow_stage' => WorkflowStageType::CandidateIdGeneration->value],
            );
            DB::afterCommit(fn () => WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::CandidateIdGeneration));

            return $candidateId;
        });
    }

    private function canView(CustomFormEntry $entry): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->is_active || $user->registration_type === 'student'
            || CustomFormRoleAccess::canAccessForm($user, $entry->custom_form_id) === false) {
            return false;
        }
        $formView = CustomFormAuthorization::checkPermission($user, 'view', (int) $entry->custom_form_id, $entry);

        return $formView !== false && ($formView === true || Gate::forUser($user)->allows('view', $entry));
    }

    private function hasBlockedStatus(CustomFormEntry $entry): bool
    {
        foreach ([$entry->review_status, data_get($entry->data, 'registration_status')] as $status) {
            if (in_array(strtolower(trim((string) $status)), ['draft', 'rejected', 'failed'], true)) {
                return true;
            }
        }

        return false;
    }

    private function hasResponsibleRole(CustomFormEntry $entry): bool
    {
        $stage = collect(WorkflowNotification::forEntry($entry)?->stages ?? [])
            ->map(fn (array $stage): array => $stage['data'] ?? $stage)
            ->first(fn (array $stage): bool => ($stage['stage_type'] ?? null) === WorkflowStageType::CandidateIdGeneration->value);
        $roles = WorkflowNotification::normalizeResponsibleRoles($stage['responsible_role'] ?? null);
        $user = auth()->user();

        return $user instanceof User && $roles !== [] && $user->hasEffectiveRole($roles);
    }
}
