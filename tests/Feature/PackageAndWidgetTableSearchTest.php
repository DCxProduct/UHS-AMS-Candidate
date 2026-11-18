<?php

namespace Tests\Feature;

use App\Filament\Widgets\AdminSidebarFormsTable;
use App\Filament\Widgets\CandidateSidebarFormsTable;
use App\Models\GeoLocation;
use App\Models\Major;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\DashboardMetrics;
use App\Support\FormEntryData;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Tables\CustomFormEntriesTable;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Pages\EditCustomForm;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Pages\ListCustomForms;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\RelationManagers\FieldsRelationManager;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Pages\ListDocumentTemplates;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use ReflectionProperty;
use Tests\TestCase;

class PackageAndWidgetTableSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetOptionCaches();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        DB::statement('PRAGMA case_sensitive_like = ON');
        $admin = $this->user('package-search-admin', 'admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        $this->resetOptionCaches();
        DB::statement('PRAGMA case_sensitive_like = OFF');
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    private function resetOptionCaches(): void
    {
        foreach (['fieldOptionCache', 'majorIdLabelCache'] as $property) {
            (new ReflectionProperty(FormEntryData::class, $property))->setValue(null, []);
        }
        (new ReflectionProperty(CustomFormEntriesTable::class, 'majorLocalizedNameCache'))->setValue(null, []);
    }

    public function test_custom_entry_search_covers_dynamic_values_labels_and_dates_without_leaking_other_forms(): void
    {
        $form = $this->form('search-application');
        foreach ([
            ['name' => 'last_name_en', 'type' => 'text_input'],
            ['name' => 'department', 'type' => 'checkbox_list', 'options' => ['choices' => ['clinical' => ['en' => 'Clinical Faculty', 'km' => 'ផ្នែកគ្លីនិក']]]],
            ['name' => 'interview_date', 'type' => 'date_picker'],
            ['name' => 'current_capital_province', 'type' => 'select_dropdown'],
        ] as $field) {
            $form->fields()->create(['label' => $field['name'], ...$field]);
        }
        $major = Major::create(['key' => 'clinical-search', 'abbreviation' => 'CLI', 'name_en' => 'Clinical Medicine', 'name_kh' => 'វេជ្ជសាស្ត្រ', 'is_active' => true]);
        $location = GeoLocation::create(['name_en' => 'Kampot Search Province', 'name_kh' => 'កំពត', 'code' => '07', 'type' => 'province']);
        $data = [
            'last_name_en' => 'McDONALD', 'department' => ['clinical'], 'interview_date' => '2026-04-23',
            'current_capital_province' => $location->id, 'selected_major' => $major->id,
            'gender' => 'female', 'academic_year' => '2026_2027', 'submitted_at' => '2026-03-18 09:45:00',
        ];
        $match = $this->entry($form, $data);
        $other = $this->entry($form, ['last_name_en' => 'Other', 'gender' => 'male']);
        $match->forceFill(['created_at' => '2020-09-13 09:00:00'])->saveQuietly();
        $other->forceFill(['created_at' => '2020-09-13 09:00:00'])->saveQuietly();
        $outside = $this->entry($this->form('other-application'), $data);
        $hidden = $this->entry($form, $data, ['form_entries_deleted_at' => now()]);
        $list = Livewire::withQueryParams(['form_id' => $form->id])->test(ListCustomFormEntries::class)
            ->assertCanSeeTableRecords([$match, $other])->assertCanNotSeeTableRecords([$outside, $hidden]);

        foreach (['mCdOnAlD', 'CLINICAL', '23-aPr-2026', 'kAmPoT', 'CLI-Clinical', 'FeMaLe', '2026-2027', '18-MAR-2026'] as $search) {
            $list->searchTable($search);
            $this->assertSame([$match->id], $list->instance()->getFilteredTableQuery()->pluck('custom_form_entries.id')->all(), $search);
            $list->assertCanSeeTableRecords([$match])
                ->assertCanNotSeeTableRecords([$other, $outside, $hidden])->assertCountTableRecords(1);
        }
        $list->searchTable('no-such-package-value')->assertCountTableRecords(0);
        $list->searchTable('13-SEP-2020')->assertCanSeeTableRecords([$other])->assertCanNotSeeTableRecords([$match, $outside, $hidden]);
        $this->assertSame($data['last_name_en'], $match->fresh()->data['last_name_en']);
    }

    public function test_profile_entry_search_matches_text_and_localized_dates(): void
    {
        $profile = $this->form('profile');
        $match = $this->entry($profile, ['exam_center' => 'Clinical CAMPUS', 'date_of_birth' => '2001-04-23']);
        $other = $this->entry($profile, ['exam_center' => 'Other Campus', 'date_of_birth' => '2000-05-04']);
        $list = Livewire::withQueryParams(['form_id' => $profile->id])->test(ListCustomFormEntries::class);
        foreach (['CLINICAL', '23-apr-2001', '២៣-មេសា-២០០១'] as $search) {
            $list->searchTable($search)->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other]);
        }
    }

    public function test_form_search_matches_workflow_names_and_localized_names_in_any_case(): void
    {
        $match = $this->form('workflow-search');
        $match->update(['name' => json_encode(['en' => 'Advanced Clinical Intake', 'km' => 'ការចុះឈ្មោះ'])]);
        $other = $this->form('unassigned-search');
        $workflow = WorkflowNotification::create(['name' => 'Special Admission Review', 'stages' => []]);
        $workflow->forms()->attach($match);
        $list = Livewire::test(ListCustomForms::class);
        foreach (['sPeCiAl', 'ADVANCED'] as $search) {
            $list->searchTable($search)->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other]);
        }
        $profile = $this->form('profile');
        $profile->update(['name' => 'Profile']);
        app()->setLocale('km');
        Livewire::test(ListCustomForms::class)->searchTable('ការចុះឈ្មោះ')
            ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$profile, $other]);
        Livewire::test(ListCustomForms::class)->searchTable('ប្រវត្តិរូប')
            ->assertCanSeeTableRecords([$profile])->assertCanNotSeeTableRecords([$match, $other]);
    }

    public function test_field_search_matches_displayed_type_and_parent_labels(): void
    {
        $form = $this->form('field-search');
        $parent = $form->fields()->create(['name' => 'container', 'label' => json_encode(['en' => 'Clinical Details', 'km' => 'ព័ត៌មាន']), 'type' => 'section']);
        $match = $form->fields()->create(['name' => 'contact', 'label' => 'Contact', 'type' => 'text_input', 'parent_id' => $parent->id]);
        $other = $form->fields()->create(['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea']);
        $list = Livewire::test(FieldsRelationManager::class, ['ownerRecord' => $form, 'pageClass' => EditCustomForm::class]);
        $list->searchTable('TEXT INPUT')->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other]);
        $list->searchTable('cLiNiCaL')->assertCanSeeTableRecords([$match, $parent])->assertCanNotSeeTableRecords([$other]);
        app()->setLocale('km');
        Livewire::test(FieldsRelationManager::class, ['ownerRecord' => $form, 'pageClass' => EditCustomForm::class])
            ->searchTable('ព័ត៌មាន')->assertCanSeeTableRecords([$match, $parent])->assertCanNotSeeTableRecords([$other]);
    }

    public function test_document_template_search_matches_name_type_and_displayed_date(): void
    {
        $match = DocumentTemplate::create(['name' => json_encode(['en' => 'Clinical Certificate', 'km' => 'វិញ្ញាបនបត្រ']), 'type' => 'Certificate', 'content' => '']);
        $match->forceFill(['created_at' => '2026-04-23 08:00:00'])->saveQuietly();
        $other = DocumentTemplate::create(['name' => 'Other Document', 'type' => 'invoice', 'content' => '']);
        $list = Livewire::test(ListDocumentTemplates::class);
        foreach (['CLINICAL', 'cErTiFiCaTe', '23-APR-2026'] as $search) {
            $list->searchTable($search)->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other]);
        }
        app()->setLocale('km');
        Livewire::test(ListDocumentTemplates::class)->searchTable('វិញ្ញាបនបត្រ')
            ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other]);
    }

    public function test_admin_form_widget_search_matches_names_and_actual_counts_and_keeps_profile_excluded(): void
    {
        $match = $this->form('Clinical Widget');
        $other = $this->form('Other Widget');
        $profile = $this->form('profile');
        $this->entry($match);
        $this->entry($match);
        $this->entry($other);
        $this->entry($profile);
        $this->entry($profile);
        $list = Livewire::test(AdminSidebarFormsTable::class);
        foreach (['CLINICAL', '2'] as $search) {
            $list->searchTable($search)->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other, $profile])->assertCountTableRecords(1);
        }
        $match->update(['name' => json_encode(['en' => 'Clinical Widget', 'km' => 'ពាក្យសុំគ្លីនិក'])]);
        app()->setLocale('km');
        Livewire::test(AdminSidebarFormsTable::class)->searchTable('ពាក្យសុំគ្លីនិក')
            ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other, $profile]);
    }

    public function test_candidate_form_widget_count_search_counts_only_its_owner_entries(): void
    {
        $candidate = $this->user('widget-candidate');
        $candidate->assignRole(Role::findOrCreate('candidate', 'web'), Role::findOrCreate('submission_applicant', 'web'));
        $otherOwner = $this->user('widget-other-owner');
        if (! Schema::hasColumn('custom_form_entries', 'system_user_id')) {
            Schema::table('custom_form_entries', fn (Blueprint $table) => $table->unsignedBigInteger('system_user_id')->nullable());
        }
        $this->actingAs($candidate);
        $profile = $this->form('profile');
        $this->entry($profile, [], ['created_by' => $candidate->id]);
        $match = $this->form('Clinical Applications', ['submission_applicant']);
        $other = $this->form('Other Applications', ['submission_applicant']);
        $excluded = $this->form('Forbidden Applications', ['another_applicant']);
        $this->entry($match, [], ['created_by' => $candidate->id]);
        $this->entry($match, [], ['created_by' => $candidate->id]);
        $this->entry($other, [], ['created_by' => $candidate->id]);
        $this->entry($other, [], ['created_by' => $otherOwner->id, 'system_user_id' => $candidate->id]);
        $this->entry($excluded, [], ['created_by' => $candidate->id]);
        $this->entry($excluded, [], ['created_by' => $candidate->id]);
        $this->assertSame(2, DashboardMetrics::studentEntriesQuery($candidate->id)->where('custom_form_id', $match->id)->count());
        $list = Livewire::test(CandidateSidebarFormsTable::class);
        foreach (['CLINICAL', '2'] as $search) {
            $list->searchTable($search)->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other, $excluded, $profile])->assertCountTableRecords(1);
        }
        $match->update(['name' => json_encode(['en' => 'Clinical Applications', 'km' => 'ពាក្យសុំគ្លីនិក'])]);
        app()->setLocale('km');
        Livewire::test(CandidateSidebarFormsTable::class)->searchTable('ពាក្យសុំគ្លីនិក')
            ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other, $excluded, $profile]);
    }

    private function user(string $name, string $type = 'student'): User
    {
        return User::create(['registration_type' => $type, 'name' => $name, 'username' => $name, 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
    }

    private function form(string $name, array $roles = []): CustomForm
    {
        return CustomForm::create(['name' => $name, 'slug' => str_replace(' ', '-', strtolower($name)), 'is_active' => true, 'allowed_roles' => $roles]);
    }

    private function entry(CustomForm $form, array $data = [], array $attributes = []): CustomFormEntry
    {
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $form->id, 'created_by' => auth()->id(), 'review_status' => 'pending',
            'workflow_snapshot' => ['schema_version' => 1, 'has_workflow' => false, 'stages' => []],
            'data' => ['registration_status' => 'pending', ...$data], ...$attributes,
        ])->saveQuietly();

        return $entry;
    }
}
