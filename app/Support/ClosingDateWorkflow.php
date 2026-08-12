<?php

namespace App\Support;

use App\Models\ClosingDate;
use Carbon\Carbon;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Illuminate\Support\Str;

class ClosingDateWorkflow
{
    public const STATE_OPEN = 'open';
    public const STATE_HIDDEN = 'hidden';
    public const STATE_CONTACT = 'contact';
    public const STATE_EXPIRED = 'expired';

    public static function checkByCustomFormId(?int $customFormId): array
    {
        if (! $customFormId) {
            return self::openWithoutRule();
        }

        $form = CustomForm::query()->find($customFormId);

        if (! $form) {
            return self::openWithoutRule();
        }

        $exactTypeKeys = [
            self::customFormTypeKey($customFormId),
            'custom_form:' . $customFormId,
            (string) $customFormId,
        ];

        $closingDate = ClosingDate::query()
            ->whereIn('type', $exactTypeKeys)
            ->latest('id')
            ->first();

        if (! $closingDate) {
            $closingDate = ClosingDate::query()
                ->where(function ($query) use ($form): void {
                    $query
                        ->where('name', $form->name)
                        ->orWhere('name', $form->slug)
                        ->orWhere('name', Str::slug((string) $form->name))
                        ->orWhere('type', (string) $form->name)
                        ->orWhere('type', (string) $form->slug)
                        ->orWhere('type', Str::slug((string) $form->name));
                })
                ->latest('id')
                ->first();
        }

        if (! $closingDate) {
            return self::openWithoutRule();
        }

        return self::buildStatus($closingDate);
    }

    public static function check(?string $type): array
    {
        if (blank($type)) {
            return self::openWithoutRule();
        }

        $type = trim((string) $type);

        $closingDate = ClosingDate::query()
            ->where(function ($query) use ($type): void {
                $query
                    ->where('type', $type)
                    ->orWhere('type', Str::slug($type))
                    ->orWhere('name', $type)
                    ->orWhere('name', Str::slug($type));
            })
            ->latest('id')
            ->first();

        if (! $closingDate) {
            return self::openWithoutRule();
        }

        return self::buildStatus($closingDate);
    }

    /*
    |--------------------------------------------------------------------------
    | Role helpers
    |--------------------------------------------------------------------------
    */

    public static function currentUserIsAdmin(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return method_exists($user, 'hasEffectiveRole')
            ? $user->hasEffectiveRole('admin')
            : $user->registration_type === 'admin';
    }

    public static function currentUserIsStudent(): bool
    {
        $user = auth()->user();

        if (! $user || $user->registration_type !== 'student') {
            return false;
        }

        if (
            method_exists($user, 'hasEffectiveRole')
            && $user->hasEffectiveRole([
                'admin',
                'cashier',
                'finance',
                'developer',
                'registrar',
                'processing',
                'team uhs',
            ])
        ) {
            return false;
        }

        return true;
    }

    public static function adminCanManage(): bool
    {
        return self::currentUserIsAdmin();
    }

    /*
    |--------------------------------------------------------------------------
    | Custom form helpers for sidebar / direct URL / submit
    |--------------------------------------------------------------------------
    */

    public static function shouldShowFeature(?int $customFormId): bool
    {
        if (self::adminCanManage()) {
            return true;
        }

        return (bool) (self::checkByCustomFormId($customFormId)['can_see_form'] ?? true);
    }

    public static function canOpenCustomFormId(?int $customFormId): bool
    {
        if (self::adminCanManage()) {
            return true;
        }

        return (bool) (self::checkByCustomFormId($customFormId)['can_open_form'] ?? true);
    }

    public static function canSubmitCustomFormId(?int $customFormId): bool
    {
        if (self::adminCanManage()) {
            return true;
        }

        return (bool) (self::checkByCustomFormId($customFormId)['can_submit'] ?? true);
    }

    public static function shouldShowContact(?int $customFormId): bool
    {
        if (self::adminCanManage()) {
            return false;
        }

        return (bool) (self::checkByCustomFormId($customFormId)['show_contact'] ?? false);
    }

    public static function isHidden(?int $customFormId): bool
    {
        return (self::checkByCustomFormId($customFormId)['state'] ?? self::STATE_OPEN) === self::STATE_HIDDEN;
    }

    public static function isContact(?int $customFormId): bool
    {
        return (self::checkByCustomFormId($customFormId)['state'] ?? self::STATE_OPEN) === self::STATE_CONTACT;
    }

    public static function isOpen(?int $customFormId): bool
    {
        return (self::checkByCustomFormId($customFormId)['state'] ?? self::STATE_OPEN) === self::STATE_OPEN;
    }

    /*
    |--------------------------------------------------------------------------
    | Generic helpers
    |--------------------------------------------------------------------------
    */

    public static function canSeeForm(?string $type): bool
    {
        if (self::adminCanManage()) {
            return true;
        }

        return (bool) (self::check($type)['can_see_form'] ?? true);
    }

    public static function canSubmit(?string $type): bool
    {
        if (self::adminCanManage()) {
            return true;
        }

        return (bool) (self::check($type)['can_submit'] ?? true);
    }

    private static function buildStatus(ClosingDate $closingDate): array
    {
        $status = self::normalizeStatus($closingDate->status ?? null);

        /*
        |--------------------------------------------------------------------------
        | Status closed
        |--------------------------------------------------------------------------
        | Student sees feature, but feature opens Contact Us.
        |--------------------------------------------------------------------------
        */
        if ($status === 'closed') {
            return [
                'state' => self::STATE_CONTACT,
                'status' => 'closed',
                'can_see_form' => true,
                'can_open_form' => false,
                'can_submit' => false,
                'show_contact' => true,
                'title' => __('app.form_closed_contact_title'),
                'message' => __('app.form_closed_contact_message'),
                'start_date' => self::formatDate($closingDate->start_date ?? null),
                'end_date' => self::formatDate($closingDate->end_date ?? null),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Status not open
        |--------------------------------------------------------------------------
        | Student does not see feature and direct URL is blocked.
        |--------------------------------------------------------------------------
        */
        if (in_array($status, ['not_open', 'notopen', 'inactive', 'draft', 'pending'], true)) {
            return [
                'state' => self::STATE_HIDDEN,
                'status' => 'not_open',
                'can_see_form' => false,
                'can_open_form' => false,
                'can_submit' => false,
                'show_contact' => false,
                'title' => __('app.form_not_open_title'),
                'message' => __('app.form_not_open_message'),
                'start_date' => self::formatDate($closingDate->start_date ?? null),
                'end_date' => self::formatDate($closingDate->end_date ?? null),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Empty date = open
        |--------------------------------------------------------------------------
        */
        if (blank($closingDate->start_date) && blank($closingDate->end_date)) {
            return self::openWithoutRule();
        }

        $today = today();

        $startDate = filled($closingDate->start_date)
            ? Carbon::parse($closingDate->start_date)->startOfDay()
            : null;

        $endDate = filled($closingDate->end_date)
            ? Carbon::parse($closingDate->end_date)->endOfDay()
            : null;

        /*
        |--------------------------------------------------------------------------
        | Before start date
        |--------------------------------------------------------------------------
        | Hide feature and block direct URL.
        |--------------------------------------------------------------------------
        */
        if ($startDate && $today->lt($startDate)) {
            return [
                'state' => self::STATE_HIDDEN,
                'status' => 'not_open_yet',
                'can_see_form' => false,
                'can_open_form' => false,
                'can_submit' => false,
                'show_contact' => false,
                'title' => __('app.enrollment_not_open_yet_title'),
                'message' => __('app.enrollment_not_open_yet_message'),
                'start_date' => $startDate->format('d M Y'),
                'end_date' => $endDate?->format('d M Y'),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Inside date range
        |--------------------------------------------------------------------------
        | Show feature and allow submit.
        |--------------------------------------------------------------------------
        */
        if (
            (! $startDate || $today->gte($startDate))
            && (! $endDate || $today->lte($endDate))
        ) {
            return [
                'state' => self::STATE_OPEN,
                'status' => 'open',
                'can_see_form' => true,
                'can_open_form' => true,
                'can_submit' => true,
                'show_contact' => false,
                'title' => __('app.enrollment_is_open_title'),
                'message' => __('app.enrollment_is_open_message'),
                'start_date' => $startDate?->format('d M Y'),
                'end_date' => $endDate?->format('d M Y'),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | After end date
        |--------------------------------------------------------------------------
        | Show feature, allow view, but block submit.
        |--------------------------------------------------------------------------
        */
        return [
            'state' => self::STATE_EXPIRED,
            'status' => 'expired',
            'can_see_form' => true,
            'can_open_form' => true,
            'can_submit' => false,
            'show_contact' => false,
            'title' => __('app.enrollment_period_closed_title'),
            'message' => __('app.enrollment_period_closed_message'),
            'start_date' => $startDate?->format('d M Y'),
            'end_date' => $endDate?->format('d M Y'),
        ];
    }

    private static function customFormTypeKey(int $customFormId): string
    {
        return 'custom_form_' . $customFormId;
    }

    private static function openWithoutRule(): array
    {
        return [
            'state' => self::STATE_OPEN,
            'status' => 'open',
            'can_see_form' => true,
            'can_open_form' => true,
            'can_submit' => true,
            'show_contact' => false,
            'title' => __('app.enrollment_is_open_title'),
            'message' => __('app.no_closing_date'),
            'start_date' => null,
            'end_date' => null,
        ];
    }

    private static function normalizeStatus(mixed $status): string
    {
        return str_replace(['-', ' '], '_', strtolower(trim((string) $status)));
    }

    private static function formatDate(mixed $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        return Carbon::parse($date)->format('d M Y');
    }
}
