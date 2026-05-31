<?php

namespace App\Filament\Student\Pages;

use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StudentDashboard extends BaseDashboard
{
    protected string $view = 'filament.student.pages.student-dashboard';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = 0;

    public static function getNavigationLabel(): string
    {
        return __('app.dashboard');
    }

    public function getTitle(): string
    {
        return __('app.dashboard');
    }

    public function getHeading(): string
    {
        return __('app.dashboard');
    }

    /*
    |--------------------------------------------------------------------------
    | Fix for Blade
    |--------------------------------------------------------------------------
    | Your Blade can call $this->dashboardData().
    */
    public function dashboardData(): array
    {
        return $this->getViewData();
    }

    /*
    |--------------------------------------------------------------------------
    | Data sent to Blade automatically
    |--------------------------------------------------------------------------
    */
    protected function getViewData(): array
    {
        $user = Auth::user();
        $userId = (int) ($user?->id ?? 0);

        $academicYear = $this->currentAcademicYear();

        $forms = $this->loadDynamicForms(
            userId: $userId,
            academicYear: $academicYear,
        );

        return [
            'studentUser' => [
                'id' => $userId,
                'name' => $user?->name ?: $user?->username ?: __('app.student'),
                'email' => $user?->email,
                'phone' => $user?->phone,
                'initial' => Str::of($user?->name ?: $user?->username ?: 'S')
                    ->trim()
                    ->substr(0, 1)
                    ->upper()
                    ->toString(),
            ],

            'academicYear' => $academicYear,

            'summary' => $this->summary($forms),

            'forms' => $forms,

            'monthly' => $this->monthlySubmissions($userId),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Load dynamic student forms
    |--------------------------------------------------------------------------
    | Reads active custom_forms from Admin DB.
    | Each form is checked against the current logged-in student.
    */
    protected function loadDynamicForms(int $userId, array $academicYear): array
    {
        if (! Schema::hasTable('custom_forms')) {
            return [];
        }

        $columns = Schema::getColumnListing('custom_forms');

        $activeColumn = $this->firstExistingColumn($columns, [
            'is_active',
            'active',
        ]);

        $sortColumn = $this->firstExistingColumn($columns, [
            'display_order',
            'sort',
            'sort_order',
            'order_column',
        ]);

        $query = DB::table('custom_forms');

        if ($activeColumn) {
            $query->where($activeColumn, true);
        }

        if ($sortColumn) {
            $query->orderBy($sortColumn);
        } else {
            $query->orderBy('id');
        }

        return $query
            ->get()
            ->filter(function (object $form): bool {
                /*
                |--------------------------------------------------------------------------
                | Optional role filter
                |--------------------------------------------------------------------------
                | If your custom_forms table has allowed_roles JSON,
                | only show forms allowed for Student.
                */
                if (isset($form->allowed_roles) && filled($form->allowed_roles)) {
                    $roles = json_decode((string) $form->allowed_roles, true);

                    if (is_array($roles)) {
                        return collect($roles)
                            ->map(fn ($role) => Str::lower((string) $role))
                            ->contains('student');
                    }
                }

                return true;
            })
            ->map(function (object $form) use ($userId, $academicYear): array {
                $formId = (int) $form->id;
                $slug = $this->formSlug($form);
                $formName = $this->translatedFormName($form);

                $closing = $this->formClosingStatus($formId);

                $entry = $this->studentFormEntryThisYear(
                    formId: $formId,
                    userId: $userId,
                    academicYearId: $academicYear['id'],
                    year: now()->year,
                );

                $submitted = (bool) $entry;

                $reviewStatusKey = $submitted
                    ? ($this->reviewStatusKey((int) $entry->id) ?: 'waiting_review')
                    : 'not_submitted';

                $canSubmit = ! $submitted && (($closing['status_key'] ?? 'open') === 'open');

                $submitLimitKey = $submitted
                    ? 'already_submitted'
                    : ($canSubmit ? 'can_submit' : 'cannot_submit');

                return [
                    'id' => $formId,
                    'slug' => $slug,
                    'name' => $formName,

                    'submission_limit' => $this->submissionLimit($form),

                    'period' => $closing['period'],
                    'form_status_key' => $closing['status_key'],
                    'form_status_label' => $closing['status_label'],

                    'can_submit' => $canSubmit,

                    'submitted' => $submitted,
                    'submitted_at' => $this->submittedDate($entry),

                    'submit_limit_key' => $submitLimitKey,
                    'submit_limit_label' => $this->submitLimitLabel($submitLimitKey),

                    'review_status_key' => $reviewStatusKey,
                    'review_status_label' => $this->reviewStatusLabel($reviewStatusKey),

                    'progress_percent' => $this->progressPercent(
                        submitted: $submitted,
                        reviewStatusKey: $reviewStatusKey,
                    ),

                    'next_step' => $this->nextStep(
                        formName: $formName,
                        formStatusKey: $closing['status_key'],
                        reviewStatusKey: $reviewStatusKey,
                        hasEntry: $submitted,
                    ),
                ];
            })
            ->values()
            ->all();
    }

    protected function currentAcademicYear(): array
    {
        if (! Schema::hasTable('academic_years')) {
            return [
                'id' => null,
                'label' => (string) now()->year,
            ];
        }

        $columns = Schema::getColumnListing('academic_years');

        $nameColumn = $this->firstExistingColumn($columns, [
            'name',
            'title',
            'academic_year',
            'year',
            'code',
        ]);

        $query = DB::table('academic_years');

        if (in_array('is_active', $columns, true)) {
            $query->where('is_active', true);
        }

        if (in_array('status', $columns, true)) {
            $query->orderByRaw("CASE WHEN LOWER(status) IN ('active', 'current') THEN 0 ELSE 1 END");
        }

        $record = $query
            ->orderByDesc('id')
            ->first();

        if (! $record) {
            return [
                'id' => null,
                'label' => (string) now()->year,
            ];
        }

        return [
            'id' => $record->id ?? null,
            'label' => $nameColumn
                ? (string) $record->{$nameColumn}
                : (string) now()->year,
        ];
    }

    protected function studentFormEntryThisYear(
        int $formId,
        int $userId,
        mixed $academicYearId,
        int $year,
    ): ?object {
        if (! Schema::hasTable('custom_form_entries')) {
            return null;
        }

        $columns = Schema::getColumnListing('custom_form_entries');

        $formColumn = $this->firstExistingColumn($columns, [
            'custom_form_id',
            'form_id',
        ]);

        $userColumn = $this->firstExistingColumn($columns, [
            'created_by',
            'user_id',
            'student_id',
        ]);

        if (! $formColumn || ! $userColumn || $userId <= 0) {
            return null;
        }

        $query = DB::table('custom_form_entries')
            ->where($formColumn, $formId)
            ->where($userColumn, $userId);

        if ($academicYearId && in_array('academic_year_id', $columns, true)) {
            $query->where('academic_year_id', $academicYearId);
        } elseif (in_array('created_at', $columns, true)) {
            $query->whereYear('created_at', $year);
        }

        return $query
            ->orderByDesc('id')
            ->first();
    }

    protected function formClosingStatus(int $formId): array
    {
        if (! Schema::hasTable('closing_dates')) {
            return [
                'status_key' => 'open',
                'status_label' => __('app.open'),
                'period' => __('app.no_closing_date'),
            ];
        }

        $columns = Schema::getColumnListing('closing_dates');

        if (! in_array('type', $columns, true)) {
            return [
                'status_key' => 'open',
                'status_label' => __('app.open'),
                'period' => __('app.no_closing_date'),
            ];
        }

        $query = DB::table('closing_dates')
            ->where('type', 'custom_form:' . $formId);

        if (in_array('deleted_at', $columns, true)) {
            $query->whereNull('deleted_at');
        }

        if (in_array('is_active', $columns, true)) {
            $query->where('is_active', true);
        }

        $record = $query
            ->orderByDesc('id')
            ->first();

        if (! $record) {
            return [
                'status_key' => 'open',
                'status_label' => __('app.open'),
                'period' => __('app.no_closing_date'),
            ];
        }

        $startDate = $record->start_date ?? null;
        $endDate = $record->end_date ?? null;

        $start = filled($startDate)
            ? Carbon::parse($startDate)->startOfDay()
            : null;

        $end = filled($endDate)
            ? Carbon::parse($endDate)->endOfDay()
            : null;

        $today = now()->startOfDay();

        $period = $this->periodLabel($start, $end);

        if ($start && $today->lt($start)) {
            return [
                'status_key' => 'not_open_yet',
                'status_label' => __('app.not_open_yet'),
                'period' => $period,
            ];
        }

        if ($end && $today->gt($end)) {
            return [
                'status_key' => 'expired',
                'status_label' => __('app.closed'),
                'period' => $period,
            ];
        }

        return [
            'status_key' => 'open',
            'status_label' => __('app.open'),
            'period' => $period,
        ];
    }

    protected function reviewStatusKey(int $entryId): ?string
    {
        if (
            ! Schema::hasTable('student_application_reviews')
            || ! Schema::hasColumn('student_application_reviews', 'custom_form_entry_id')
            || ! Schema::hasColumn('student_application_reviews', 'status')
        ) {
            return null;
        }

        $status = DB::table('student_application_reviews')
            ->where('custom_form_entry_id', $entryId)
            ->orderByDesc('id')
            ->value('status');

        if (! $status) {
            return null;
        }

        $normalized = Str::of((string) $status)
            ->lower()
            ->replace([' ', '-'], '_')
            ->toString();

        return match ($normalized) {
            'pending',
            'waiting',
            'waiting_review',
            'wait_review',
            'submitted',
            'under_review' => 'waiting_review',

            'approved',
            'accepted',
            'pass',
            'passed' => 'approved',

            'rejected',
            'declined',
            'failed',
            'fail' => 'rejected',

            'need_correction',
            'needs_correction',
            'correction',
            'returned',
            'return_to_student' => 'need_correction',

            default => 'waiting_review',
        };
    }

    protected function reviewStatusLabel(string $key): string
    {
        return match ($key) {
            'waiting_review' => __('app.waiting_review'),
            'approved' => __('app.approved'),
            'rejected' => __('app.rejected'),
            'need_correction' => __('app.need_correction'),
            default => __('app.not_submitted'),
        };
    }

    protected function submitLimitLabel(string $key): string
    {
        return match ($key) {
            'can_submit' => __('app.can_submit'),
            'already_submitted' => __('app.already_submitted'),
            default => __('app.cannot_submit'),
        };
    }

    protected function progressPercent(bool $submitted, string $reviewStatusKey): int
    {
        if (! $submitted) {
            return 0;
        }

        return match ($reviewStatusKey) {
            'waiting_review' => 60,
            'need_correction' => 75,
            'approved',
            'rejected' => 100,
            default => 50,
        };
    }

    protected function nextStep(
        string $formName,
        string $formStatusKey,
        string $reviewStatusKey,
        bool $hasEntry,
    ): array {
        if ($formStatusKey === 'not_open_yet') {
            return [
                'type' => 'warning',
                'icon' => '⏳',
                'title' => __('app.form_not_open_yet_title', ['name' => $formName]),
                'message' => __('app.form_not_open_yet_message'),
            ];
        }

        if (! $hasEntry && $formStatusKey === 'expired') {
            return [
                'type' => 'danger',
                'icon' => '!',
                'title' => __('app.form_closed_title', ['name' => $formName]),
                'message' => __('app.form_closed_message'),
            ];
        }

        if (! $hasEntry) {
            return [
                'type' => 'warning',
                'icon' => '✍',
                'title' => __('app.form_not_submitted_title', ['name' => $formName]),
                'message' => __('app.form_not_submitted_message'),
            ];
        }

        return match ($reviewStatusKey) {
            'approved' => [
                'type' => 'success',
                'icon' => '✓',
                'title' => __('app.form_approved_title', ['name' => $formName]),
                'message' => __('app.form_approved_message'),
            ],

            'rejected' => [
                'type' => 'danger',
                'icon' => '×',
                'title' => __('app.form_rejected_title', ['name' => $formName]),
                'message' => __('app.form_rejected_message'),
            ],

            'need_correction' => [
                'type' => 'warning',
                'icon' => '!',
                'title' => __('app.form_correction_title', ['name' => $formName]),
                'message' => __('app.form_correction_message'),
            ],

            default => [
                'type' => 'warning',
                'icon' => '⏳',
                'title' => __('app.form_waiting_review_title', ['name' => $formName]),
                'message' => __('app.form_waiting_review_message'),
            ],
        };
    }

    protected function summary(array $forms): array
    {
        $total = count($forms);

        $submitted = collect($forms)
            ->where('submitted', true)
            ->count();

        $available = collect($forms)
            ->where('can_submit', true)
            ->count();

        $waiting = collect($forms)
            ->where('review_status_key', 'waiting_review')
            ->count();

        $approved = collect($forms)
            ->where('review_status_key', 'approved')
            ->count();

        $progress = $total > 0
            ? (int) round(($submitted / $total) * 100)
            : 0;

        return [
            'total_forms' => $total,
            'submitted_forms' => $submitted,
            'available_forms' => $available,
            'waiting_review' => $waiting,
            'approved_forms' => $approved,
            'overall_progress' => $progress,
        ];
    }

    protected function monthlySubmissions(int $userId): array
    {
        $months = collect(range(5, 0))
            ->mapWithKeys(function (int $index): array {
                $date = now()->subMonths($index);

                return [
                    $date->format('Y-m') => [
                        'key' => $date->format('Y-m'),
                        'label' => $date->format('m/Y'),
                        'count' => 0,
                        'percent' => 0,
                    ],
                ];
            })
            ->all();

        if (! Schema::hasTable('custom_form_entries')) {
            return array_values($months);
        }

        $columns = Schema::getColumnListing('custom_form_entries');

        $userColumn = $this->firstExistingColumn($columns, [
            'created_by',
            'user_id',
            'student_id',
        ]);

        if (
            ! $userColumn
            || ! in_array('created_at', $columns, true)
            || $userId <= 0
        ) {
            return array_values($months);
        }

        $entries = DB::table('custom_form_entries')
            ->where($userColumn, $userId)
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->get(['created_at']);

        foreach ($entries as $entry) {
            $key = Carbon::parse($entry->created_at)->format('Y-m');

            if (isset($months[$key])) {
                $months[$key]['count']++;
            }
        }

        $max = max(collect($months)->pluck('count')->max() ?: 1, 1);

        foreach ($months as $key => $month) {
            $months[$key]['percent'] = (int) round(($month['count'] / $max) * 100);
        }

        return array_values($months);
    }

    protected function periodLabel(?Carbon $start, ?Carbon $end): string
    {
        if (! $start && ! $end) {
            return __('app.no_closing_date');
        }

        if ($start && $end) {
            return $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y');
        }

        if ($start) {
            return __('app.from_date', [
                'date' => $start->format('d/m/Y'),
            ]);
        }

        return __('app.until_date', [
            'date' => $end?->format('d/m/Y'),
        ]);
    }

    protected function submittedDate(?object $entry): ?string
    {
        if (! $entry || blank($entry->created_at ?? null)) {
            return null;
        }

        return Carbon::parse($entry->created_at)->format('d/m/Y');
    }

    protected function translatedFormName(object $form): string
    {
        $name = $this->formName($form);
        $slug = $this->formSlug($form);

        $key = 'app.forms_nav.' . $slug;

        $translated = __($key);

        if ($translated !== $key) {
            return $translated;
        }

        return $name;
    }

    protected function formName(object $form): string
    {
        return (string) (
            $form->name
            ?? $form->form_name
            ?? $form->title
            ?? __('app.untitled_form')
        );
    }

    protected function formSlug(object $form): string
    {
        return (string) (
            $form->slug
            ?? Str::slug($this->formName($form))
        );
    }

    protected function submissionLimit(object $form): string
    {
        foreach (['submission_limit', 'submit_limit', 'limit_per_year'] as $column) {
            if (isset($form->{$column}) && filled($form->{$column})) {
                return (string) $form->{$column};
            }
        }

        return '1x';
    }

    protected function firstExistingColumn(array $columns, array $names): ?string
    {
        foreach ($names as $name) {
            if (in_array($name, $columns, true)) {
                return $name;
            }
        }

        return null;
    }
}
