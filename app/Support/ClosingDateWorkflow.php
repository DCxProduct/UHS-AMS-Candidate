<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClosingDateWorkflow
{
    public const STATE_OPEN = 'open';
    public const STATE_CONTACT = 'contact';
    public const STATE_HIDDEN = 'hidden';

    public static function checkByCustomFormId(int $customFormId): array
    {
        if (! Schema::hasTable('closing_dates')) {
            return self::openWithoutClosingDate();
        }

        $columns = Schema::getColumnListing('closing_dates');

        if (! in_array('type', $columns, true)) {
            return self::openWithoutClosingDate();
        }

        $query = DB::table('closing_dates')
            ->where('type', 'custom_form:' . $customFormId);

        if (in_array('deleted_at', $columns, true)) {
            $query->whereNull('deleted_at');
        }

        if (in_array('is_active', $columns, true)) {
            $query->where('is_active', true);
        }

        $closingDate = $query
            ->orderByDesc('id')
            ->first();

        if (! $closingDate) {
            return self::openWithoutClosingDate();
        }

        $status = self::normalizeStatus($closingDate->status ?? null);

        if ($status === 'closed') {
            return [
                'state' => self::STATE_CONTACT,
                'status' => 'closed',
                'can_submit' => false,
                'show_feature' => true,
                'show_contact' => true,
                'start_date' => self::formatDate($closingDate->start_date ?? null),
                'end_date' => self::formatDate($closingDate->end_date ?? null),
                'message' => __('app.form_closed_contact_message'),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Not Open = hide feature + no direct URL permission
        |--------------------------------------------------------------------------
        */
        if ($status !== 'open') {
            return [
                'state' => self::STATE_HIDDEN,
                'status' => 'not_open',
                'can_submit' => false,
                'show_feature' => false,
                'show_contact' => false,
                'start_date' => self::formatDate($closingDate->start_date ?? null),
                'end_date' => self::formatDate($closingDate->end_date ?? null),
                'message' => __('app.form_not_open_message'),
            ];
        }

        $startDate = $closingDate->start_date ?? null;
        $endDate = $closingDate->end_date ?? null;

        if (blank($startDate) && blank($endDate)) {
            return [
                'state' => self::STATE_OPEN,
                'status' => 'open',
                'can_submit' => true,
                'show_feature' => true,
                'show_contact' => false,
                'start_date' => null,
                'end_date' => null,
                'message' => __('app.enrollment_is_open_message'),
            ];
        }

        $today = now()->startOfDay();

        $start = filled($startDate)
            ? Carbon::parse($startDate)->startOfDay()
            : null;

        $end = filled($endDate)
            ? Carbon::parse($endDate)->endOfDay()
            : null;

        if ($start && $today->lt($start)) {
            return [
                'state' => self::STATE_HIDDEN,
                'status' => 'not_open_yet',
                'can_submit' => false,
                'show_feature' => false,
                'show_contact' => false,
                'start_date' => $start->format('d M Y'),
                'end_date' => $end?->format('d M Y'),
                'message' => __('app.enrollment_not_open_yet_message'),
            ];
        }

        if ($end && $today->gt($end)) {
            return [
                'state' => self::STATE_HIDDEN,
                'status' => 'expired',
                'can_submit' => false,
                'show_feature' => false,
                'show_contact' => false,
                'start_date' => $start?->format('d M Y'),
                'end_date' => $end->format('d M Y'),
                'message' => __('app.enrollment_period_closed_message'),
            ];
        }

        return [
            'state' => self::STATE_OPEN,
            'status' => 'open',
            'can_submit' => true,
            'show_feature' => true,
            'show_contact' => false,
            'start_date' => $start?->format('d M Y'),
            'end_date' => $end?->format('d M Y'),
            'message' => __('app.enrollment_is_open_message'),
        ];
    }

    public static function shouldShowFeature(int $customFormId): bool
    {
        return (bool) self::checkByCustomFormId($customFormId)['show_feature'];
    }

    public static function shouldShowContact(int $customFormId): bool
    {
        return (bool) self::checkByCustomFormId($customFormId)['show_contact'];
    }

    public static function canSubmit(int $customFormId): bool
    {
        return (bool) self::checkByCustomFormId($customFormId)['can_submit'];
    }

    public static function isHidden(int $customFormId): bool
    {
        return self::checkByCustomFormId($customFormId)['state'] === self::STATE_HIDDEN;
    }

    protected static function openWithoutClosingDate(): array
    {
        return [
            'state' => self::STATE_OPEN,
            'status' => 'open',
            'can_submit' => true,
            'show_feature' => true,
            'show_contact' => false,
            'start_date' => null,
            'end_date' => null,
            'message' => __('app.no_closing_date'),
        ];
    }

    protected static function normalizeStatus(mixed $status): string
    {
        return str_replace('-', '_', strtolower(trim((string) $status)));
    }

    protected static function formatDate(mixed $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        return Carbon::parse($date)->format('d M Y');
    }
}
