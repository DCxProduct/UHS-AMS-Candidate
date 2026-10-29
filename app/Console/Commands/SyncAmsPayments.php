<?php

namespace App\Console\Commands;

use App\Models\AmsPaymentRequest;
use App\Services\AmsPaymentSync;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Console\Command;

class SyncAmsPayments extends Command
{
    protected $signature = 'ams:sync-payments {--entry= : Retry one application}';
    protected $description = 'Retry pending or failed Candidate payment requests to AMS';

    public function handle(AmsPaymentSync $sync): int
    {
        $query = CustomFormEntry::query();
        if ($this->option('entry')) {
            $query->whereKey($this->option('entry'));
        } else {
            $query->whereIn('id', AmsPaymentRequest::query()->whereIn('sync_status', ['pending', 'failed'])->select('custom_form_entry_id'));
        }
        $failed = 0;
        foreach ($query->lazyById() as $entry) {
            $result = $sync->sync($entry);
            if ($result?->sync_status === 'failed') {
                $failed++;
            }
        }
        $this->info($failed === 0 ? 'AMS payment requests synchronized.' : $failed.' AMS requests remain pending retry.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
