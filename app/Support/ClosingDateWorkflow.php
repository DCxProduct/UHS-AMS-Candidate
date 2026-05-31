<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClosingDateWorkflow
{
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

        $startDate = $closingDate->start_date ?? null;
        $endDate = $closingDate->end_date ?? null;

        if (blank($startDate) && blank($endDate)) {
            return self::openWithoutClosingDate();
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
                'status' => 'not_open_yet',
                'can_submit' => false,
                'start_date' => $start->format('d M Y'),
                'end_date' => $end?->format('d M Y'),
                'message' => __('app.enrollment_not_open_yet_message'),
            ];
        }

        if ($end && $today->gt($end)) {
            return [
                'status' => 'expired',
                'can_submit' => false,
                'start_date' => $start?->format('d M Y'),
                'end_date' => $end->format('d M Y'),
                'message' => __('app.enrollment_period_closed_message'),
            ];
        }

        return [
            'status' => 'open',
            'can_submit' => true,
            'start_date' => $start?->format('d M Y'),
            'end_date' => $end?->format('d M Y'),
            'message' => __('app.enrollment_is_open_message'),
        ];
    }

    protected static function openWithoutClosingDate(): array
    {
        return [
            'status' => 'open',
            'can_submit' => true,
            'start_date' => null,
            'end_date' => null,
            'message' => __('app.no_closing_date'),
        ];
    }
}
