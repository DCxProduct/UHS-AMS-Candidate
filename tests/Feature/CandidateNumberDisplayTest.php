<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CandidateEntranceStatistics\Tables\CandidateEntranceStatisticsTable;
use App\Filament\Admin\Resources\CandidateExitStatistics\Tables\CandidateExitStatisticsTable;
use App\Filament\Admin\Resources\ExamResults\Tables\ExamResultsTable;
use App\Filament\Admin\Resources\Payments\Tables\PaymentsTable;
use App\Filament\Admin\Resources\PaymentStatusLists\Tables\PaymentStatusListsTable;
use App\Models\CandidateExitStatistic;
use App\Models\Payment;
use App\Models\PaymentStatusList;
use App\Support\CandidateNumber;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class CandidateNumberDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_formatted_and_canonical_searches_match_without_changing_scope_or_stored_ids(): void
    {
        $form = $this->form('candidate-number-search');
        $otherForm = $this->form('candidate-number-other');
        $current = $this->entry($form, 'BNM260001');
        $nextYear = $this->entry($form, 'BNM270001');
        $nextNumber = $this->entry($form, 'BNM260002');
        $formatted = $this->entry($form, 'BNM-0001');
        $this->entry($otherForm, 'BNM260001');

        foreach (['BNM-0001', 'bnm-0001'] as $search) {
            $query = CustomFormEntry::query()->where('custom_form_id', $form);
            CandidateNumber::applySearch($query, 'candidate_id', $search);
            $this->assertSame([$current, $nextYear, $formatted], $query->orderBy('id')->pluck('id')->all());
        }

        $query = CustomFormEntry::query()->where('custom_form_id', $form);
        CandidateNumber::applySearch($query, 'candidate_id', 'BNM260001');
        $this->assertSame([$current], $query->pluck('id')->all());

        $query = CustomFormEntry::query()->where('custom_form_id', $form);
        CandidateNumber::applySearch($query, 'candidate_id', 'BNM-000');
        $this->assertSame([$current, $nextYear, $nextNumber, $formatted], $query->orderBy('id')->pluck('id')->all());
        $this->assertSame('BNM260001', DB::table('custom_form_entries')->where('id', $current)->value('candidate_id'));
        $this->assertSame('BNM270001', DB::table('custom_form_entries')->where('id', $nextYear)->value('candidate_id'));
    }

    public function test_statistics_excel_sheets_format_candidate_numbers_without_mutating_entries(): void
    {
        $entry = new CustomFormEntry;
        $entry->candidate_id = 'BNM260001';
        $exit = new CandidateExitStatistic;
        $exit->setRelation('customFormEntry', $entry);

        foreach ([CandidateEntranceStatisticsTable::class => $entry, CandidateExitStatisticsTable::class => $exit] as $table => $record) {
            $response = $table::downloadExcel([$record], ['candidate_id']);
            $path = $response->getFile()->getPathname();
            $zip = new \ZipArchive;

            try {
                $this->assertTrue($zip->open($path));
                foreach ([1, 2] as $number) {
                    $sheet = simplexml_load_string($zip->getFromName("xl/worksheets/sheet{$number}.xml"));
                    $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    $this->assertSame('BNM-0001', (string) $sheet->xpath('//s:row[@r="2"]/s:c/s:is/s:t')[0]);
                }
            } finally {
                $zip->close();
                @unlink($path);
            }
        }

        $this->assertSame('BNM260001', $entry->candidate_id);
    }

    public function test_payment_and_result_presentations_leave_canonical_candidate_numbers_unchanged(): void
    {
        $entry = new CustomFormEntry;
        $entry->id = 123;
        $entry->candidate_id = 'BNM260001';
        $entry->data = [];
        $entry->setRelation('creator', null);

        $this->assertSame('BNM-0001', $this->invoke(ExamResultsTable::class, 'candidateId', [$entry]));

        $payment = new Payment;
        $payment->custom_form_entry_id = $entry->id;
        $payment->setRelation('customFormEntry', $entry);
        $definitions = $this->invoke(PaymentsTable::class, 'exportColumnDefinitions');
        $this->assertSame('BNM-0001', $definitions['candidate_id']['value']($payment));
        $this->assertSame('BNM-0001', $definitions['candidate_id']['clean']($payment));

        $status = new PaymentStatusList;
        $status->id = 123;
        $status->candidate_id = 'BNM260001';
        $status->data = [];
        $status->setRelation('creator', null);
        $status->setRelation('customForm', null);
        foreach (['exportRow', 'cleanDataRow'] as $method) {
            $this->assertSame(['BNM-0001'], $this->invoke(PaymentStatusListsTable::class, $method, [$status, 1, ['candidate_id']]));
        }

        $this->assertSame('BNM260001', $entry->candidate_id);
        $this->assertSame('BNM260001', $status->candidate_id);
    }

    private function invoke(string $class, string $method, array $arguments = []): mixed
    {
        return (new ReflectionMethod($class, $method))->invoke(null, ...$arguments);
    }

    private function form(string $slug): int
    {
        return DB::table('custom_forms')->insertGetId([
            'name' => $slug, 'slug' => $slug, 'schema' => '[]', 'is_active' => true,
            'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function entry(int $form, string $number): int
    {
        return DB::table('custom_form_entries')->insertGetId([
            'custom_form_id' => $form, 'candidate_id' => $number, 'data' => '{}',
            'review_status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
