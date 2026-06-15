<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Dashboard extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-home';

    protected string $view = 'filament.pages.dashboard';

    protected static ?string $slug = 'dashboard';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('app.dashboard');
    }

    public function getTitle(): string | Htmlable
    {
        return __('app.dashboard');
    }

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->registration_type, [
            'admin',
            'student',
        ], true);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check();
    }

    public function isAdmin(): bool
    {
        return auth()->user()?->registration_type === 'admin';
    }

    public function isStudent(): bool
    {
        return auth()->user()?->registration_type === 'student';
    }

    public function getAdminStats(): array
    {
        if (! Schema::hasTable('custom_form_entries') || ! Schema::hasTable('custom_forms')) {
            return [
                'pending' => 0,
                'accepted' => 0,
                'rejected' => 0,
                'total' => 0,
            ];
        }

        $baseQuery = DB::table('custom_form_entries')
            ->join('custom_forms', 'custom_forms.id', '=', 'custom_form_entries.custom_form_id')
            ->where('custom_forms.slug', 'enrollment');

        return [
            'pending' => (clone $baseQuery)->where('review_status', 'pending')->count(),
            'accepted' => (clone $baseQuery)->where('review_status', 'accepted')->count(),
            'rejected' => (clone $baseQuery)->where('review_status', 'rejected')->count(),
            'total' => (clone $baseQuery)->count(),
        ];
    }

    public function getStudentStats(): array
    {
        $userId = auth()->id();

        if (
            ! $userId
            || ! Schema::hasTable('custom_forms')
            || ! Schema::hasTable('custom_form_entries')
        ) {
            return [
                'profile_completed' => false,
                'enrollment_status' => 'pending',
            ];
        }

        $profileFormId = DB::table('custom_forms')->where('slug', 'profile')->value('id');
        $enrollmentFormId = DB::table('custom_forms')->where('slug', 'enrollment')->value('id');

        $ownerColumns = collect([
            'created_by',
            'user_id',
            'created_by_id',
        ])
            ->filter(fn (string $column): bool => Schema::hasColumn('custom_form_entries', $column))
            ->values()
            ->all();

        if (empty($ownerColumns)) {
            return [
                'profile_completed' => false,
                'enrollment_status' => 'pending',
            ];
        }

        $profileCompleted = false;

        if ($profileFormId) {
            $profileCompleted = DB::table('custom_form_entries')
                ->where('custom_form_id', $profileFormId)
                ->where(function ($query) use ($ownerColumns, $userId): void {
                    foreach ($ownerColumns as $column) {
                        $query->orWhere($column, $userId);
                    }
                })
                ->exists();
        }

        $enrollmentStatus = 'pending';

        if ($enrollmentFormId) {
            $enrollmentStatus = DB::table('custom_form_entries')
                ->where('custom_form_id', $enrollmentFormId)
                ->where(function ($query) use ($ownerColumns, $userId): void {
                    foreach ($ownerColumns as $column) {
                        $query->orWhere($column, $userId);
                    }
                })
                ->latest('id')
                ->value('review_status') ?? 'pending';
        }

        return [
            'profile_completed' => $profileCompleted,
            'enrollment_status' => $enrollmentStatus,
        ];
    }

    public function getProfileUrl(): string
    {
        $profileFormId = Schema::hasTable('custom_forms')
            ? DB::table('custom_forms')->where('slug', 'profile')->value('id')
            : null;

        return $profileFormId
            ? url('/custom-form-entries?tableFilters[custom_form_id][value]=' . $profileFormId)
            : url('/custom-form-entries');
    }

    public function getEnrollmentUrl(): string
    {
        $enrollmentFormId = Schema::hasTable('custom_forms')
            ? DB::table('custom_forms')->where('slug', 'enrollment')->value('id')
            : null;

        return $enrollmentFormId
            ? url('/custom-form-entries?tableFilters[custom_form_id][value]=' . $enrollmentFormId)
            : url('/custom-form-entries');
    }
}
