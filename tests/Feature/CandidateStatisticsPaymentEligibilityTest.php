<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CandidateEntranceStatistics\CandidateEntranceStatisticResource;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Pages\ListCandidateEntranceStatistics;
use App\Filament\Admin\Resources\CandidateExitStatistics\CandidateExitStatisticResource;
use App\Filament\Admin\Resources\CandidateExitStatistics\Pages\ListCandidateExitStatistics;
use App\Models\AmsPaymentRequest;
use App\Models\CandidateExitStatistic;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\StatisticsMenuOptions;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CandidateStatisticsPaymentEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public static function menus(): array
    {
        return [
            'entrance' => [StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS],
            'exit' => [StatisticsMenuOptions::EXIT_EXAM_STATISTICS],
        ];
    }

    #[DataProvider('menus')]
    public function test_payment_is_required_even_when_the_form_or_template_has_no_payment_stage(string $menu): void
    {
        foreach ([false, true] as $requiresPayment) {
            $form = $this->form($menu, $requiresPayment);
            if ($requiresPayment) {
                $workflow = WorkflowNotification::query()->create([
                    'name' => 'Review without a payment stage',
                    'stages' => [
                        ['type' => 'stage', 'data' => ['stage_name' => 'Review', 'stage_type' => 'review']],
                        ['type' => 'stage', 'data' => ['stage_name' => 'Completed', 'stage_type' => 'completed']],
                    ],
                ]);
                $workflow->forms()->attach($form);
            }

            $entry = $this->entry($form);
            $this->assertVisible($menu, $entry, false);
            $this->payment($entry);
            $this->assertVisible($menu, $entry, true);
        }
    }

    #[DataProvider('menus')]
    public function test_a_payment_only_unlocks_its_exact_application_and_form(string $menu): void
    {
        $form = $this->form($menu);
        $owner = $this->user();
        $paid = $this->entry($form, $owner);
        $unpaid = $this->entry($form, $owner);
        $this->payment($paid);

        $this->assertVisible($menu, $paid, true);
        $this->assertVisible($menu, $unpaid, false);

        $wrongForm = $this->entry($form, $owner);
        $this->payment($wrongForm, formId: $this->form($menu)->id);
        $this->assertVisible($menu, $wrongForm, false);
    }

    #[DataProvider('menus')]
    public function test_a_synced_ams_request_is_hidden_until_an_actual_paid_payment_exists(string $menu): void
    {
        $entry = $this->entry($this->form($menu));
        AmsPaymentRequest::query()->create([
            'custom_form_entry_id' => $entry->id,
            'ams_request_id' => 'AMS-request-'.$entry->id,
            'sync_status' => 'synced',
            'synced_at' => now(),
        ]);

        $this->assertVisible($menu, $entry, false);
        $paymentId = $this->payment($entry, source: 'ams');
        AmsPaymentRequest::query()->where('custom_form_entry_id', $entry->id)->update([
            'local_payment_id' => $paymentId,
            'external_payment_id' => 'AMS-payment-'.$paymentId,
        ]);
        $this->assertVisible($menu, $entry, true);
    }

    #[DataProvider('menus')]
    public function test_only_the_latest_payment_status_counts_and_paid_is_case_insensitive(string $menu): void
    {
        // Legacy installations may contain several payment records for an application.
        Schema::table('payments', fn (Blueprint $table) => $table->dropUnique('payments_application_unique'));
        $entry = $this->entry($this->form($menu));
        $this->payment($entry, status: 'paid');
        $this->assertVisible($menu, $entry, true);
        $this->payment($entry, status: 'unpaid');
        $this->assertVisible($menu, $entry, false);
        $this->payment($entry, status: 'PAID', source: 'ams');
        $this->assertVisible($menu, $entry, true);
    }

    public function test_unlinked_exit_statistics_rows_are_excluded(): void
    {
        $statistic = CandidateExitStatistic::query()->create([
            'first_name_kh' => 'Unlinked application',
            'candidate_status' => 'pending',
        ]);

        $this->assertFalse(CandidateExitStatisticResource::getEloquentQuery()->whereKey($statistic)->exists());
    }

    #[DataProvider('menus')]
    public function test_legacy_payment_schema_requires_the_same_form_and_owner(string $menu): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropUnique('payments_application_unique'));
        Schema::table('payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('custom_form_entry_id'));
        $form = $this->form($menu);
        $owner = $this->user();
        $entry = $this->entry($form, $owner);
        $otherOwner = $this->entry($form);
        $otherForm = $this->entry($this->form($menu), $owner);
        DB::table('payments')->insert([
            'users_id' => $owner->id,
            'form_id' => $form->id,
            'status_payt' => 'paid',
        ]);

        $this->assertVisible($menu, $entry, true);
        $this->assertVisible($menu, $otherOwner, false);
        $this->assertVisible($menu, $otherForm, false);
    }

    #[DataProvider('menus')]
    public function test_paid_records_still_obey_existing_hidden_flags(string $menu): void
    {
        $entry = $this->entry($this->form($menu));
        $this->payment($entry);
        $this->assertVisible($menu, $entry, true);

        if ($menu === StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS) {
            $entry->forceFill(['data' => array_merge($entry->data, [CandidateEntranceStatisticResource::HIDDEN_FLAG => true])])->saveQuietly();
        } else {
            CandidateExitStatistic::query()->where('custom_form_entry_id', $entry->id)->update(['hidden_from_statistics' => true]);
        }

        $this->assertVisible($menu, $entry, false);
    }

    #[DataProvider('menus')]
    public function test_excel_selection_cannot_export_an_unpaid_application(string $menu): void
    {
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $subject = $menu === StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS ? 'CandidateEntranceStatistic' : 'CandidateExitStatistic';
        $page = $menu === StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS ? ListCandidateEntranceStatistics::class : ListCandidateExitStatistics::class;
        $role = Role::findOrCreate('admin', 'web');
        foreach (['ViewAny', 'DownloadExcel'] as $action) {
            $role->givePermissionTo(Permission::findOrCreate($action.':'.$subject, 'web'));
        }
        $admin = $this->user('admin');
        $admin->assignRole($role);
        $this->actingAs($admin);

        $form = $this->form($menu);
        $paid = $this->entry($form, marker: 'EXPORT-PAID-ONLY');
        $unpaid = $this->entry($form, marker: 'EXPORT-UNPAID-HIDDEN');
        $tableRecords = $menu === StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS
            ? collect([$paid, $unpaid])
            : CandidateExitStatistic::query()->whereIn('custom_form_entry_id', [$paid->id, $unpaid->id])->orderBy('id')->get();
        $component = Livewire::test($page)->assertCanNotSeeTableRecords($tableRecords);

        $this->payment($paid);
        $component->call('$refresh')
            ->assertCanSeeTableRecords([$tableRecords->first()])
            ->assertCanNotSeeTableRecords([$tableRecords->last()])
            ->call('downloadExcelFromTableSelection', $tableRecords->map(fn ($record) => $record->getKey())->all())
            ->assertFileDownloaded();
        $path = tempnam(sys_get_temp_dir(), 'paid-statistics-export-');
        try {
            file_put_contents($path, base64_decode($component->effects['download']['content'], true));
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'] as $sheetName) {
                $sheet = $zip->getFromName($sheetName);
                $this->assertIsString($sheet);
                $this->assertStringContainsString('EXPORT-PAID-ONLY', $sheet);
                $this->assertStringNotContainsString('EXPORT-UNPAID-HIDDEN', $sheet);
            }
            $zip->close();
        } finally {
            unlink($path);
        }
    }

    private function form(string $menu, bool $requiresPayment = false): CustomForm
    {
        return CustomForm::query()->create([
            'name' => 'Statistics payment fixture',
            'slug' => 'statistics-payment-'.uniqid(),
            'is_active' => true,
            'requires_payment' => $requiresPayment,
            'statistics_menu' => $menu,
        ]);
    }

    private function user(string $type = 'student'): User
    {
        $name = 'statistics_payment_'.uniqid();

        return User::query()->create([
            'registration_type' => $type,
            'name' => $name,
            'username' => $name,
            'email' => $name.'@example.test',
            'password' => 'password',
            'date_of_birth' => '2000-01-01',
            'is_active' => true,
        ]);
    }

    private function entry(CustomForm $form, ?User $owner = null, string $marker = 'Eligible candidate'): CustomFormEntry
    {
        $owner ??= $this->user();
        $entryId = DB::table('custom_form_entries')->insertGetId([
            'custom_form_id' => $form->id,
            'created_by' => $owner->id,
            'review_status' => 'accepted',
            'data' => json_encode(['candidate_status' => 'pending', 'first_name_kh' => $marker]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($form->statistics_menu === StatisticsMenuOptions::EXIT_EXAM_STATISTICS) {
            CandidateExitStatistic::query()->create([
                'custom_form_entry_id' => $entryId,
                'first_name_kh' => $marker,
                'candidate_status' => 'pending',
            ]);
        }

        return CustomFormEntry::query()->findOrFail($entryId);
    }

    private function payment(CustomFormEntry $entry, string $status = 'paid', ?string $source = null, ?int $formId = null): int
    {
        return DB::table('payments')->insertGetId([
            'users_id' => $entry->created_by,
            'form_id' => $formId ?? $entry->custom_form_id,
            'custom_form_entry_id' => $entry->id,
            'status_payt' => $status,
            'integration_source' => $source,
            'integration_payment_id' => $source === 'ams' ? uniqid('ams-') : null,
            'amount_kh' => 120000,
            'datetime_pay' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertVisible(string $menu, CustomFormEntry $entry, bool $expected): void
    {
        $visible = $menu === StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS
            ? CandidateEntranceStatisticResource::getEloquentQuery()->whereKey($entry->id)->exists()
            : CandidateExitStatisticResource::getEloquentQuery()->where('custom_form_entry_id', $entry->id)->exists();

        $this->assertSame($expected, $visible, $menu.' application '.$entry->id);
    }
}
