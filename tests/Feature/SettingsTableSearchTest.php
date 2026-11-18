<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Pages\ListCandidateSubmitPopupSettings;
use App\Filament\Admin\Resources\CandidateTypes\Pages\ListCandidateTypes;
use App\Filament\Admin\Resources\ClosingDates\Pages\ListClosingDates;
use App\Filament\Admin\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Filament\Admin\Resources\GeoLocations\Pages\ListGeoLocations;
use App\Filament\Admin\Resources\Majors\Pages\ListMajors;
use App\Filament\Admin\Resources\PaymentTypes\Pages\ListPaymentTypes;
use App\Filament\Admin\Resources\SmsTemplates\Pages\ListSmsTemplates;
use App\Filament\Admin\Resources\SystemUsers\Pages\ListSystemUsers;
use App\Filament\Admin\Resources\WorkflowButtons\Pages\ListWorkflowButtons;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\ListWorkflowNotifications;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\ListWorkflowStageTypes;
use App\Models\AuditLog;
use App\Models\CandidateSubmitPopupSetting;
use App\Models\ClosingDate;
use App\Models\DegreeLevel;
use App\Models\EmailTemplate;
use App\Models\GeoLocation;
use App\Models\Major;
use App\Models\PaymentType;
use App\Models\Role;
use App\Models\RoleType;
use App\Models\SmsTemplate;
use App\Models\SystemUser;
use App\Models\User;
use App\Models\UserType;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Support\WorkflowConfigurationUsage;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\ListRoles;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SettingsTableSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        Notification::fake();
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA case_sensitive_like = ON');
        }
        $admin = User::query()->create([
            'registration_type' => 'admin', 'name' => 'Search administrator', 'username' => 'settings-search-admin',
            'email' => 'settings-search-admin@example.test', 'password' => 'password',
            'date_of_birth' => '1990-01-01', 'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA case_sensitive_like = OFF');
        }
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    public function test_staff_names_and_displayed_role_labels_ignore_case_and_preserve_status_filters(): void
    {
        Role::findOrCreate('registrar_officer', 'web')->forceFill(['label_en' => 'Admissions Desk'])->save();
        Role::findOrCreate('cashier', 'web');
        $match = SystemUser::query()->create([
            'name' => 'SOK DARA', 'first_name_latin' => 'SOK', 'last_name_latin' => 'DARA',
            'username' => 'search-registrar', 'email' => 'registrar@example.test', 'password' => 'password',
            'roles' => ['registrar_officer'], 'is_active' => true,
        ]);
        $other = SystemUser::query()->create([
            'name' => 'CHAN SEN', 'first_name_latin' => 'CHAN', 'last_name_latin' => 'SEN',
            'username' => 'search-cashier', 'email' => 'cashier@example.test', 'password' => 'password',
            'roles' => ['cashier'], 'is_active' => false,
        ]);

        foreach (['sOk', 'DaRa', 'dEsK'] as $term) {
            Livewire::test(ListSystemUsers::class)->searchTable($term)
                ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other]);
        }
        Livewire::test(ListSystemUsers::class)->filterTable('is_active', '0')->searchTable('desk')
            ->assertCanNotSeeTableRecords([$match, $other]);
    }

    public function test_payment_method_labels_and_displayed_english_and_khmer_dates_are_searchable(): void
    {
        $bank = PaymentType::query()->create(['key' => 'wire', 'abbreviation' => 'WIRE', 'name_en' => 'Transfer', 'method' => 'bank', 'is_active' => true]);
        $cash = PaymentType::query()->create(['key' => 'cash', 'abbreviation' => 'CSH', 'name_en' => 'Desk', 'method' => 'cash', 'is_active' => true]);
        DB::table('payment_types')->where('id', $bank->id)->update(['created_at' => '2024-02-03 11:22:33', 'updated_at' => '2024-02-03 11:22:33']);

        foreach (['bAnK', '03-fEb-2024'] as $term) {
            Livewire::test(ListPaymentTypes::class)->searchTable($term)
                ->assertCanSeeTableRecords([$bank])->assertCanNotSeeTableRecords([$cash]);
        }
        app()->setLocale('km');
        foreach ([__('payment_types.options.methods.bank'), '០៣-កុម្ភៈ-២០២៤'] as $term) {
            Livewire::test(ListPaymentTypes::class)->searchTable($term)
                ->assertCanSeeTableRecords([$bank])->assertCanNotSeeTableRecords([$cash]);
        }
    }

    public function test_related_degree_and_application_labels_are_found_in_settings_tables(): void
    {
        $degree = DegreeLevel::query()->create(['key' => 'research', 'label_en' => 'Research Degree', 'label_kh' => 'កម្រិតស្រាវជ្រាវ', 'is_active' => true]);
        $major = Major::query()->create(['key' => 'nursing', 'abbreviation' => 'NUR', 'name_en' => 'Nursing', 'is_active' => true]);
        $degree->majors()->attach($major);
        $type = UserType::query()->create(['key' => 'research_applicant', 'label_en' => 'Visitor application', 'label_kh' => 'ពាក្យស្នើសុំ', 'group_name' => $degree->key, 'is_active' => true]);
        $form = CustomForm::query()->create(['name' => json_encode(['en' => 'Bridge Intake', 'km' => 'ពាក្យស្នើសុំថ្មី'], JSON_UNESCAPED_UNICODE), 'slug' => 'bridge-intake', 'is_active' => true]);
        $schedule = ClosingDate::query()->create([
            'name' => 'Autumn period', 'type' => ClosingDate::customFormTypeKey($form->id), 'degree_level_id' => $degree->id,
            'status' => 'closed', 'start_date' => '2024-02-01', 'end_date' => '2024-03-01',
        ]);
        $popup = CandidateSubmitPopupSetting::query()->create(['custom_form_id' => $form->id, 'is_active' => true]);

        Livewire::test(ListMajors::class)->searchTable('rEsEaRcH')->assertCanSeeTableRecords([$major]);
        Livewire::test(ListCandidateTypes::class)->searchTable('rEsEaRcH')->assertCanSeeTableRecords([$type]);
        foreach (['bRiDgE', 'rEsEaRcH', 'cLoSeD', '01-fEb-2024'] as $term) {
            Livewire::test(ListClosingDates::class)->searchTable($term)->assertCanSeeTableRecords([$schedule]);
        }
        Livewire::test(ListCandidateSubmitPopupSettings::class)->searchTable('bRiDgE')->assertCanSeeTableRecords([$popup]);
    }

    public function test_geo_parent_labels_and_translated_types_are_searchable(): void
    {
        $province = GeoLocation::query()->create(['name_en' => 'Northern region', 'name_kh' => 'តំបន់ខាងជើង', 'code' => '01', 'type' => 'province', 'is_active' => true]);
        $district = GeoLocation::query()->create(['name_en' => 'Central area', 'code' => '0101', 'type' => 'district', 'parent_id' => $province->id, 'is_active' => true]);
        $other = GeoLocation::query()->create(['name_en' => 'Southern region', 'code' => '02', 'type' => 'province', 'is_active' => true]);

        Livewire::test(ListGeoLocations::class)->searchTable('nOrThErN')
            ->assertCanSeeTableRecords([$province, $district])->assertCanNotSeeTableRecords([$other]);
        app()->setLocale('km');
        Livewire::test(ListGeoLocations::class)->searchTable(__('geo_locations.types.district'))
            ->assertCanSeeTableRecords([$district])->assertCanNotSeeTableRecords([$province, $other]);
    }

    public function test_escaped_json_form_names_can_be_found_using_the_rendered_khmer_label(): void
    {
        $khmer = 'ពាក្យស្នើសុំស្ពានថ្មី';
        $form = CustomForm::query()->create([
            'name' => json_encode(['en' => 'Bridge application', 'km' => $khmer]),
            'slug' => 'escaped-khmer-form', 'is_active' => true,
        ]);
        $otherForm = CustomForm::query()->create(['name' => 'Unrelated application', 'slug' => 'unrelated-form', 'is_active' => true]);
        $popup = CandidateSubmitPopupSetting::query()->create(['custom_form_id' => $form->id, 'is_active' => true]);
        $otherPopup = CandidateSubmitPopupSetting::query()->create(['custom_form_id' => $otherForm->id, 'is_active' => true]);
        $schedule = ClosingDate::query()->create([
            'name' => 'First period', 'type' => ClosingDate::customFormTypeKey($form->id),
            'status' => 'open', 'start_date' => today(), 'end_date' => today()->addWeek(),
        ]);
        $this->assertStringNotContainsString($khmer, $form->getRawOriginal('name'));
        app()->setLocale('km');

        Livewire::test(ListCandidateSubmitPopupSettings::class)->searchTable($khmer)
            ->assertCanSeeTableRecords([$popup])->assertCanNotSeeTableRecords([$otherPopup]);
        Livewire::test(ListClosingDates::class)->searchTable($khmer)->assertCanSeeTableRecords([$schedule]);
    }

    public function test_email_and_sms_search_include_display_names_action_labels_and_builtin_fallback_text(): void
    {
        $email = EmailTemplate::for(EmailTemplate::RESET_PASSWORD);
        $email->update(['subject' => '']);
        $customEmail = EmailTemplate::query()->create(['key' => 'review_letter', 'name' => 'Review LETTER', 'action' => 'accept', 'header_title' => 'UHS', 'subject' => 'Admission notice', 'button' => 'Open', 'body' => '<p>Welcome</p>']);
        Livewire::test(ListEmailTemplates::class)->searchTable('YoUr')->assertCanSeeTableRecords([$email])->assertCanNotSeeTableRecords([$customEmail]);
        Livewire::test(ListEmailTemplates::class)->searchTable('lEtTeR')->assertCanSeeTableRecords([$customEmail])->assertCanNotSeeTableRecords([$email]);
        Livewire::test(ListEmailTemplates::class)->searchTable('cHeCkEd')->assertCanSeeTableRecords([$customEmail])->assertCanNotSeeTableRecords([$email]);

        $sms = SmsTemplate::for(SmsTemplate::RESET_PASSWORD_OTP);
        $sms->update(['body' => '']);
        $customSms = SmsTemplate::query()->create(['key' => 'review_notice', 'name' => 'Review NOTICE', 'action' => 'accept', 'app_name' => 'UHS', 'body' => 'Admission approved']);
        Livewire::test(ListSmsTemplates::class)->searchTable('VaLiD')->assertCanSeeTableRecords([$sms])->assertCanNotSeeTableRecords([$customSms]);
        Livewire::test(ListSmsTemplates::class)->searchTable('nOtIcE')->assertCanSeeTableRecords([$customSms])->assertCanNotSeeTableRecords([$sms]);
        Livewire::test(ListSmsTemplates::class)->searchTable('cHeCkEd')->assertCanSeeTableRecords([$customSms])->assertCanNotSeeTableRecords([$sms]);
    }

    public function test_workflow_usage_button_labels_and_displayed_counts_are_searchable(): void
    {
        $button = WorkflowButton::query()->create(['label_en' => 'Approve NOW', 'label_km' => 'យល់ព្រមឥឡូវ', 'action_key' => 'accept']);
        $type = WorkflowStageType::query()->create(['label_en' => 'Special Review Stage', 'label_km' => 'ដំណាក់កាលពិនិត្យពិសេស', 'behavior' => 'review', 'workflow_button_ids' => [$button->id]]);
        $workflowId = DB::table('workflow_notifications')->insertGetId([
            'name' => 'Unique workflow', 'stages' => json_encode([
                ['type' => 'stage', 'data' => ['stage_type' => 'review', 'stage_type_id' => $type->id, 'buttons' => [$button->toDefinition()]]],
                ['type' => 'stage', 'data' => ['stage_type' => 'completed']],
            ]), 'created_at' => '1999-11-11 00:00:00', 'updated_at' => '1999-11-11 00:00:00',
        ]);
        $workflow = WorkflowNotification::query()->findOrFail($workflowId);
        $other = WorkflowNotification::query()->create(['name' => 'Other flow', 'stages' => []]);
        DB::table('workflow_notifications')->where('id', $other->id)->update(['created_at' => '1999-11-11 00:00:00', 'updated_at' => '1999-11-11 00:00:00']);
        app()->forgetInstance(WorkflowConfigurationUsage::class);

        foreach (['sPeCiAl', 'uNiQuE'] as $term) {
            Livewire::test(ListWorkflowButtons::class)->searchTable($term)->assertCanSeeTableRecords([$button]);
        }
        foreach (['aPpRoVe', 'uNiQuE'] as $term) {
            Livewire::test(ListWorkflowStageTypes::class)->searchTable($term)->assertCanSeeTableRecords([$type]);
        }
        Livewire::test(ListWorkflowNotifications::class)->searchTable('2')
            ->assertCanSeeTableRecords([$workflow])->assertCanNotSeeTableRecords([$other]);
    }

    public function test_translated_audit_labels_do_not_escape_the_privileged_activity_scope(): void
    {
        $match = AuditLog::query()->forceCreate(['module' => 'payments', 'action' => 'verified', 'actor_name' => 'Operator', 'actor_role' => 'admin']);
        $excluded = AuditLog::query()->forceCreate(['module' => 'payments', 'action' => 'verified', 'actor_name' => 'Other', 'actor_role' => 'student']);

        Livewire::test(ListAuditLogs::class)->searchTable('aPpLiCaTiOn ReCeIvEd')
            ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$excluded]);
    }

    public function test_role_type_badges_permission_counts_and_displayed_dates_are_searchable(): void
    {
        $type = RoleType::query()->create(['key' => 'operations', 'label_en' => 'Operations Desk', 'label_kh' => 'ផ្នែកប្រតិបត្តិការ', 'is_active' => true]);
        $match = Role::query()->create(['name' => 'search_alpha', 'label_en' => 'Alpha access', 'guard_name' => 'web', 'role_type_key' => $type->key]);
        $other = Role::query()->create(['name' => 'search_other', 'label_en' => 'Other access', 'guard_name' => 'web', 'role_type_key' => 'staff']);
        $permissions = array_slice(RoleResource::manageablePermissionNames(), 0, 8);
        $this->assertCount(8, $permissions);
        $match->givePermissionTo(array_map(fn (string $name) => Permission::findOrCreate($name, 'web'), $permissions));
        DB::table('roles')->where('id', $match->id)->update(['updated_at' => '2024-02-03 11:22:33']);
        DB::table('roles')->where('id', $other->id)->update(['updated_at' => '1999-11-11 00:00:00']);

        foreach (['oPeRaTiOnS', '8', '03-fEb-2024'] as $term) {
            Livewire::test(ListRoles::class)->searchTable($term)
                ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other]);
        }
        app()->setLocale('km');
        Livewire::test(ListRoles::class)->searchTable('ផ្នែកប្រតិបត្តិការ')
            ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other]);
    }
}
