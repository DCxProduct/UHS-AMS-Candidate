<?php

namespace App\Filament\Widgets;

use App\Support\DashboardMetrics;
use App\Support\DashboardUserAccess;
use App\Support\UserTypeOptions;
use Filament\Widgets\Widget;

class CandidateMenuOverview extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.admin-menu-overview';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return DashboardUserAccess::isCandidate(auth()->user());
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $userId = (int) auth()->id();
        $availableFormsCount = count(DashboardMetrics::studentAvailableForms($userId));
        $applicationCount = DashboardMetrics::studentSubmittedApplicationsCount($userId);

        return [
            'eyebrow' => $this->candidateRoleLabel(),
            'title' => __('dashboard.welcome', [
                'name' => $user?->name ?: $user?->username ?: __('dashboard.user'),
            ]),
            'description' => __('dashboard.candidate_overview_description'),
            'highlights' => [
                [
                    'label' => __('dashboard.available_forms'),
                    'value' => number_format($availableFormsCount),
                ],
                [
                    'label' => __('dashboard.application_count'),
                    'value' => number_format($applicationCount),
                ],
            ],
            'items' => [],
        ];
    }

    private function candidateRoleLabel(): string
    {
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'effectiveRoleNames')) {
            return UserTypeOptions::formatLabel('candidate');
        }

        $role = $user->effectiveRoleNames()
            ->map(fn (string $role): string => strtolower(trim($role)))
            ->first(fn (string $role): bool => UserTypeOptions::isCandidateManagedRole($role) && ! in_array($role, ['candidate', 'student'], true));

        return $role
            ? UserTypeOptions::formatLabel($role)
            : UserTypeOptions::formatLabel('candidate');
    }

}
