<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CandidateEntranceStatistics\CandidateEntranceStatisticResource;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Pages\ListCandidateEntranceStatistics;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Tables\CandidateEntranceStatisticsTable;
use App\Filament\Admin\Resources\CandidateExitStatistics\CandidateExitStatisticResource;
use App\Filament\Admin\Resources\CandidateExitStatistics\Pages\ListCandidateExitStatistics;
use App\Filament\Admin\Resources\CandidateExitStatistics\Tables\CandidateExitStatisticsTable;
use App\Models\CandidateExitStatistic;
use App\Models\Major;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Services\CandidateStatisticsImportService;
use App\Support\CandidateApplicationContext;
use App\Support\CandidateStatisticsSynchronizer;
use App\Support\FormEntryData;
use App\Support\SpreadsheetRowReader;
use App\Support\StatisticsMenuOptions;
use App\Support\WorkflowEntrySnapshot;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use ZipArchive;

class CandidateStatisticsImportTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Role $role;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['fieldOptionCache', 'majorIdLabelCache'] as $property) {
            (new \ReflectionProperty(FormEntryData::class, $property))->setValue(null, []);
        }
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $this->role = Role::findOrCreate('statistics_import_operator', 'web');
        $this->operator = User::query()->create([
            'registration_type' => 'admin', 'name' => 'Import operator', 'username' => 'statistics_import_operator',
            'email' => 'statistics-import@example.test', 'password' => 'password',
            'date_of_birth' => '1990-01-01', 'is_active' => true,
        ]);
        $this->operator->assignRole($this->role);
        $this->actingAs($this->operator);
    }

    protected function tearDown(): void
    {
        foreach (['fieldOptionCache', 'majorIdLabelCache'] as $property) {
            (new \ReflectionProperty(FormEntryData::class, $property))->setValue(null, []);
        }
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public static function menus(): array
    {
        return ['entrance' => ['entrance'], 'exit' => ['exit']];
    }

    public static function localizedMenus(): array
    {
        return [
            'entrance English' => ['entrance', 'en'],
            'entrance Khmer' => ['entrance', 'km'],
            'exit English' => ['exit', 'en'],
            'exit Khmer' => ['exit', 'km'],
        ];
    }

    #[DataProvider('menus')]
    public function test_import_marks_existing_candidates_passed_and_reimport_does_not_repeat_the_workflow(string $menu): void
    {
        [$resource] = $this->grantImport($menu);
        [$entry] = $this->entry($menu);
        $snapshot = $entry->workflow_snapshot;
        $file = $this->csv([['MED-0001', 'SOK DARA', '2000-01-02']]);

        $result = app(CandidateStatisticsImportService::class)->import($file, $resource);

        $this->assertEquals(['passed' => 1, 'already_passed' => 0, 'total' => 1], $result);
        $this->assertSame('passed', data_get($entry->fresh()->data, 'candidate_status'));
        $this->assertTrue(data_get($entry->fresh()->data, 'workflow_progress.result_passed'));
        $this->assertSame($snapshot, $entry->fresh()->workflow_snapshot);
        $this->assertDatabaseCount('custom_form_entries', 1);
        if ($menu === 'exit') {
            $this->assertDatabaseHas('candidate_exit_statistics', ['custom_form_entry_id' => $entry->id, 'candidate_status' => 'passed']);
        }
        $after = $entry->fresh()->getRawOriginal();
        $notifications = DB::table('notifications')->count();

        $this->assertEquals(['passed' => 0, 'already_passed' => 1, 'total' => 1], app(CandidateStatisticsImportService::class)->import($file, $resource));
        $this->assertSame($after, $entry->fresh()->getRawOriginal());
        $this->assertSame($notifications, DB::table('notifications')->count());
    }

    #[DataProvider('localizedMenus')]
    public function test_existing_downloaded_excel_can_be_imported_in_both_languages_without_importing_its_second_sheet(string $menu, string $locale): void
    {
        [$resource, , $table] = $this->grantImport($menu);
        [$entry, $row] = $this->entry($menu);
        app()->setLocale($locale);
        $response = $table::downloadExcel([$row]);
        $path = $response->getFile()->getPathname();
        app()->setLocale($locale === 'en' ? 'km' : 'en');

        try {
            $result = app(CandidateStatisticsImportService::class)->import(new UploadedFile(
                $path, 'passed-candidates.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true,
            ), $resource);

            $this->assertEquals(['passed' => 1, 'already_passed' => 0, 'total' => 1], $result);
            $this->assertSame('passed', data_get($entry->fresh()->data, 'candidate_status'));
        } finally {
            @unlink($path);
        }
    }

    #[DataProvider('menus')]
    public function test_database_export_template_accepts_all_fourteen_raw_columns_and_pending_metadata(string $menu): void
    {
        [, $page, $table] = $this->grantImport($menu);
        $major = Major::query()->create([
            'key' => 'medicine', 'abbreviation' => 'MED', 'name_en' => 'Medicine',
            'name_kh' => 'វេជ្ជសាស្ត្រ', 'is_active' => true,
        ]);
        $type = $menu === 'exit'
            ? 'national_exit_exam_application_bachelor'
            : 'national_entrance_exam_application_bachelor';
        [$entry, $record] = $this->entry($menu, identity: [
            CandidateApplicationContext::DATA_KEY => $type,
            'major' => (string) $major->id,
            'academic_year' => '-',
        ]);
        app()->setLocale('km');
        $definitions = $table::exportColumnDefinitions();
        $headers = array_column($definitions, 'field_key');
        $this->assertSame([
            'row_number', 'candidate_id', 'form_type', 'academic_year', 'user_type', 'seat_number',
            'first_name_kh', 'last_name_kh', 'first_name_en', 'last_name_en',
            'gender', 'major', 'date_of_birth', 'candidate_status',
        ], $headers);
        $values = array_values(array_map(fn (array $definition): string => $definition['clean']($record, 1), $definitions));
        $row = array_combine($headers, $values);
        $this->assertSame('', $row['form_type']);
        $this->assertSame('-', $row['academic_year']);
        $this->assertSame($type, $row['user_type']);
        $this->assertSame((string) $major->id, $row['major']);
        $this->assertSame('០២-មករា-២០០០', $row['date_of_birth']);
        $this->assertSame('pending', $row['candidate_status']);
        $response = $table::downloadExcel([$record]);
        $path = $response->getFile()->getPathname();
        app()->setLocale('en');

        try {
            // Automatically prefer Database Export over the unedited display sheet.
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path));
            $firstSheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->addFromString('xl/worksheets/sheet1.xml', str_replace('MED-0001', 'WRONG-FIRST-SHEET', $firstSheet));
            $zip->close();

            Livewire::test($page)
                ->mountAction('import_excel')
                ->assertFormFieldDoesNotExist('worksheet')
                ->fillForm(['file' => UploadedFile::fake()->createWithContent('passed-candidates.xlsx', file_get_contents($path))
                    ->mimeType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')])
                ->callMountedAction()
                ->assertHasNoActionErrors()
                ->assertNotified(__('candidate_statistics_import.completed_title'));
        } finally {
            @unlink($path);
        }

        $this->assertSame('passed', data_get($entry->fresh()->data, 'candidate_status'));
        $this->assertNotEmpty(data_get($entry->fresh()->data, 'candidate_reviewed_at'));
    }

    #[DataProvider('menus')]
    public function test_statistics_names_remain_separate_in_the_table_and_downloaded_sheets(string $menu): void
    {
        [, $page, $table] = $this->grantImport($menu);
        $parts = [
            'first_name_kh' => 'សុខ វ៉ាន់', 'last_name_kh' => 'ដារា វីរៈ',
            'first_name_en' => 'SOK VAN', 'last_name_en' => 'DARA VIREAK',
        ];
        [, $record] = $this->entry($menu, identity: $parts);
        $component = Livewire::test($page)
            ->assertTableColumnDoesNotExist('name_khmer')
            ->assertTableColumnDoesNotExist('name_latin');
        foreach ($parts as $field => $value) {
            $component->assertTableColumnExists($field)
                ->assertTableColumnVisible($field)
                ->assertTableColumnStateSet($field, $value, $record);
        }

        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $response = $table::downloadExcel([$record], array_keys($parts));
            $path = $response->getFile()->getPathname();
            try {
                $file = new UploadedFile($path, 'separate-names.xlsx', null, null, true);
                $rawRows = app(SpreadsheetRowReader::class)->read($file, 'Database Export');
                $this->assertSame(array_keys($parts), array_values($rawRows[1]));
                $this->assertSame(array_values($parts), array_values($rawRows[2]));
                $displayRows = app(SpreadsheetRowReader::class)->read($file);
                $this->assertSame([
                    __('exam_results.first_name_khmer'), __('exam_results.last_name_khmer'),
                    __('exam_results.first_name_latin'), __('exam_results.last_name_latin'),
                ], array_values($displayRows[1]));
                $this->assertSame(array_values($parts), array_values($displayRows[2]));
            } finally {
                @unlink($path);
            }
        }
    }

    #[DataProvider('menus')]
    public function test_a_wrong_individual_name_part_rejects_the_entire_import_batch(string $menu): void
    {
        [$resource] = $this->grantImport($menu);
        [$first] = $this->entry($menu);
        [$second] = $this->entry($menu, candidateId: 'MED260002');
        $before = [$first->getRawOriginal(), $second->getRawOriginal()];
        $file = UploadedFile::fake()->createWithContent('separate-names.csv',
            "candidate_id,first_name_en,last_name_en,date_of_birth\n"
            ."MED-0001,SOK,DARA,2000-01-02\n"
            ."MED-0002,SOK,SOMEONE ELSE,2000-01-02\n"
        );

        $this->assertInvalidImport($resource, $file);

        $this->assertSame($before, [$first->fresh()->getRawOriginal(), $second->fresh()->getRawOriginal()]);
    }

    #[DataProvider('menus')]
    public function test_invalid_identity_in_any_row_rejects_the_entire_batch_without_changes_or_notifications(string $menu): void
    {
        [$resource] = $this->grantImport($menu);
        [$first] = $this->entry($menu);
        [$second] = $this->entry($menu, candidateId: 'MED260002');
        $before = [$first->getRawOriginal(), $second->getRawOriginal()];
        $notifications = DB::table('notifications')->count();

        $this->assertInvalidImport($resource, $this->csv([
            ['MED-0001', 'SOK DARA', '2000-01-02'],
            ['MED-0002', 'SOMEONE ELSE', '2000-01-02'],
        ]));

        $this->assertSame($before, [$first->fresh()->getRawOriginal(), $second->fresh()->getRawOriginal()]);
        $this->assertSame($notifications, DB::table('notifications')->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    #[DataProvider('menus')]
    public function test_duplicate_uploaded_rows_are_rejected_instead_of_repeating_a_result_change(string $menu): void
    {
        [$resource] = $this->grantImport($menu);
        [$entry] = $this->entry($menu);
        $this->assertInvalidImport($resource, $this->csv([
            ['MED-0001', 'SOK DARA', '2000-01-02'],
            ['MED-0001', 'SOK DARA', '2000-01-02'],
        ]));
        $this->assertSame('pending', data_get($entry->fresh()->data, 'candidate_status'));
    }

    #[DataProvider('menus')]
    public function test_unique_upload_toggle_can_allow_duplicate_rows_without_repeating_the_workflow(string $menu): void
    {
        [, $page] = $this->grantImport($menu);
        [$entry] = $this->entry($menu);
        $snapshot = $entry->workflow_snapshot;
        $file = $this->csv([
            ['MED-0001', 'SOK DARA', '2000-01-02'],
            ['MED-0001', 'SOK DARA', '2000-01-02'],
        ])->mimeType('text/plain');

        $component = Livewire::test($page)->mountAction('import_excel')
            ->fillForm(['file' => $file])
            ->assertFormFieldExists('unique')
            ->assertSet('mountedActions.0.data.unique', true)
            ->callMountedAction()
            ->assertHasActionErrors(['file'])
            ->assertActionMounted('import_excel');
        $this->assertSame('pending', data_get($entry->fresh()->data, 'candidate_status'));

        $component->set('mountedActions.0.data.unique', false)
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified(__('candidate_statistics_import.completed_title'));

        $this->assertSame('passed', data_get($entry->fresh()->data, 'candidate_status'));
        $this->assertTrue(data_get($entry->fresh()->data, 'workflow_progress.result_passed'));
        $this->assertSame($snapshot, $entry->fresh()->workflow_snapshot);
    }

    #[DataProvider('menus')]
    public function test_non_unique_import_counts_rows_but_changes_and_counts_each_candidate_only_once(string $menu): void
    {
        [$resource] = $this->grantImport($menu);
        $this->operator->assignRole(Role::findOrCreate('registrar', 'web'));
        [$first] = $this->entry($menu);
        [$second] = $this->entry($menu, candidateId: 'MED260002');
        $file = $this->csv([
            ['MED-0001', 'SOK DARA', '2000-01-02'],
            ['MED260001', 'SOK DARA', '2000-01-02'],
            ['MED-0002', 'SOK DARA', '2000-01-02'],
            ['MED-0002', 'SOK DARA', '2000-01-02'],
        ]);
        $service = app(CandidateStatisticsImportService::class);

        $this->assertEquals(['passed' => 2, 'already_passed' => 0, 'total' => 4], $service->import($file, $resource, unique: false));
        $this->assertSame('passed', data_get($first->fresh()->data, 'candidate_status'));
        $this->assertSame('passed', data_get($second->fresh()->data, 'candidate_status'));
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'passed')->count());
        $before = [$first->fresh()->getRawOriginal(), $second->fresh()->getRawOriginal()];
        $notifications = DB::table('notifications')->count();

        $this->assertEquals(['passed' => 0, 'already_passed' => 2, 'total' => 4], $service->import($file, $resource, unique: false));
        $this->assertSame($before, [$first->fresh()->getRawOriginal(), $second->fresh()->getRawOriginal()]);
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'passed')->count());
        $this->assertSame($notifications, DB::table('notifications')->count());
    }

    #[DataProvider('menus')]
    public function test_non_unique_import_still_validates_every_repeated_row_before_any_result_change(string $menu): void
    {
        [$resource] = $this->grantImport($menu);
        [$first] = $this->entry($menu);
        [$second] = $this->entry($menu, candidateId: 'MED260002');
        $before = [$first->getRawOriginal(), $second->getRawOriginal()];
        $notifications = DB::table('notifications')->count();

        $this->assertInvalidSelectedImport($resource, $this->csv([
            ['MED-0001', 'SOK DARA', '2000-01-02'],
            ['MED-0002', 'SOK DARA', '2000-01-02'],
            ['MED-0001', 'SOMEONE ELSE', '2000-01-02'],
        ]), ['name_latin', 'date_of_birth'], unique: false);

        $this->assertSame($before, [$first->fresh()->getRawOriginal(), $second->fresh()->getRawOriginal()]);
        $this->assertSame($notifications, DB::table('notifications')->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    #[DataProvider('menus')]
    public function test_non_unique_import_does_not_bypass_ambiguous_matches_or_workflow_permissions(string $menu): void
    {
        [$resource] = $this->grantImport($menu);
        [$first] = $this->entry($menu);
        [$ambiguous] = $this->entry($menu);
        [$unauthorized] = $this->entry($menu, candidateId: 'MED260002', responsibleRole: 'another_unique_import_operator');
        $before = [$first->getRawOriginal(), $ambiguous->getRawOriginal(), $unauthorized->getRawOriginal()];

        foreach (['MED-0001', 'MED-0002', 'UNKNOWN'] as $candidateId) {
            $this->assertInvalidSelectedImport($resource, $this->columnCsv(['candidate_id'], [[$candidateId], [$candidateId]]), [], unique: false);
        }

        $this->assertSame($before, [$first->fresh()->getRawOriginal(), $ambiguous->fresh()->getRawOriginal(), $unauthorized->fresh()->getRawOriginal()]);
    }

    #[DataProvider('menus')]
    public function test_unique_checks_each_enabled_field_and_ignores_fields_that_are_disabled(string $menu): void
    {
        [$resource] = $this->grantImport($menu);
        [$first] = $this->entry($menu);
        [$second] = $this->entry($menu, candidateId: 'MED260002');
        $before = [$first->getRawOriginal(), $second->getRawOriginal()];
        $file = $this->columnCsv(['candidate_id', 'gender'], [['MED-0001', 'male'], ['MED-0002', 'male']]);

        try {
            app(CandidateStatisticsImportService::class)->import($file, $resource, validationColumns: ['gender']);
            $this->fail('Unique must reject a duplicate enabled field even when candidate IDs are different.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
            $this->assertStringContainsString(__('exam_results.gender'), implode(' ', $exception->errors()['file']));
        }

        $this->assertSame($before, [$first->fresh()->getRawOriginal(), $second->fresh()->getRawOriginal()]);
        $this->assertEquals(['passed' => 2, 'already_passed' => 0, 'total' => 2], app(CandidateStatisticsImportService::class)->import($file, $resource, validationColumns: []));
    }

    #[DataProvider('menus')]
    public function test_turning_unique_off_in_the_modal_allows_shared_field_values_for_different_candidates(string $menu): void
    {
        [, $page] = $this->grantImport($menu);
        [$first] = $this->entry($menu);
        [$second] = $this->entry($menu, candidateId: 'MED260002');
        $file = $this->columnCsv(['candidate_id', 'gender'], [['MED-0001', 'male'], ['MED-0002', 'male']]);

        $component = Livewire::test($page)->mountAction('import_excel')
            ->fillForm(['file' => $file])
            ->callMountedAction()
            ->assertHasActionErrors(['file'])
            ->assertActionMounted('import_excel');
        $this->assertSame('pending', data_get($first->fresh()->data, 'candidate_status'));
        $this->assertSame('pending', data_get($second->fresh()->data, 'candidate_status'));

        $component->set('mountedActions.0.data.unique', false)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('passed', data_get($first->fresh()->data, 'candidate_status'));
        $this->assertSame('passed', data_get($second->fresh()->data, 'candidate_status'));
    }

    #[DataProvider('menus')]
    public function test_unique_normalizes_case_whitespace_khmer_digits_dates_and_localized_values(string $menu): void
    {
        [$resource, , $table] = $this->grantImport($menu);
        [$first] = $this->entry($menu, identity: ['seat_number' => '123']);
        [$second] = $this->entry($menu, candidateId: 'MED260002', identity: ['seat_number' => '123']);
        $before = [$first->getRawOriginal(), $second->getRawOriginal()];
        $definitions = collect($table::exportColumnDefinitions())->keyBy('field_key');

        foreach ([
            ['first_name_en', '  SoK  ', 'sok'],
            ['seat_number', '១២៣', '123'],
            ['date_of_birth', '2000-01-02', '០២-មករា-២០០០'],
            ['gender', 'male', 'ប្រុស'],
        ] as [$field, $firstValue, $secondValue]) {
            $file = $this->columnCsv(['candidate_id', $field], [['MED-0001', $firstValue], ['MED-0002', $secondValue]]);
            try {
                app(CandidateStatisticsImportService::class)->import($file, $resource, validationColumns: [$field]);
                $this->fail('Equivalent values in '.$field.' must fail uniqueness.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('file', $exception->errors());
                $this->assertStringContainsString($definitions[$field]['label'], implode(' ', $exception->errors()['file']));
            }
        }

        $this->assertSame($before, [$first->fresh()->getRawOriginal(), $second->fresh()->getRawOriginal()]);
    }

    #[DataProvider('menus')]
    public function test_unique_ignores_empty_optional_values(string $menu): void
    {
        [$resource] = $this->grantImport($menu);
        [$first] = $this->entry($menu);
        [$second] = $this->entry($menu, candidateId: 'MED260002');
        $file = $this->columnCsv(['candidate_id', 'major'], [['MED-0001', ''], ['MED-0002', '-']]);

        $this->assertEquals(['passed' => 2, 'already_passed' => 0, 'total' => 2], app(CandidateStatisticsImportService::class)->import($file, $resource, validationColumns: ['major']));
        $this->assertSame('passed', data_get($first->fresh()->data, 'candidate_status'));
        $this->assertSame('passed', data_get($second->fresh()->data, 'candidate_status'));
    }

    #[DataProvider('menus')]
    public function test_unique_accepts_an_upload_when_all_enabled_nonempty_field_values_are_distinct(string $menu): void
    {
        [$resource, , $table] = $this->grantImport($menu);
        [$first, $firstRecord] = $this->entry($menu);
        [$second, $secondRecord] = $this->entry($menu, candidateId: 'MED260002', identity: [
            'first_name_kh' => 'ចាន់', 'last_name_kh' => 'សុភ័ក្ត្រ',
            'first_name_en' => 'CHAN', 'last_name_en' => 'SOPHEAK',
            'gender' => 'female', 'date_of_birth' => '2001-02-03', 'academic_year' => '2027-2028',
        ]);
        $definitions = $table::exportColumnDefinitions();
        $headers = array_column($definitions, 'field_key');
        $values = fn ($record, int $position): array => array_values(array_map(fn (array $definition): string => $definition['clean']($record, $position), $definitions));
        $file = $this->columnCsv($headers, [$values($firstRecord, 1), $values($secondRecord, 2)]);
        $selection = array_values(array_diff($headers, ['row_number', 'candidate_status']));

        $this->assertEquals(['passed' => 2, 'already_passed' => 0, 'total' => 2], app(CandidateStatisticsImportService::class)->import($file, $resource, validationColumns: $selection));
        $this->assertSame('passed', data_get($first->fresh()->data, 'candidate_status'));
        $this->assertSame('passed', data_get($second->fresh()->data, 'candidate_status'));
    }

    #[DataProvider('menus')]
    public function test_ambiguous_visible_candidate_numbers_do_not_update_multiple_applications(string $menu): void
    {
        [$resource] = $this->grantImport($menu);
        [$first] = $this->entry($menu);
        [$second] = $this->entry($menu);

        $this->assertInvalidImport($resource, $this->csv([['MED-0001', 'SOK DARA', '2000-01-02']]));

        $this->assertSame('pending', data_get($first->fresh()->data, 'candidate_status'));
        $this->assertSame('pending', data_get($second->fresh()->data, 'candidate_status'));
    }

    #[DataProvider('menus')]
    public function test_import_permission_is_required_in_the_ui_and_the_service(string $menu): void
    {
        [$resource, $page] = $this->menu($menu);
        $this->grant($resource, 'ViewAny');
        [$entry] = $this->entry($menu);
        Livewire::test($page)->assertActionHidden('import_excel');

        try {
            app(CandidateStatisticsImportService::class)->import($this->csv([['MED-0001', 'SOK DARA', '2000-01-02']]), $resource);
            $this->fail('Calling the import service must not bypass its permission.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame('pending', data_get($entry->fresh()->data, 'candidate_status'));
        $this->grant($resource, 'ImportExcel');
        Livewire::test($page)->assertActionVisible('import_excel');
    }

    #[DataProvider('menus')]
    public function test_authorized_upload_action_passes_matching_candidates_and_refreshes_the_list(string $menu): void
    {
        [, $page] = $this->grantImport($menu);
        [$entry, $row] = $this->entry($menu);
        $file = $this->csv([['MED-0001', 'SOK DARA', '2000-01-02']])->mimeType('text/plain');

        Livewire::test($page)
            ->assertCanSeeTableRecords([$row])
            ->callAction('import_excel', data: ['file' => $file])
            ->assertHasNoActionErrors()
            ->assertNotified(__('candidate_statistics_import.completed_title'));

        $this->assertSame('passed', data_get($entry->fresh()->data, 'candidate_status'));
    }

    #[DataProvider('menus')]
    public function test_invalid_upload_shows_its_row_error_in_the_modal_and_can_be_corrected(string $menu): void
    {
        [, $page] = $this->grantImport($menu);
        [$entry] = $this->entry($menu);
        $before = $entry->getRawOriginal();
        $message = __('candidate_statistics_import.errors.mismatch', [
            'row' => 2,
            'field' => __('exam_results.name_latin'),
        ]);

        $component = Livewire::test($page)
            ->callAction('import_excel', data: [
                'file' => $this->csv([['MED-0001', 'SOMEONE ELSE', '2000-01-02']])->mimeType('text/plain'),
            ])
            ->assertHasActionErrors(['file'])
            ->assertActionMounted('import_excel')
            ->call('forceRender')
            ->assertSee($message);

        $this->assertSame($before, $entry->fresh()->getRawOriginal());

        $component->fillForm(['file' => []])->fillForm([
            'file' => $this->csv([['MED-0001', 'SOK DARA', '2000-01-02']])->mimeType('text/plain'),
        ])->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertActionNotMounted('import_excel')
            ->assertNotified(__('candidate_statistics_import.completed_title'));

        $this->assertSame('passed', data_get($entry->fresh()->data, 'candidate_status'));
    }

    #[DataProvider('menus')]
    public function test_import_grants_cannot_override_a_different_responsible_workflow_role(string $menu): void
    {
        [$resource] = $this->grantImport($menu);
        $this->grant($resource, 'Passed');
        $this->grant($resource, 'BulkPassed');
        [$allowed] = $this->entry($menu);
        [$wrong] = $this->entry($menu, candidateId: 'MED260002', responsibleRole: 'different_import_operator');

        $this->assertInvalidImport($resource, $this->csv([
            ['MED-0001', 'SOK DARA', '2000-01-02'],
            ['MED-0002', 'SOK DARA', '2000-01-02'],
        ]));

        $this->assertSame('pending', data_get($allowed->fresh()->data, 'candidate_status'));
        $this->assertSame('pending', data_get($wrong->fresh()->data, 'candidate_status'));
    }

    #[DataProvider('menus')]
    public function test_incomplete_identity_headers_and_unknown_candidates_are_rejected(string $menu): void
    {
        [$resource] = $this->grantImport($menu);
        [$entry] = $this->entry($menu);

        foreach ([
            "candidate_id,date_of_birth\nMED-0001,2000-01-02\n",
            "candidate_id,name_latin\nMED-0001,SOK DARA\n",
            "candidate_id,name_latin,date_of_birth\nUNKNOWN,SOK DARA,2000-01-02\n",
            "candidate_id,name_latin,date_of_birth\nMED-0001,SOK DARA,invalid-date\n",
        ] as $content) {
            $this->assertInvalidImport($resource, UploadedFile::fake()->createWithContent('passed.csv', $content));
        }

        $this->assertSame('pending', data_get($entry->fresh()->data, 'candidate_status'));
        $this->assertDatabaseCount('custom_form_entries', 1);
    }

    #[DataProvider('menus')]
    public function test_uploaded_columns_generate_exactly_matching_validation_toggles_and_replacing_the_file_resets_them(string $menu): void
    {
        [, $page] = $this->grantImport($menu);
        $this->entry($menu);
        $six = ['candidate_id', 'first_name_en', 'last_name_en', 'date_of_birth', 'gender', 'major'];
        $ten = [...$six, 'row_number', 'candidate_status', 'academic_year', 'External notes'];
        $component = Livewire::test($page)->mountAction('import_excel')
            ->fillForm(['file' => $this->columnCsv($six, [['MED-0001', 'SOK', 'DARA', '2000-01-02', 'male', '']])]);

        $this->assertCount(6, $this->validationToggles($component));
        $this->assertSame($six, array_column($component->get('mountedActions.0.data.detected_columns'), 'key'));
        $component->set('mountedActions.0.data.unique', false)
            ->set('mountedActions.0.data.validation_columns.gender', false)
            ->fillForm(['file' => []])
            ->fillForm(['file' => $this->columnCsv($ten, [['MED-0001', 'SOK', 'DARA', '2000-01-02', 'male', '', '1', 'pending', '2026-2027', 'Reference only']])]);

        $toggles = $this->validationToggles($component);
        $this->assertCount(10, $toggles);
        $component->assertSet('mountedActions.0.data.unique', true)
            ->assertSet('mountedActions.0.data.validation_columns.gender', true)
            ->assertSet('mountedActions.0.data.validation_columns.row_number', false)
            ->assertSet('mountedActions.0.data.validation_columns.candidate_status', false);
        $columns = $component->get('mountedActions.0.data.detected_columns');
        $unknown = collect($columns)->firstWhere('header', 'External notes');
        $this->assertNotNull($unknown);
        $this->assertFalse($unknown['supported']);
        $component->assertSet('mountedActions.0.data.validation_columns.'.$unknown['key'], false);
        $this->assertTrue(collect($toggles)->first(fn (Toggle $toggle): bool => str_ends_with($toggle->getStatePath(), '.candidate_id'))->isDisabled());
        $this->assertTrue(collect($toggles)->first(fn (Toggle $toggle): bool => str_ends_with($toggle->getStatePath(), '.'.$unknown['key']))->isDisabled());

        $component->fillForm(['file' => []]);
        $this->assertCount(0, $this->validationToggles($component));
        $component->assertSet('mountedActions.0.data.validation_columns', []);
    }

    #[DataProvider('menus')]
    public function test_only_the_full_export_template_hides_metadata_toggles_and_custom_files_keep_every_column(string $menu): void
    {
        [, $page, $table] = $this->grantImport($menu);
        [, $record] = $this->entry($menu);
        $definitions = $table::exportColumnDefinitions();
        $headers = array_column($definitions, 'field_key');
        $this->assertCount(14, $headers);
        $this->assertContains('row_number', $headers);
        $this->assertContains('candidate_status', $headers);
        $response = $table::downloadExcel([$record]);
        $path = $response->getFile()->getPathname();

        try {
            $component = Livewire::test($page)->mountAction('import_excel')
                ->assertFormFieldDoesNotExist('worksheet')
                ->fillForm(['file' => UploadedFile::fake()->createWithContent('full-export.xlsx', file_get_contents($path))
                    ->mimeType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')]);
            $this->assertCount(12, $this->validationToggles($component));
            $this->assertSame(
                array_values(array_diff($headers, ['row_number', 'candidate_status'])),
                array_column($component->get('mountedActions.0.data.detected_columns'), 'key'),
            );
            $selection = $component->get('mountedActions.0.data.validation_columns');
            $this->assertArrayNotHasKey('row_number', $selection);
            $this->assertArrayNotHasKey('candidate_status', $selection);

            $unknownHeaders = array_map(fn (int $number): string => 'External note '.$number, range(1, 6));
            $values = array_values(array_map(fn (array $definition): string => $definition['clean']($record, 1), $definitions));
            $component->fillForm(['file' => []])
                ->fillForm(['file' => $this->columnCsv(
                    [...$headers, ...$unknownHeaders],
                    [[...$values, ...array_fill(0, 6, 'Reference only')]],
                )]);

            $toggles = $this->validationToggles($component);
            $columns = $component->get('mountedActions.0.data.detected_columns');
            $this->assertCount(20, $toggles);
            $this->assertCount(20, $columns);
            foreach (['row_number', 'candidate_status'] as $field) {
                $this->assertContains($field, array_column($columns, 'key'));
                $component->assertSet('mountedActions.0.data.validation_columns.'.$field, false);
                $this->assertFalse(collect($toggles)->first(fn (Toggle $toggle): bool => str_ends_with($toggle->getStatePath(), '.'.$field))->isDisabled());
            }
            foreach ($unknownHeaders as $header) {
                $unknown = collect($columns)->firstWhere('header', $header);
                $this->assertNotNull($unknown);
                $this->assertFalse($unknown['supported']);
                $component->assertSet('mountedActions.0.data.validation_columns.'.$unknown['key'], false);
                $this->assertTrue(collect($toggles)->first(fn (Toggle $toggle): bool => str_ends_with($toggle->getStatePath(), '.'.$unknown['key']))->isDisabled());
            }
        } finally {
            @unlink($path);
        }
    }

    #[DataProvider('menus')]
    public function test_replacing_workbooks_automatically_selects_their_data_sheet_and_rebuilds_validation_toggles(string $menu): void
    {
        [, $page] = $this->grantImport($menu);
        $this->entry($menu);
        $six = ['candidate_id', 'first_name_en', 'last_name_en', 'date_of_birth', 'gender', 'major'];
        $ten = [...$six, 'row_number', 'candidate_status', 'academic_year', 'External notes'];
        $file = $this->columnWorkbook([
            'Clean Data' => [$six, ['MED-0001', 'SOK', 'DARA', '2000-01-02', 'male', '']],
            'Database Export' => [$ten, ['MED-0001', 'SOK', 'DARA', '2000-01-02', 'male', '', '1', 'pending', '2026-2027', 'Reference']],
        ]);
        $component = Livewire::test($page)->mountAction('import_excel')
            ->assertFormFieldDoesNotExist('worksheet')
            ->fillForm(['file' => $file]);
        $this->assertCount(10, $this->validationToggles($component));

        $component->set('mountedActions.0.data.validation_columns.gender', false)
            ->fillForm(['file' => []])
            ->fillForm(['file' => $this->columnWorkbook([
                'Candidates' => [$six, ['MED-0001', 'SOK', 'DARA', '2000-01-02', 'male', '']],
            ])]);

        $this->assertCount(6, $this->validationToggles($component));
        $component->assertSet('mountedActions.0.data.validation_columns.gender', true);
        $this->assertSame($six, array_column($component->get('mountedActions.0.data.detected_columns'), 'key'));
        $this->assertArrayNotHasKey('candidate_status', $component->get('mountedActions.0.data.validation_columns'));
    }

    #[DataProvider('menus')]
    public function test_only_enabled_validation_columns_are_checked_when_uploading(string $menu): void
    {
        [, $page] = $this->grantImport($menu);
        [$entry] = $this->entry($menu);
        $file = $this->columnCsv(
            ['candidate_id', 'first_name_en', 'last_name_en', 'date_of_birth', 'gender', 'major'],
            [['MED-0001', 'SOK', 'DARA', 'not-a-date', 'female', 'NOT-THE-MAJOR']],
        );
        $component = Livewire::test($page)->mountAction('import_excel')
            ->fillForm(['file' => $file])
            ->callMountedAction()
            ->assertHasActionErrors(['file'])
            ->assertActionMounted('import_excel');
        $this->assertSame('pending', data_get($entry->fresh()->data, 'candidate_status'));

        $component->set('mountedActions.0.data.validation_columns.date_of_birth', false)
            ->set('mountedActions.0.data.validation_columns.gender', false)
            ->set('mountedActions.0.data.validation_columns.major', false)
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified(__('candidate_statistics_import.completed_title'));

        $this->assertSame('passed', data_get($entry->fresh()->data, 'candidate_status'));
        $this->assertSame('2000-01-02', data_get($entry->fresh()->data, 'date_of_birth'));
        $this->assertSame('male', data_get($entry->fresh()->data, 'gender'));
    }

    #[DataProvider('menus')]
    public function test_validation_selection_cannot_forge_unknown_columns_or_turn_off_candidate_matching(string $menu): void
    {
        [$resource] = $this->grantImport($menu);
        [$entry] = $this->entry($menu);
        $service = app(CandidateStatisticsImportService::class);
        $known = $this->columnCsv(['candidate_id', 'gender', 'External notes'], [['MED-0001', 'female', 'Reference']]);
        $unknown = collect($service->previewColumns($known, $resource))->firstWhere('header', 'External notes');

        foreach ([['major'], [$unknown['key']]] as $selection) {
            $this->assertInvalidSelectedImport($resource, $known, $selection);
        }
        $this->assertInvalidSelectedImport($resource, $this->columnCsv(['candidate_id', 'gender'], [['UNKNOWN', 'male']]), []);
        $this->assertSame('pending', data_get($entry->fresh()->data, 'candidate_status'));

        $this->assertEquals(['passed' => 1, 'already_passed' => 0, 'total' => 1], $service->import($known, $resource, null, []));
        $this->assertSame('passed', data_get($entry->fresh()->data, 'candidate_status'));
    }

    #[DataProvider('menus')]
    public function test_disabling_identity_checks_does_not_bypass_ambiguous_matches_or_workflow_authorization(string $menu): void
    {
        [$resource] = $this->grantImport($menu);
        [$one] = $this->entry($menu);
        [$two] = $this->entry($menu);
        [$wrongRole] = $this->entry($menu, candidateId: 'MED260002', responsibleRole: 'another_validation_operator');

        $this->assertInvalidSelectedImport($resource, $this->columnCsv(['candidate_id'], [['MED-0001']]), []);
        $this->assertInvalidSelectedImport($resource, $this->columnCsv(['candidate_id'], [['MED-0002']]), []);

        foreach ([$one, $two, $wrongRole] as $entry) {
            $this->assertSame('pending', data_get($entry->fresh()->data, 'candidate_status'));
        }
    }

    #[DataProvider('menus')]
    public function test_metadata_columns_are_optional_but_are_validated_when_selected(string $menu): void
    {
        [$resource] = $this->grantImport($menu);
        [$entry] = $this->entry($menu);
        $file = $this->columnCsv(
            ['candidate_id', 'row_number', 'candidate_status', 'candidate_reviewed_at'],
            [['MED-0001', 'not-a-number', 'failed', '2020-01-01']],
        );
        foreach (['row_number', 'candidate_status', 'candidate_reviewed_at'] as $field) {
            $this->assertInvalidSelectedImport($resource, $file, [$field]);
        }
        $this->assertSame('pending', data_get($entry->fresh()->data, 'candidate_status'));

        $this->assertEquals(['passed' => 1, 'already_passed' => 0, 'total' => 1], app(CandidateStatisticsImportService::class)->import($file, $resource, null, []));
        $this->assertSame('passed', data_get($entry->fresh()->data, 'candidate_status'));
    }

    #[DataProvider('menus')]
    public function test_reviewed_at_is_hidden_from_excel_and_import_without_removing_its_table_or_workflow_value(string $menu): void
    {
        [$resource, $page, $table] = $this->grantImport($menu);
        [$entry, $record] = $this->entry($menu);
        $tableColumn = $menu === 'exit' ? 'candidate_reviewed_at' : 'data.candidate_reviewed_at';
        Livewire::test($page)->assertTableColumnExists($tableColumn)->assertTableColumnVisible($tableColumn);
        $this->assertNotContains('candidate_reviewed_at', array_column($table::exportColumnDefinitions(), 'field_key'));

        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $label = $menu === 'exit'
                ? __('candidate_exit_statistics.fields.candidate_reviewed_at')
                : __('candidate_entrance_statistics.reviewed_at');
            // Even a table-column selection cannot add the hidden field to Excel.
            $response = $table::downloadExcel([$record], [...array_keys($table::exportColumnDefinitions()), $tableColumn]);
            $path = $response->getFile()->getPathname();
            try {
                $file = new UploadedFile($path, 'without-reviewed-at.xlsx', null, null, true);
                $rawRows = app(SpreadsheetRowReader::class)->read($file, 'Database Export');
                $displayRows = app(SpreadsheetRowReader::class)->read($file);
                $this->assertCount(14, $rawRows[1]);
                $this->assertNotContains('candidate_reviewed_at', $rawRows[1]);
                $this->assertNotContains($label, $displayRows[1]);
            } finally {
                @unlink($path);
            }

            $oldFile = $this->columnCsv(
                ['candidate_id', 'name_latin', 'date_of_birth', 'candidate_reviewed_at', 'data.candidate_reviewed_at', $label],
                [['MED-0001', 'SOK DARA', '2000-01-02', '1900-01-01', '1900-01-01', '1900-01-01']],
            );
            $columns = app(CandidateStatisticsImportService::class)->previewColumns($oldFile, $resource);
            $this->assertSame(['candidate_id', 'name_latin', 'date_of_birth'], array_column($columns, 'key'));
        }

        $this->assertInvalidSelectedImport($resource, $oldFile, ['candidate_reviewed_at']);
        $component = Livewire::test($page)->mountAction('import_excel')->fillForm(['file' => $oldFile]);
        $this->assertCount(3, $this->validationToggles($component));
        $this->assertArrayNotHasKey('candidate_reviewed_at', $component->get('mountedActions.0.data.validation_columns'));
        $component->callMountedAction()->assertHasNoActionErrors();

        $this->assertSame('passed', data_get($entry->fresh()->data, 'candidate_status'));
        $this->assertNotEmpty(data_get($entry->fresh()->data, 'candidate_reviewed_at'));
        $this->assertNotSame('1900-01-01', data_get($entry->fresh()->data, 'candidate_reviewed_at'));
    }

    private function validationToggles($component): array
    {
        $livewire = $component->instance();
        $schema = $livewire->{$livewire->getMountedActionSchemaName()};

        return array_values(array_filter($schema->getFlatFields(), fn ($field): bool => $field instanceof Toggle
            && str_contains($field->getStatePath(), '.validation_columns.')));
    }

    private function columnCsv(array $headers, array $rows): UploadedFile
    {
        $stream = fopen('php://temp', 'r+');
        foreach ([$headers, ...$rows] as $row) {
            fputcsv($stream, $row, escape: '');
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent('validation-columns.csv', $content)->mimeType('text/plain');
    }

    private function columnWorkbook(array $sheets): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import-toggle-sheets-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $workbook = '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $relationships = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach (array_values(array_keys($sheets)) as $index => $name) {
            $number = $index + 1;
            $workbook .= '<sheet name="'.htmlspecialchars($name, ENT_XML1).'" sheetId="'.$number.'" r:id="rId'.$number.'"/>';
            $relationships .= '<Relationship Id="rId'.$number.'" Target="worksheets/sheet'.$number.'.xml"/>';
            $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
            foreach ($sheets[$name] as $rowIndex => $row) {
                $xml .= '<row r="'.($rowIndex + 1).'">';
                foreach ($row as $column => $value) {
                    $xml .= '<c r="'.chr(65 + $column).($rowIndex + 1).'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
                }
                $xml .= '</row>';
            }
            $zip->addFromString('xl/worksheets/sheet'.$number.'.xml', $xml.'</sheetData></worksheet>');
        }
        $zip->addFromString('xl/workbook.xml', $workbook.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $relationships.'</Relationships>');
        $zip->close();
        $file = UploadedFile::fake()->createWithContent('validation-sheets.xlsx', file_get_contents($path))
            ->mimeType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        unlink($path);

        return $file;
    }

    private function assertInvalidSelectedImport(string $resource, UploadedFile $file, array $selection, bool $unique = true): void
    {
        try {
            app(CandidateStatisticsImportService::class)->import($file, $resource, null, $selection, $unique);
            $this->fail('Invalid selected validation fields must fail before updating a result.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }
    }

    private function menu(string $menu): array
    {
        return $menu === 'exit'
            ? [CandidateExitStatisticResource::class, ListCandidateExitStatistics::class, CandidateExitStatisticsTable::class]
            : [CandidateEntranceStatisticResource::class, ListCandidateEntranceStatistics::class, CandidateEntranceStatisticsTable::class];
    }

    private function grantImport(string $menu): array
    {
        $details = $this->menu($menu);
        $this->grant($details[0], 'ViewAny');
        $this->grant($details[0], 'ImportExcel');

        return $details;
    }

    private function grant(string $resource, string $prefix): void
    {
        $this->role->givePermissionTo(Permission::findOrCreate($prefix.':'.class_basename($resource::getModel()), 'web'));
        $this->operator->unsetRelation('roles')->unsetRelation('permissions');
    }

    private function entry(string $menu, string $candidateId = 'MED260001', string $responsibleRole = 'statistics_import_operator', array $identity = []): array
    {
        $number = ++$this->sequence;
        $target = $menu === 'exit' ? StatisticsMenuOptions::EXIT_EXAM_STATISTICS : StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS;
        Role::findOrCreate($responsibleRole, 'web');
        $candidate = User::query()->create([
            'registration_type' => 'student', 'name' => 'សុខ ដារា', 'name_latin' => 'SOK DARA',
            'username' => 'import_candidate_'.$number, 'email' => 'import-candidate-'.$number.'@example.test',
            'password' => 'password', 'date_of_birth' => '2000-01-02', 'is_active' => true,
            'academic_year' => '2026-2027', 'seat_number' => 'SEAT-'.$number,
        ]);
        $form = CustomForm::query()->create([
            'name' => json_encode(['en' => 'Import application '.$number, 'km' => 'ពាក្យសុំ '.$number], JSON_UNESCAPED_UNICODE),
            'slug' => 'import-application-'.$number, 'is_active' => true, 'requires_payment' => false,
            'statistics_menu' => $target,
        ]);
        $workflow = WorkflowNotification::query()->create([
            'name' => 'Import workflow '.$number,
            'stages' => [
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Results', 'stage_type' => 'awaiting_results',
                    'statistics_menu' => $target, 'responsible_role' => [$responsibleRole],
                ]],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Notify', 'stage_type' => 'completed', 'responsible_role' => [$responsibleRole],
                ]],
            ],
        ]);
        $workflow->forms()->attach($form);
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $form->id, 'created_by' => $candidate->id, 'candidate_id' => $candidateId,
            'review_status' => 'approved', 'workflow_snapshot' => WorkflowEntrySnapshot::forForm($form->id),
            'data' => [
                'first_name_kh' => 'សុខ', 'last_name_kh' => 'ដារា', 'first_name_en' => 'SOK', 'last_name_en' => 'DARA',
                'gender' => 'male', 'date_of_birth' => '2000-01-02', 'academic_year' => '2026-2027',
                'seat_number' => 'SEAT-'.$number, 'candidate_status' => 'pending', 'registration_status' => 'approved',
                'submitted_at' => now()->toIso8601String(),
                ...$identity,
            ],
        ])->saveQuietly();
        WorkflowStageProgression::initialize($entry);
        CandidateStatisticsSynchronizer::syncEntry($entry->fresh());
        $row = $menu === 'exit'
            ? CandidateExitStatistic::query()->where('custom_form_entry_id', $entry->id)->sole()
            : $entry->fresh();

        return [$entry->fresh(), $row];
    }

    private function csv(array $rows): UploadedFile
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['candidate_id', 'name_latin', 'date_of_birth'], escape: '');
        foreach ($rows as $row) {
            fputcsv($stream, $row, escape: '');
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent('passed.csv', $content);
    }

    private function assertInvalidImport(string $resource, UploadedFile $file): void
    {
        try {
            app(CandidateStatisticsImportService::class)->import($file, $resource);
            $this->fail('Invalid imports must fail before changing any candidate.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }
    }
}
