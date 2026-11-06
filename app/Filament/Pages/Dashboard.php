<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminMenuOverview;
use App\Filament\Widgets\AdminSidebarFormsTable;
use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\CandidateMenuOverview;
use App\Filament\Widgets\CandidateSidebarFormsTable;
use App\Filament\Widgets\CandidateStatsOverview;
use App\Filament\Widgets\CashierStatsOverview;
use App\Support\CandidateApplicationLevels;
use App\Support\CandidateDisplayName;
use App\Support\DashboardUserAccess;
use App\Support\UserTypeOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class Dashboard extends BaseDashboard
{
    protected static string $routePath = 'dashboard';

    protected static ?int $navigationSort = -100;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('switch_application_level')
                ->label(__('dashboard.application_levels.switch'))
                ->icon('heroicon-o-arrows-right-left')
                ->visible(fn (): bool => CandidateApplicationLevels::canSwitch(auth()->user()))
                ->modalHeading(__('dashboard.application_levels.switch'))
                ->modalDescription(__('dashboard.application_levels.description'))
                ->modalWidth('lg')
                ->extraModalWindowAttributes(['class' => 'uhs-level-switch-modal'])
                ->modalSubmitActionLabel(__('dashboard.application_levels.confirm'))
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema([
                    Placeholder::make('current_level')
                        ->hiddenLabel()
                        ->content(fn () => view('filament.dashboard.current-education-level', [
                            'level' => UserTypeOptions::formatLabel(CandidateApplicationLevels::currentType(auth()->user()) ?? 'candidate'),
                        ])),
                    Select::make('type')
                        ->label(__('dashboard.application_levels.field'))
                        ->placeholder(__('dashboard.application_levels.placeholder'))
                        ->helperText(__('dashboard.application_levels.history_note'))
                        ->options(fn (): array => CandidateApplicationLevels::switchOptions(auth()->user()))
                        ->searchable()
                        ->required()
                        ->validationMessages(['required' => __('dashboard.application_levels.required')])
                        ->rules(['string'])
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    try {
                        CandidateApplicationLevels::switchTo(auth()->user(), $data['type']);
                    } catch (ValidationException $exception) {
                        throw ValidationException::withMessages([
                            $this->getMountedActionSchema()->getStatePath().'.type' => Arr::flatten($exception->errors()),
                        ]);
                    }

                    Notification::make()->success()
                        ->title(__('dashboard.application_levels.switched', ['type' => UserTypeOptions::formatLabel($data['type'])]))
                        ->body(__('dashboard.application_levels.availability'))
                        ->send();
                    $this->redirect(static::getUrl(), navigate: false);
                }),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.dashboard');
    }

    public function getTitle(): string|Htmlable
    {
        return __('dashboard.title');
    }

    public function getHeading(): string|Htmlable
    {
        $user = auth()->user();

        return __('dashboard.welcome', [
            'name' => CandidateDisplayName::for($user),
        ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        if ($this->isAdminDashboardUser()) {
            return __('dashboard.admin_subheading');
        }

        if ($this->isCashierDashboardUser()) {
            return __('dashboard.cashier_subheading');
        }

        if ($this->isRegistrarDashboardUser()) {
            return __('dashboard.registrar_subheading');
        }

        if ($this->isStudentDashboardUser()) {
            return __('dashboard.student_subheading');
        }

        return __('dashboard.staff_subheading');
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 2,
        ];
    }

    public function getWidgets(): array
    {
        if ($this->isAdminDashboardUser()) {
            return [
                AdminStatsOverview::class,
                AdminMenuOverview::class,
                AdminSidebarFormsTable::class,
            ];
        }

        if ($this->isCashierDashboardUser()) {
            return [
                CashierStatsOverview::class,
                AdminMenuOverview::class,
            ];
        }

        if ($this->isRegistrarDashboardUser()) {
            return [
                AdminStatsOverview::class,
                AdminMenuOverview::class,
                AdminSidebarFormsTable::class,
            ];
        }

        if (! $this->isStudentDashboardUser()) {
            return [];
        }

        return [
            CandidateStatsOverview::class,
            CandidateMenuOverview::class,
            CandidateSidebarFormsTable::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->isAdminDashboardUser();
    }

    public function hasDashboardWidgets(): bool
    {
        return $this->getWidgets() !== [];
    }

    protected function isAdminDashboardUser(): bool
    {
        return DashboardUserAccess::isAdmin(auth()->user());
    }

    protected function isCashierDashboardUser(): bool
    {
        return DashboardUserAccess::isCashier(auth()->user());
    }

    protected function isRegistrarDashboardUser(): bool
    {
        return DashboardUserAccess::isRegistrar(auth()->user());
    }

    protected function isStudentDashboardUser(): bool
    {
        return DashboardUserAccess::isCandidate(auth()->user());
    }
}
