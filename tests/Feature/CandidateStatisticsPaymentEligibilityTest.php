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
use App\Support\WorkflowEntrySnapshot;
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
    public function test_reviewed_applications_without_payment_are_admitted_but_unreviewed_applications_are_hidden(string $menu): void
    {
        foreach ([false, true] as $hasWorkflow) {
            $form = $this->form($menu, $hasWorkflow);
            if ($hasWorkflow) {
                $this->workflow($form);
            }

            $snapshot = $this->legacySnapshot($form);
            foreach (['pending', 'draft', 'sent_back', 'rejected', 'approved', 'accepted', 'passed'] as $status) {
                $entry = $this->entry($form, snapshot: $snapshot);
                DB::table('custom_form_entries')->where('id', $entry->id)->update(['review_status' => $status]);
                $this->assertVisible($menu, $entry, in_array($status, ['approved', 'accepted', 'passed'], true));
            }
        }

        $this->assertDatabaseCount('payments', 0);
    }

    #[DataProvider('menus')]
    public function test_frozen_payment_requirement_survives_adding_and_removing_template_payment_stages(string $menu): void
    {
        $form = $this->form($menu);
        $workflow = $this->workflow($form);
        $freeEntry = $this->entry($form, snapshot: $this->legacySnapshot($form));
        $this->assertFalse($freeEntry->workflow_snapshot['requires_payment']);
        $this->assertVisible($menu, $freeEntry, true);

        $workflow->update(['stages' => array_merge($workflow->stages, [
            ['type' => 'stage', 'data' => ['stage_name' => 'Payment', 'stage_type' => 'payment', 'payment_source' => 'ams']],
        ])]);
        $paymentEntry = $this->entry($form, snapshot: $this->legacySnapshot($form));
        $this->assertTrue($paymentEntry->workflow_snapshot['requires_payment']);
        $this->assertVisible($menu, $freeEntry, true);
        $this->assertVisible($menu, $paymentEntry, false);

        $workflow->update(['stages' => $freeEntry->workflow_snapshot['stages']]);
        $newFreeEntry = $this->entry($form, snapshot: $this->legacySnapshot($form));
        $this->assertVisible($menu, $newFreeEntry, true);
        $this->assertVisible($menu, $paymentEntry, false);
        $this->payment($paymentEntry, source: 'ams');
        $this->assertVisible($menu, $paymentEntry, true);
    }

    #[DataProvider('menus')]
    public function test_legacy_null_snapshot_uses_current_workflow_or_static_form_payment_requirement(string $menu): void
    {
        $form = $this->form($menu);
        $entry = $this->entry($form);
        $this->assertNull($entry->workflow_snapshot);
        $this->assertVisible($menu, $entry, false);

        $workflow = $this->workflow($form);
        $this->assertVisible($menu, $entry, true);
        $workflow->update(['stages' => array_merge($workflow->stages, [
            ['type' => 'stage', 'data' => ['stage_name' => 'Payment', 'stage_type' => 'payment']],
        ])]);
        $this->assertVisible($menu, $entry, false);
        $form->update(['requires_payment' => false]);
        $this->assertVisible($menu, $entry, false);
        $workflow->forms()->detach($form);
        $this->assertVisible($menu, $entry, true);
    }

    #[DataProvider('menus')]
    public function test_explicit_static_snapshot_uses_form_flag_when_a_template_is_later_attached(string $menu): void
    {
        $form = $this->form($menu, false);
        $entry = $this->entry($form, snapshot: $this->legacySnapshot($form));
        $this->assertFalse($entry->workflow_snapshot['has_workflow']);
        $workflow = $this->workflow($form);
        $workflow->update(['stages' => array_merge($workflow->stages, [
            ['type' => 'stage', 'data' => ['stage_name' => 'Payment', 'stage_type' => 'payment']],
        ])]);

        $this->assertVisible($menu, $entry, true);
        $form->update(['requires_payment' => true]);
        $this->assertVisible($menu, $entry, false);
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
        $this->loginForMenu($menu);
        $page = $menu === StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS ? ListCandidateEntranceStatistics::class : ListCandidateExitStatistics::class;

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
        $this->assertExportContains($component->effects['download']['content'], ['EXPORT-PAID-ONLY'], ['EXPORT-UNPAID-HIDDEN']);
    }

    #[DataProvider('menus')]
    public function test_table_badge_and_excel_include_reviewed_free_applications_without_a_payment(string $menu): void
    {
        $this->loginForMenu($menu);
        $page = $menu === StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS ? ListCandidateEntranceStatistics::class : ListCandidateExitStatistics::class;
        $resource = $menu === StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS ? CandidateEntranceStatisticResource::class : CandidateExitStatisticResource::class;
        $form = $this->form($menu);
        $this->workflow($form);
        $free = $this->entry($form, marker: 'EXPORT-REVIEWED-FREE', snapshot: $this->legacySnapshot($form));
        $pending = $this->entry($form, marker: 'EXPORT-PENDING-FREE', snapshot: $this->legacySnapshot($form));
        $rejected = $this->entry($form, marker: 'EXPORT-REJECTED-FREE', snapshot: $this->legacySnapshot($form));
        $unpaid = $this->entry($this->form($menu), marker: 'EXPORT-REQUIRES-PAYMENT');
        DB::table('custom_form_entries')->where('id', $pending->id)->update(['review_status' => 'pending']);
        DB::table('custom_form_entries')->where('id', $rejected->id)->update(['review_status' => 'rejected']);
        $entries = collect([$free, $pending, $rejected, $unpaid]);
        $tableRecords = $menu === StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS
            ? $entries
            : CandidateExitStatistic::query()->whereIn('custom_form_entry_id', $entries->pluck('id')->all())->orderBy('id')->get();

        $this->assertSame('1', $resource::getNavigationBadge());
        $component = Livewire::test($page)
            ->assertCanSeeTableRecords([$tableRecords->first()])
            ->assertCanNotSeeTableRecords($tableRecords->skip(1))
            ->call('downloadExcelFromTableSelection', $tableRecords->map(fn ($record) => $record->getKey())->all())
            ->assertFileDownloaded();
        $this->assertExportContains($component->effects['download']['content'], ['EXPORT-REVIEWED-FREE'], [
            'EXPORT-PENDING-FREE', 'EXPORT-REJECTED-FREE', 'EXPORT-REQUIRES-PAYMENT',
        ]);
        $this->assertDatabaseCount('payments', 0);
    }

    private function assertExportContains(string $content, array $included, array $excluded): void
    {
        $path = tempnam(sys_get_temp_dir(), 'statistics-export-');
        try {
            file_put_contents($path, base64_decode($content, true));
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'] as $sheetName) {
                $sheet = $zip->getFromName($sheetName);
                $this->assertIsString($sheet);
                foreach ($included as $marker) {
                    $this->assertStringContainsString($marker, $sheet);
                }
                foreach ($excluded as $marker) {
                    $this->assertStringNotContainsString($marker, $sheet);
                }
            }
            $zip->close();
        } finally {
            unlink($path);
        }
    }

    private function loginForMenu(string $menu): void
    {
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $subject = $menu === StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS ? 'CandidateEntranceStatistic' : 'CandidateExitStatistic';
        $role = Role::findOrCreate('admin', 'web');
        foreach (['ViewAny', 'DownloadExcel'] as $action) {
            $role->givePermissionTo(Permission::findOrCreate($action.':'.$subject, 'web'));
        }
        $admin = $this->user('admin');
        $admin->assignRole($role);
        $this->actingAs($admin);
    }

    private function legacySnapshot(CustomForm $form): array
    {
        // Direct review-status fixtures represent the pre-ordered workflow contract.
        return array_replace(WorkflowEntrySnapshot::forForm($form->id), ['schema_version' => 1]);
    }

    private function workflow(CustomForm $form): WorkflowNotification
    {
        $workflow = WorkflowNotification::query()->create([
            'name' => 'Review without a payment stage',
            'stages' => [
                ['type' => 'stage', 'data' => ['stage_name' => 'Review', 'stage_type' => 'review']],
                ['type' => 'stage', 'data' => ['stage_name' => 'Completed', 'stage_type' => 'completed']],
            ],
        ]);
        $workflow->forms()->attach($form);

        return $workflow;
    }

    private function form(string $menu, bool $requiresPayment = true): CustomForm
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

    private function entry(CustomForm $form, ?User $owner = null, string $marker = 'Eligible candidate', ?array $snapshot = null): CustomFormEntry
    {
        $owner ??= $this->user();
        $entryId = DB::table('custom_form_entries')->insertGetId([
            'custom_form_id' => $form->id,
            'created_by' => $owner->id,
            'review_status' => 'accepted',
            'workflow_snapshot' => $snapshot === null ? null : json_encode($snapshot),
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
