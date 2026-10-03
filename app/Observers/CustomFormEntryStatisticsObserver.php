<?php

namespace App\Observers;

use App\Support\CandidateStatisticsSynchronizer;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;

class CustomFormEntryStatisticsObserver
{
    public function created(CustomFormEntry $entry): void
    {
        CandidateStatisticsSynchronizer::syncEntry($entry);
    }

    public function updated(CustomFormEntry $entry): void
    {
        CandidateStatisticsSynchronizer::syncEntry($entry);
    }
}
