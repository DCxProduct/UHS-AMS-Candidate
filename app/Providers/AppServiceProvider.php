<?php

namespace App\Providers;

use App\Models\User;
use App\Support\NotificationLanguage;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Notifications\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return route('student.password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        CustomForm::creating(function (CustomForm $form): void {
            if (blank($form->allowed_roles)) {
                $form->allowed_roles = json_encode([
                    'student',
                    'admin',
                ], JSON_UNESCAPED_UNICODE);
            }
        });

        CustomForm::saving(function (CustomForm $form): void {
            if (blank($form->allowed_roles)) {
                $form->allowed_roles = json_encode([
                    'student',
                    'admin',
                ], JSON_UNESCAPED_UNICODE);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Auto save owner when student submits dynamic form
        |--------------------------------------------------------------------------
        */
        CustomFormEntry::creating(function (CustomFormEntry $entry): void {
            if (! auth()->check()) {
                return;
            }

            $userId = auth()->id();

            if (Schema::hasColumn('custom_form_entries', 'created_by') && blank($entry->created_by)) {
                $entry->created_by = $userId;
            }

            if (Schema::hasColumn('custom_form_entries', 'user_id') && blank($entry->user_id)) {
                $entry->user_id = $userId;
            }

            if (Schema::hasColumn('custom_form_entries', 'created_by_id') && blank($entry->created_by_id)) {
                $entry->created_by_id = $userId;
            }

            if (Schema::hasColumn('custom_form_entries', 'review_status') && blank($entry->review_status)) {
                $entry->review_status = 'pending';
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Notify admin when student submits Enrollment
        |--------------------------------------------------------------------------
        */
        CustomFormEntry::created(function (CustomFormEntry $entry): void {
            $this->notifyAdminsWhenStudentSubmitEnrollment($entry);
        });

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales([
                    'en',
                    'km',
                ])
                ->labels([
                    'en' => 'English',
                    'km' => 'ខ្មែរ',
                ])
                ->flags([
                    'en' => 'https://flagcdn.com/w40/gb.png',
                    'km' => 'https://flagcdn.com/w40/kh.png',
                ])
                ->flagsOnly(false)
                ->circular()
                ->visible(
                    insidePanels: true,
                    outsidePanels: true,
                );
        });
    }

    protected function notifyAdminsWhenStudentSubmitEnrollment(CustomFormEntry $entry): void
    {
        try {
            if (
                ! Schema::hasTable('custom_forms')
                || ! Schema::hasTable('custom_form_entries')
                || ! Schema::hasTable('users')
                || ! Schema::hasTable('notifications')
            ) {
                return;
            }

            $form = DB::table('custom_forms')
                ->select([
                    'id',
                    'name',
                    'slug',
                ])
                ->where('id', $entry->custom_form_id)
                ->first();

            if (! $form) {
                return;
            }

            if ((string) $form->slug !== 'enrollment') {
                return;
            }

            $student = auth()->user();

            if (! $student || (string) $student->registration_type !== 'student') {
                return;
            }

            $admins = User::query()
                ->where('registration_type', 'admin')
                ->when(
                    Schema::hasColumn('users', 'is_active'),
                    fn ($query) => $query->where('is_active', true),
                )
                ->get();

            if ($admins->isEmpty()) {
                return;
            }

            $data = $this->normalizeCustomFormEntryData($entry->data);

            $studentName = $this->getStudentNameForNotification($data, $student->name ?? null);

            foreach ($admins as $admin) {
                Notification::make()
                    ->title(NotificationLanguage::transForUser(
                        $admin,
                        'review_applications.notifications.enrollment_submitted_title'
                    ))
                    ->body(NotificationLanguage::transForUser(
                        $admin,
                        'review_applications.notifications.enrollment_submitted_body',
                        [
                            'student' => $studentName,
                        ]
                    ))
                    ->icon('heroicon-o-clipboard-document-check')
                    ->iconColor('warning')
                    ->sendToDatabase($admin);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function normalizeCustomFormEntryData(mixed $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        $decoded = json_decode((string) $data, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function getStudentNameForNotification(array $data, ?string $fallbackName = null): string
    {
        $khmerName = trim(implode(' ', array_filter([
            $data['last_name_kh'] ?? null,
            $data['first_name_kh'] ?? null,
        ])));

        if (filled($khmerName)) {
            return $khmerName;
        }

        $englishName = trim(implode(' ', array_filter([
            $data['first_name_en'] ?? null,
            $data['last_name_en'] ?? null,
        ])));

        if (filled($englishName)) {
            return $englishName;
        }

        if (filled($data['student_id'] ?? null)) {
            return (string) $data['student_id'];
        }

        return filled($fallbackName) ? $fallbackName : __('review_applications.notifications.unknown_student');
    }
}
