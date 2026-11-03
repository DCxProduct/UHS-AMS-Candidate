<?php

namespace App\Console\Commands;

use App\Models\AmsPaymentRequest;
use App\Services\AmsPaymentSync;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Console\Command;

class SyncAmsPayments extends Command
{
    protected $signature = 'ams:sync-payments {--entry= : Retry one application} {--refresh-workflow : Resend frozen payment settings for one unpaid --entry}';
    protected $description = 'Retry pending or failed Candidate payment requests to AMS';

    public function handle(AmsPaymentSync $sync): int
    {
        if ($this->option('refresh-workflow') && blank($this->option('entry'))) {
            $this->error('Specify --entry when refreshing AMS workflow settings.');

            return self::INVALID;
        }

        $query = CustomFormEntry::query();
        if ($this->option('entry')) {
            $query->whereKey($this->option('entry'));
        } else {
            $query->whereIn('id', AmsPaymentRequest::query()->whereIn('sync_status', ['pending', 'failed'])->select('custom_form_entry_id'));
        }
        $failed = 0;
        foreach ($query->lazyById() as $entry) {
            $result = $sync->sync($entry, (bool) $this->option('refresh-workflow'));
            if ($result?->sync_status === 'failed') {
                $failed++;
            }
        }
        $this->info($failed === 0 ? 'AMS payment requests synchronized.' : $failed.' AMS requests remain pending retry.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
