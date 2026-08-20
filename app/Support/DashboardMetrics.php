<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DashboardMetrics
{
    /*
    |--------------------------------------------------------------------------
    | Admin statistics
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

    public static function studentSubmittedApplicationsCount(int $userId): int
    {
        if (! Schema::hasTable('custom_form_entries')) {
            return 0;
        }

        $ownerColumns = static::entryOwnerColumns();

        if (empty($ownerColumns)) {
            return 0;
        }

        $query = DB::table('custom_form_entries');

        if (
            Schema::hasTable('custom_forms')
            && Schema::hasColumn('custom_form_entries', 'custom_form_id')
            && Schema::hasColumn('custom_forms', 'slug')
        ) {
            $query
                ->join(
                    'custom_forms',
                    'custom_form_entries.custom_form_id',
                    '=',
                    'custom_forms.id'
                )
                ->where('custom_forms.slug', '!=', 'profile');
        }

        static::applyStudentOwnerFilter($query, $userId);

        if (Schema::hasColumn('custom_form_entries', 'review_status')) {
            $query->where(function ($query): void {
                $query->whereNull('custom_form_entries.review_status')
                    ->orWhere('custom_form_entries.review_status', '!=', 'draft');
            });
        }

        $query->where(function ($query): void {
            $query->whereNull('custom_form_entries.data->registration_status')
                ->orWhere('custom_form_entries.data->registration_status', '!=', 'draft');
        });

        return $query->count();
    }

    public static function adminSubmittedApplicationsCount(): int
    {
        if (
            ! Schema::hasTable('custom_form_entries')
            || ! Schema::hasTable('custom_forms')
            || ! Schema::hasColumn('custom_form_entries', 'custom_form_id')
        ) {
            return 0;
        }

        $query = DB::table('custom_form_entries')
            ->join(
                'custom_forms',
                'custom_form_entries.custom_form_id',
                '=',
                'custom_forms.id'
            )
            ->where('custom_forms.slug', '!=', 'profile');

        $query->where(function ($query): void {
            $query->where(function ($query): void {
                $query->where('custom_forms.menu_placement', 'sidebar')
                    ->where('custom_forms.is_active', true);
            })->orWhere(function ($query): void {
                $query->where('custom_forms.menu_placement', 'sub_item')
                    ->where('custom_forms.is_active', true)
                    ->whereExists(function ($query): void {
                        $query->selectRaw('1')
                            ->from('custom_forms as parent_forms')
                            ->whereColumn('parent_forms.id', 'custom_forms.custom_form_id')
                            ->where('parent_forms.menu_placement', 'sidebar')
                            ->where('parent_forms.is_active', true)
                            ->where('parent_forms.slug', '!=', 'profile');
                    });
            });
        });

        if (Schema::hasColumn('custom_form_entries', 'review_status')) {
            $query->where(function ($query): void {
                $query->whereNull('custom_form_entries.review_status')
                    ->orWhere('custom_form_entries.review_status', '!=', 'draft');
            });
        }

        $query->where(function ($query): void {
            $query->whereNull('custom_form_entries.data->registration_status')
                ->orWhere('custom_form_entries.data->registration_status', '!=', 'draft');
        });

        return $query->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Admin line chart
    |--------------------------------------------------------------------------
    */

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
            $startDate = $months->first()?->copy()->startOfMonth();

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

    /*
    |--------------------------------------------------------------------------
    | Admin bar diagram
    |--------------------------------------------------------------------------
    */

    public static function submissionsByForm(int $limit = 8): array
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

        $query = DB::table('custom_forms')
            ->select([
                'custom_forms.id',
                'custom_forms.name',
            ])
            ->selectRaw('COUNT(custom_form_entries.id) AS entry_count')
            ->leftJoin(
                'custom_form_entries',
                'custom_forms.id',
                '=',
                'custom_form_entries.custom_form_id'
            )
            ->whereNotNull('custom_forms.name');

        if (Schema::hasColumn('custom_forms', 'is_active')) {
            $query->where('custom_forms.is_active', true);
        } elseif (Schema::hasColumn('custom_forms', 'active')) {
            $query->where('custom_forms.active', true);
        }

        $forms = $query
            ->groupBy([
                'custom_forms.id',
                'custom_forms.name',
            ])
            ->orderByDesc('entry_count')
            ->limit($limit)
            ->get();

        return [
            'labels' => $forms
                ->pluck('name')
                ->map(fn ($name): string => (string) $name)
                ->values()
                ->all(),

            'data' => $forms
                ->pluck('entry_count')
                ->map(fn ($count): int => (int) $count)
                ->values()
                ->all(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Admin doughnut chart
    |--------------------------------------------------------------------------
    */

    public static function reviewStatusCounts(): array
    {
        $result = [
            'pending' => 0,
            'accepted' => 0,
            'rejected' => 0,
        ];

        if (! Schema::hasTable('custom_form_entries')) {
            return $result;
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

        foreach ($statuses as $status) {
            $normalizedStatus = static::normalizeStatus($status);

            if (array_key_exists($normalizedStatus, $result)) {
                $result[$normalizedStatus]++;
            }
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Student personal line chart
    |--------------------------------------------------------------------------
    */

    public static function studentMonthlySubmissions(
        int $userId,
        int $monthCount = 6,
    ): array {
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
            ! Schema::hasTable('custom_form_entries')
            || ! Schema::hasColumn('custom_form_entries', 'created_at')
        ) {
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

        $ownerColumns = static::entryOwnerColumns();

        if (empty($ownerColumns)) {
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

        $startDate = $months->first()?->copy()->startOfMonth();

        $query = DB::table('custom_form_entries')
            ->where('created_at', '>=', $startDate);

        static::applyStudentOwnerFilter($query, $userId);

        $createdDates = $query->pluck('created_at');

        foreach ($createdDates as $createdAt) {
            if (blank($createdAt)) {
                continue;
            }

            $key = Carbon::parse($createdAt)->format('Y-m');

            if (array_key_exists($key, $counts)) {
                $counts[$key]++;
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

    /*
    |--------------------------------------------------------------------------
    | Student form completion
    |--------------------------------------------------------------------------
    */

    public static function studentProgressItems(int $userId): array
    {
        return collect(static::studentAvailableForms($userId))
            ->map(function (array $form) use ($userId): array {
                return [
                    'id' => $form['id'],
                    'slug' => $form['slug'],
                    'name' => $form['name'],

                    'completed' => static::studentSubmissionCountForFormTree(
                        $userId,
                        $form['id']
                    ) > 0,
                ];
            })
            ->values()
            ->all();
    }

    public static function studentProgressPercentage(int $userId): int
    {
        $summary = static::studentCompletionSummary($userId);

        if ($summary['total'] === 0) {
            return 0;
        }

        return (int) round(
            ($summary['completed'] / $summary['total']) * 100
        );
    }

    public static function studentCompletionSummary(int $userId): array
    {
        $items = static::studentProgressItems($userId);

        $total = count($items);

        $completed = collect($items)
            ->where('completed', true)
            ->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'remaining' => max(0, $total - $completed),
        ];
    }

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

    public static function studentHasSubmittedForm(
        int $userId,
        string $formSlug,
    ): bool {
        $formId = static::formIdBySlug($formSlug);

        if (! $formId) {
            return false;
        }

        return static::studentSubmittedCountForForms($userId, [$formId]) > 0;
    }

    public static function studentHasEntryForForm(
        int $userId,
        int $formId,
    ): bool {
        return static::studentSubmissionCountForForms($userId, [$formId]) > 0;
    }

    public static function studentSubmissionCountForFormTree(
        int $userId,
        int $formId,
    ): int {
        $formIds = [$formId];

        if (
            Schema::hasTable('custom_forms')
            && Schema::hasColumn('custom_forms', 'custom_form_id')
        ) {
            $formIds = DB::table('custom_forms')
                ->where('id', $formId)
                ->orWhere('custom_form_id', $formId)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->push($formId)
                ->unique()
                ->values()
                ->all();
        }

        return static::studentSubmissionCountForForms($userId, $formIds);
    }

    private static function studentSubmissionCountForForms(
        int $userId,
        array $formIds,
    ): int {
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
            return 0;
        }

        $query = DB::table('custom_form_entries')
            ->whereIn('custom_form_id', array_values(array_unique($formIds)));

        static::applyStudentOwnerFilter($query, $userId);

        return $query->count();
    }

    private static function studentSubmittedCountForForms(
        int $userId,
        array $formIds,
    ): int {
        if (
            ! Schema::hasTable('custom_form_entries')
            || ! Schema::hasColumn('custom_form_entries', 'custom_form_id')
        ) {
            return 0;
        }

        $ownerColumns = static::entryOwnerColumns();

        if (empty($ownerColumns)) {
            return 0;
        }

        $query = DB::table('custom_form_entries')
            ->whereIn('custom_form_id', array_values(array_unique($formIds)));

        static::applyStudentOwnerFilter($query, $userId);

        if (Schema::hasColumn('custom_form_entries', 'review_status')) {
            $query->where(function ($query): void {
                $query->whereNull('review_status')
                    ->orWhere('review_status', '!=', 'draft');
            });
        }

        $query->where(function ($query): void {
            $query->whereNull('data->registration_status')
                ->orWhere('data->registration_status', '!=', 'draft');
        });

        return $query->count();
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

        $query = DB::table('custom_form_entries');

        static::applyStudentOwnerFilter($query, $userId);

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

    /*
    |--------------------------------------------------------------------------
    | Student dynamic forms
    |--------------------------------------------------------------------------
    */

    public static function studentAvailableForms(int $userId): array
    {
        if (! Schema::hasTable('custom_forms')) {
            return [];
        }

        if (! static::studentHasSubmittedForm($userId, 'profile')) {
            return [];
        }

        $userRoles = static::studentDashboardRoleKeys($userId);

        $query = DB::table('custom_forms')
            ->whereNotNull('name');

        if (Schema::hasColumn('custom_forms', 'is_active')) {
            $query->where('is_active', true);
        } elseif (Schema::hasColumn('custom_forms', 'active')) {
            $query->where('active', true);
        }

        if (Schema::hasColumn('custom_forms', 'menu_placement')) {
            $query->where('menu_placement', 'sidebar');
        }

        if (Schema::hasColumn('custom_forms', 'display_order')) {
            $query->orderBy('display_order');
        }

        $forms = $query
            ->orderBy('id')
            ->get();

        return $forms
            ->filter(function ($form) use ($userRoles): bool {
                if (! static::formAllowsStudent($form, $userRoles)) {
                    return false;
                }

                if ((string) ($form->slug ?? '') === 'profile') {
                    return false;
                }

                return ClosingDateWorkflow::canOpenCustomFormId((int) $form->id);
            })
            ->map(function ($form): array {
                return [
                    'id' => (int) $form->id,
                    'name' => self::transText($form->name),
                    'slug' => (string) ($form->slug ?? ''),
                ];
            })
            ->values()
            ->all();
    }
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

    /*
    |--------------------------------------------------------------------------
    | Owner detection
    |--------------------------------------------------------------------------
    */

    private static function studentOwnerIds(int $userId): array
    {
        $ids = collect([$userId]);

        if (
            ! Schema::hasTable('users')
            || ! Schema::hasTable('system_users')
        ) {
            return $ids->unique()->values()->all();
        }

        $user = DB::table('users')
            ->where('id', $userId)
            ->first();

        if (! $user) {
            return $ids->unique()->values()->all();
        }

        $criteria = [];

        foreach (['username', 'email', 'phone'] as $column) {
            if (
                Schema::hasColumn('system_users', $column)
                && filled($user->{$column} ?? null)
            ) {
                $criteria[] = [
                    'column' => $column,
                    'value' => $user->{$column},
                ];
            }
        }

        if (empty($criteria)) {
            return $ids->unique()->values()->all();
        }

        $systemUserIds = DB::table('system_users')
            ->where(function ($query) use ($criteria): void {
                foreach ($criteria as $criterion) {
                    $query->orWhere(
                        $criterion['column'],
                        $criterion['value']
                    );
                }
            })
            ->pluck('id');

        return $ids
            ->merge($systemUserIds)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private static function applyStudentOwnerFilter(
        Builder $query,
        int $userId,
    ): Builder {
        $ownerColumns = static::entryOwnerColumns();
        $ownerIds = static::studentOwnerIds($userId);

        return $query->where(
            function ($query) use ($ownerColumns, $ownerIds): void {
                foreach ($ownerColumns as $column) {
                    $query->orWhereIn($column, $ownerIds);
                }
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Other helpers
    |--------------------------------------------------------------------------
    */

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

    private static function formHasClosingDateRule(object $form): bool
    {
        if (! Schema::hasTable('closing_dates')) {
            return false;
        }

        $formId = (int) ($form->id ?? 0);
        $name = (string) ($form->name ?? '');
        $slug = (string) ($form->slug ?? '');

        return DB::table('closing_dates')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($formId, $name, $slug): void {
                $query
                    ->whereIn('type', array_filter([
                        'custom_form_' . $formId,
                        'custom_form:' . $formId,
                        (string) $formId,
                        $name,
                        $slug,
                        \Illuminate\Support\Str::slug($name),
                    ]))
                    ->orWhere('name', $name)
                    ->orWhere('name', $slug)
                    ->orWhere('name', \Illuminate\Support\Str::slug($name));
            })
            ->exists();
    }

    private static function formAllowsStudent(object $form, array $userRoles): bool
    {
        if (! property_exists($form, 'allowed_roles')) {
            return true;
        }

        $roles = $form->allowed_roles ?? [];

        if (blank($roles)) {
            return false;
        }

        if (is_string($roles)) {
            $decoded = json_decode($roles, true);

            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            $roles = is_array($decoded)
                ? $decoded
                : explode(',', trim($roles, " \t\n\r\0\x0B\"'[]"));
        }

        if (! is_array($roles)) {
            return false;
        }

        $roles = collect($roles)
            ->map(
                fn ($role): string => strtolower(
                    trim((string) $role, " \t\n\r\0\x0B\"'")
                )
            )
            ->reject(fn (string $role): bool => in_array($role, ['student', 'candidate'], true))
            ->flatMap(function (string $role): array {
                if (in_array($role, ['student', 'candidate'], true)) {
                    return ['student', 'candidate'];
                }

                return $role !== '' ? [$role] : [];
            })
            ->filter()
            ->values()
            ->all();

        if (empty($roles)) {
            return false;
        }

        return collect($userRoles)
            ->intersect($roles)
            ->isNotEmpty();
    }

    private static function studentDashboardRoleKeys(int $userId): array
    {
        $user = User::query()->find($userId);

        if (! $user) {
            return ['student', 'candidate'];
        }

        return $user->effectiveRoleNames()
            ->map(fn (string $role): string => strtolower(trim($role)))
            ->merge(['student', 'candidate'])
            ->filter(fn (string $role): bool => $role !== '')
            ->unique()
            ->values()
            ->all();
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

            default => 'pending',
        };
    }

    private static function localizedMonthLabel(Carbon $month): string
    {
        if (app()->getLocale() === 'km') {
            $months = [
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

            return ($months[$month->month] ?? '')
                . ' '
                . $month->year;
        }

        return $month->format('M Y');
    }

    protected static function transText(mixed $value): string
    {
        $locale = app()->getLocale();

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            return $value[$locale]
                ?? $value['km']
                ?? $value['en']
                ?? collect($value)->first()
                ?? '';
        }

        return (string) $value;
    }
}
