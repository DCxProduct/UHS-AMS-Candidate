<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DashboardMetrics
{
    /*
    |--------------------------------------------------------------------------
    | Admin metrics
    |--------------------------------------------------------------------------
    */

    public static function totalStudents(): int
    {
        if (! Schema::hasTable('users')) {
            return 0;
        }

        return DB::table('users')
            ->where('registration_type', 'student')
            ->count();
    }

    public static function activeStudents(): int
    {
        if (! Schema::hasTable('users')) {
            return 0;
        }

        $query = DB::table('users')
            ->where('registration_type', 'student');

        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->count();
    }

    public static function inactiveStudents(): int
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasColumn('users', 'is_active')
        ) {
            return 0;
        }

        return DB::table('users')
            ->where('registration_type', 'student')
            ->where('is_active', false)
            ->count();
    }

    public static function totalSubmissions(): int
    {
        if (! Schema::hasTable('custom_form_entries')) {
            return 0;
        }

        return DB::table('custom_form_entries')->count();
    }

    public static function totalCustomForms(): int
    {
        if (! Schema::hasTable('custom_forms')) {
            return 0;
        }

        $query = DB::table('custom_forms');

        if (Schema::hasColumn('custom_forms', 'is_active')) {
            $query->where('is_active', true);
        } elseif (Schema::hasColumn('custom_forms', 'active')) {
            $query->where('active', true);
        }

        return $query->count();
    }

    public static function reviewStatusCounts(): array
    {
        $default = [
            'pending' => 0,
            'accepted' => 0,
            'rejected' => 0,
        ];

        if (! Schema::hasTable('custom_form_entries')) {
            return $default;
        }

        $statusColumn = static::entryStatusColumn();

        if (! $statusColumn) {
            return [
                'pending' => static::totalSubmissions(),
                'accepted' => 0,
                'rejected' => 0,
            ];
        }

        $statuses = DB::table('custom_form_entries')
            ->pluck($statusColumn);

        $result = $default;

        foreach ($statuses as $status) {
            $normalizedStatus = static::normalizeStatus($status);

            if (array_key_exists($normalizedStatus, $result)) {
                $result[$normalizedStatus]++;
            }
        }

        return $result;
    }

    public static function monthlySubmissions(int $monthCount = 6): array
    {
        $monthCount = max(1, $monthCount);

        $months = collect(range($monthCount - 1, 0))
            ->map(
                fn (int $offset): Carbon => now()
                    ->subMonths($offset)
                    ->startOfMonth()
            );

        $counts = $months
            ->mapWithKeys(
                fn (Carbon $month): array => [
                    $month->format('Y-m') => 0,
                ]
            )
            ->all();

        if (
            Schema::hasTable('custom_form_entries')
            && Schema::hasColumn('custom_form_entries', 'created_at')
        ) {
            $startDate = $months
                ->first()
                ?->copy()
                ->startOfMonth();

            $createdDates = DB::table('custom_form_entries')
                ->where('created_at', '>=', $startDate)
                ->pluck('created_at');

            foreach ($createdDates as $createdAt) {
                if (blank($createdAt)) {
                    continue;
                }

                $key = Carbon::parse($createdAt)->format('Y-m');

                if (array_key_exists($key, $counts)) {
                    $counts[$key]++;
                }
            }
        }

        return [
            'labels' => $months
                ->map(
                    fn (Carbon $month): string => static::localizedMonthLabel($month)
                )
                ->values()
                ->all(),

            'data' => array_values($counts),
        ];
    }

    public static function submissionsByForm(): array
    {
        if (
            ! Schema::hasTable('custom_forms')
            || ! Schema::hasTable('custom_form_entries')
            || ! Schema::hasColumn('custom_form_entries', 'custom_form_id')
        ) {
            return [
                'labels' => [],
                'data' => [],
            ];
        }

        $forms = DB::table('custom_forms')
            ->select([
                'custom_forms.id',
                'custom_forms.name',
            ])
            ->selectRaw(
                'COUNT(custom_form_entries.id) AS entry_count'
            )
            ->leftJoin(
                'custom_form_entries',
                'custom_forms.id',
                '=',
                'custom_form_entries.custom_form_id'
            )
            ->groupBy([
                'custom_forms.id',
                'custom_forms.name',
            ])
            ->orderByDesc('entry_count')
            ->limit(10)
            ->get();

        return [
            'labels' => $forms
                ->pluck('name')
                ->map(fn ($name): string => (string) $name)
                ->all(),

            'data' => $forms
                ->pluck('entry_count')
                ->map(fn ($count): int => (int) $count)
                ->all(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Student metrics
    |--------------------------------------------------------------------------
    */

    public static function studentHasCompletedForm(
        int $userId,
        string $formSlug,
    ): bool {
        $formId = static::formIdBySlug($formSlug);

        if (! $formId) {
            return false;
        }

        return static::studentHasEntryForForm($userId, $formId);
    }

    public static function studentHasEntryForForm(
        int $userId,
        int $formId,
    ): bool {
        if (
            ! Schema::hasTable('custom_form_entries')
            || ! Schema::hasColumn(
                'custom_form_entries',
                'custom_form_id'
            )
        ) {
            return false;
        }

        $ownerColumns = static::entryOwnerColumns();

        if (empty($ownerColumns)) {
            return false;
        }

        return DB::table('custom_form_entries')
            ->where('custom_form_id', $formId)
            ->where(function ($query) use ($ownerColumns, $userId): void {
                foreach ($ownerColumns as $column) {
                    $query->orWhere($column, $userId);
                }
            })
            ->exists();
    }

    public static function studentLatestStatus(int $userId): string
    {
        if (! Schema::hasTable('custom_form_entries')) {
            return 'not_submitted';
        }

        $ownerColumns = static::entryOwnerColumns();

        if (empty($ownerColumns)) {
            return 'not_submitted';
        }

        $query = DB::table('custom_form_entries')
            ->where(function ($query) use ($ownerColumns, $userId): void {
                foreach ($ownerColumns as $column) {
                    $query->orWhere($column, $userId);
                }
            });

        if (! $query->exists()) {
            return 'not_submitted';
        }

        $statusColumn = static::entryStatusColumn();

        if (! $statusColumn) {
            return 'pending';
        }

        $status = $query
            ->orderByDesc('id')
            ->value($statusColumn);

        return static::normalizeStatus($status);
    }

    public static function studentProgressItems(int $userId): array
    {
        return collect(static::studentAvailableForms($userId))
            ->map(function (array $form) use ($userId): array {
                return [
                    'id' => $form['id'],
                    'slug' => $form['slug'],
                    'name' => $form['name'],
                    'completed' => static::studentHasEntryForForm(
                        $userId,
                        $form['id']
                    ),
                ];
            })
            ->values()
            ->all();
    }

    public static function studentProgressPercentage(int $userId): int
    {
        $items = static::studentProgressItems($userId);

        if (empty($items)) {
            return 0;
        }

        $completedCount = collect($items)
            ->where('completed', true)
            ->count();

        return (int) round(
            ($completedCount / count($items)) * 100
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Dynamic student quick actions
    |--------------------------------------------------------------------------
    */

    public static function studentQuickActions(int $userId): array
    {
        $forms = static::studentAvailableForms($userId);

        return collect($forms)
            ->map(function (array $form) use ($userId): array {
                $completed = static::studentHasEntryForForm(
                    $userId,
                    $form['id']
                );

                $workflow = static::formWorkflow($form['id']);

                $showContact = (bool) (
                    $workflow['show_contact'] ?? false
                );

                $url = $showContact
                    ? url('/contact-us?form_id=' . $form['id'])
                    : static::customFormEntryUrl($form['id']);

                return [
                    'id' => $form['id'],
                    'name' => $form['name'],
                    'slug' => $form['slug'],
                    'url' => $url,
                    'completed' => $completed,
                    'expired' => $showContact,
                    'icon' => static::formIcon($form['slug']),
                    'color' => $completed
                        ? 'success'
                        : ($showContact ? 'danger' : 'primary'),
                ];
            })
            ->values()
            ->all();
    }

    public static function studentAvailableForms(int $userId): array
    {
        if (! Schema::hasTable('custom_forms')) {
            return [];
        }

        $query = DB::table('custom_forms')
            ->whereNotNull('name')
            ->orderBy('id');

        if (Schema::hasColumn('custom_forms', 'is_active')) {
            $query->where('is_active', true);
        } elseif (Schema::hasColumn('custom_forms', 'active')) {
            $query->where('active', true);
        }

        $forms = $query->get();

        $profileCompleted = static::studentHasCompletedForm(
            $userId,
            'profile'
        );

        return $forms
            ->filter(function ($form) use ($profileCompleted): bool {
                $slug = (string) ($form->slug ?? '');

                if (! static::formAllowsStudent($form)) {
                    return false;
                }

                if ($slug !== 'profile' && ! $profileCompleted) {
                    return false;
                }

                $workflow = static::formWorkflow((int) $form->id);

                return (bool) (
                    $workflow['can_see_form'] ?? true
                );
            })
            ->map(function ($form): array {
                return [
                    'id' => (int) $form->id,
                    'name' => (string) $form->name,
                    'slug' => (string) ($form->slug ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Form helpers
    |--------------------------------------------------------------------------
    */

    public static function formIdBySlug(string $slug): ?int
    {
        if (
            ! Schema::hasTable('custom_forms')
            || ! Schema::hasColumn('custom_forms', 'slug')
        ) {
            return null;
        }

        $id = DB::table('custom_forms')
            ->where('slug', $slug)
            ->value('id');

        return $id ? (int) $id : null;
    }

    public static function customFormEntryUrl(int $formId): string
    {
        return url('/custom-form-entries')
            . '?'
            . http_build_query([
                'tableFilters' => [
                    'custom_form_id' => [
                        'value' => $formId,
                    ],
                ],
            ]);
    }

    private static function formWorkflow(int $formId): array
    {
        if (
            class_exists(ClosingDateWorkflow::class)
            && method_exists(
                ClosingDateWorkflow::class,
                'checkByCustomFormId'
            )
        ) {
            return ClosingDateWorkflow::checkByCustomFormId($formId);
        }

        return [
            'can_see_form' => true,
            'can_submit' => true,
            'show_contact' => false,
            'status' => 'open',
        ];
    }

    private static function formAllowsStudent(object $form): bool
    {
        if (! property_exists($form, 'allowed_roles')) {
            return true;
        }

        $roles = $form->allowed_roles ?? [];

        if (blank($roles)) {
            return true;
        }

        if (is_string($roles)) {
            $decoded = json_decode($roles, true);

            $roles = is_array($decoded)
                ? $decoded
                : explode(',', $roles);
        }

        if (! is_array($roles)) {
            return true;
        }

        $roles = collect($roles)
            ->map(
                fn ($role): string => strtolower(
                    trim((string) $role)
                )
            )
            ->filter()
            ->values()
            ->all();

        return empty($roles)
            || in_array('student', $roles, true);
    }

    private static function formIcon(string $slug): string
    {
        return match ($slug) {
            'profile' => 'heroicon-o-user-circle',
            'enrollment' => 'heroicon-o-document-text',
            'national-exam',
            'national-examination' => 'heroicon-o-academic-cap',
            default => 'heroicon-o-clipboard-document-list',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Database schema helpers
    |--------------------------------------------------------------------------
    */

    private static function entryOwnerColumns(): array
    {
        if (! Schema::hasTable('custom_form_entries')) {
            return [];
        }

        $columns = Schema::getColumnListing(
            'custom_form_entries'
        );

        return collect([
            'user_id',
            'created_by',
            'created_by_id',
            'system_user_id',
        ])
            ->filter(
                fn (string $column): bool => in_array(
                    $column,
                    $columns,
                    true
                )
            )
            ->values()
            ->all();
    }

    private static function entryStatusColumn(): ?string
    {
        if (! Schema::hasTable('custom_form_entries')) {
            return null;
        }

        $columns = Schema::getColumnListing(
            'custom_form_entries'
        );

        foreach ([
                     'review_status',
                     'status',
                     'admission_status',
                 ] as $column) {
            if (in_array($column, $columns, true)) {
                return $column;
            }
        }

        return null;
    }

    private static function normalizeStatus(mixed $status): string
    {
        $status = Str::lower(
            trim((string) $status)
        );

        return match ($status) {
            'accepted',
            'approved',
            'approve',
            'success' => 'accepted',

            'rejected',
            'declined',
            'deny',
            'denied' => 'rejected',

            '',
            'new',
            'submitted',
            'pending',
            'reviewing',
            'in_review' => 'pending',

            default => 'pending',
        };
    }

    private static function localizedMonthLabel(
        Carbon $month
    ): string {
        if (app()->getLocale() === 'km') {
            $khmerMonths = [
                1 => 'មករា',
                2 => 'កុម្ភៈ',
                3 => 'មីនា',
                4 => 'មេសា',
                5 => 'ឧសភា',
                6 => 'មិថុនា',
                7 => 'កក្កដា',
                8 => 'សីហា',
                9 => 'កញ្ញា',
                10 => 'តុលា',
                11 => 'វិច្ឆិកា',
                12 => 'ធ្នូ',
            ];

            return ($khmerMonths[$month->month] ?? '')
                . ' '
                . $month->year;
        }

        return $month->format('M Y');
    }
}
