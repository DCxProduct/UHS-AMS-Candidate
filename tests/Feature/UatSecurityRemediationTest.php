<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\CashierStatsOverview;
use App\Models\AuditLog;
use App\Models\SystemUser;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Services\DbSyncApiService;
use App\Support\AuditLogger;
use App\Support\DashboardMetrics;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UatSecurityRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        Notification::fake();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    private function user(string $role, bool $active = true, array $permissions = []): User
    {
        $id = User::count() + 1;
        $u = User::create(['registration_type' => $role === 'candidate' ? 'student' : 'admin', 'name' => 'QA '.$role, 'username' => 'qa_'.$id, 'email' => 'qa-'.$id.'@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'test-only-password', 'is_active' => $active]);
        $u->assignRole(Role::findOrCreate($role, 'web'));
        foreach ($permissions as $permission) {
            $u->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $u;
    }

    private function entry(?User $owner = null): CustomFormEntry
    {
        $form = CustomForm::create(['name' => 'QA Application', 'slug' => 'qa-'.CustomForm::count(), 'is_active' => true, 'requires_payment' => true]);

        return CustomFormEntry::create(['custom_form_id' => $form->id, 'created_by' => $owner?->id, 'data' => ['registration_status' => 'pending', 'phone_number' => '188610029'], 'review_status' => 'pending']);
    }

    public static function reviewEndpoints(): array
    {
        return [['approve'], ['reject']];
    }

    #[DataProvider('reviewEndpoints')]
    public function test_unauthorized_cashier_review_is_forbidden_without_mutation(string $action): void
    {
        $cashier = $this->user('cashier_manager');
        $entry = $this->entry();
        $before = $entry->fresh()->getAttributes();
        $this->actingAs($cashier)->post('/admin/custom-form-entries/'.$entry->id.'/'.$action, ['review_note' => 'QA return'])->assertForbidden();
        $this->assertSame($before, $entry->fresh()->getAttributes());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_cashier_cannot_run_sync_and_service_is_never_called(): void
    {
        $this->mock(DbSyncApiService::class, fn ($mock) => $mock->shouldNotReceive('sync'));
        $this->actingAs($this->user('cashier_manager'))->postJson('/sync/run', ['dry_run' => true])->assertForbidden();
    }

    public function test_authorized_admin_can_run_mocked_sync(): void
    {
        $this->mock(DbSyncApiService::class, fn ($mock) => $mock->shouldReceive('sync')->once()->with(true, false)->andReturn(['success' => true, 'message' => 'QA mock', 'data' => [], 'errors' => []]));
        $this->actingAs($this->user('admin'))->postJson('/sync/run', ['dry_run' => true])->assertOk()->assertJsonPath('success', true);
    }

    #[DataProvider('reviewEndpoints')]
    public function test_authorized_registrar_review_still_works_and_is_audited(string $action): void
    {
        $manager = $this->user('registrar_manager', permissions: ['View:CustomFormEntry', 'Accepted:CustomFormEntry', 'Rejected:CustomFormEntry']);
        $entry = $this->entry();
        $this->actingAs($manager)->post('/admin/custom-form-entries/'.$entry->id.'/'.$action, ['review_note' => 'QA return'])->assertRedirect();
        $expected = $action === 'approve' ? 'approved' : 'rejected';
        $this->assertSame($expected, $entry->fresh()->review_status);
        $this->assertSame($manager->id, $entry->fresh()->reviewed_by);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $manager->id, 'action' => $expected]);
    }

    public function test_review_requires_the_configured_stage_role(): void
    {
        $manager = $this->user('registrar_manager', permissions: ['View:CustomFormEntry', 'Accepted:CustomFormEntry']);
        $entry = $this->entry();
        $workflow = WorkflowNotification::create(['name' => 'QA stage', 'stages' => [['name' => 'Review', 'type' => 'review', 'responsible_role' => 'registrar_officer']]]);
        $workflow->forms()->attach($entry->custom_form_id);
        $this->actingAs($manager)->post('/admin/custom-form-entries/'.$entry->id.'/approve')->assertForbidden();
        $this->assertSame('pending', $entry->fresh()->review_status);
    }

    #[DataProvider('reviewEndpoints')]
    public function test_completed_application_cannot_be_reviewed_again(string $action): void
    {
        $entry = $this->entry();
        $entry->forceFill(['review_status' => 'passed', 'data' => ['registration_status' => 'passed']])->save();
        $this->actingAs($this->user('admin'))->post('/admin/custom-form-entries/'.$entry->id.'/'.$action, ['review_note' => 'QA return'])->assertStatus(409);
        $this->assertSame('passed', $entry->fresh()->review_status);
    }

    public function test_unverified_registration_does_not_claim_existing_applications(): void
    {
        $other = $this->user('candidate');
        $unowned = $this->entry();
        $owned = $this->entry($other);
        $component = Livewire::test(Register::class)->instance();
        $user = (new ReflectionMethod(Register::class, 'handleRegistration'))->invoke($component, ['student_role' => 'candidate', 'first_name_en' => 'QA', 'last_name_en' => 'REGISTRATION', 'phone' => '188610029', 'email' => 'registration@example.test', 'password' => 'test-only-password']);
        $this->assertNull($unowned->fresh()->created_by);
        $this->assertSame($other->id, $owned->fresh()->created_by);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->linkedSystemUser()->email_verified_at);
        $user->linkedSystemUser()->syncLoginUser();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public static function inactiveRoutes(): array
    {
        return [['approve'], ['reject'], ['sync']];
    }

    #[DataProvider('inactiveRoutes')]
    public function test_deactivation_terminates_existing_session_before_mutation(string $action): void
    {
        $staff = $this->user('admin');
        $entry = $this->entry();
        $this->actingAs($staff);
        DB::table('users')->where('id', $staff->id)->update(['is_active' => false]);
        $this->mock(DbSyncApiService::class, fn ($mock) => $mock->shouldNotReceive('sync'));
        $url = $action === 'sync' ? '/sync/run' : '/admin/custom-form-entries/'.$entry->id.'/'.$action;
        $this->postJson($url, ['review_note' => 'QA return'])->assertForbidden();
        $this->assertGuest();
        $this->assertSame('pending', $entry->fresh()->review_status);
    }

    public function test_inactive_account_cannot_access_panel_or_livewire(): void
    {
        $staff = $this->user('admin', false);
        $this->actingAs($staff)->get('/dashboard')->assertRedirect('/login');
        $this->actingAs($staff)->postJson(app(HandleRequests::class)->getUpdateUri(), ['components' => []])->assertForbidden();
    }

    public static function managers(): array
    {
        return [['registrar_manager'], ['registrar_officer'], ['cashier_manager'], ['cashier_officer']];
    }

    #[DataProvider('managers')]
    public function test_staff_audit_records_have_actor_and_redacted_values(string $role): void
    {
        $u = $this->user($role);
        $this->actingAs($u);
        AuditLogger::log('review', newValues: ['status' => 'approved', 'password' => 'do-not-record', 'nested' => ['api_key' => 'do-not-record', 'value' => 'safe']]);
        $log = AuditLog::sole();
        $this->assertSame($u->id, $log->actor_id);
        $this->assertSame($role, $log->actor_role);
        $this->assertSame('review', $log->action);
        $this->assertStringNotContainsString('do-not-record', json_encode($log->getAttributes()));
    }

    public function test_dashboard_counts_only_users_id_owners_when_ids_collide(): void
    {
        $candidate = $this->user('candidate');
        $other = $this->user('candidate');
        SystemUser::create(['id' => $other->id, 'name' => $candidate->name, 'username' => $candidate->username, 'email' => $candidate->email, 'password' => 'test-only-password', 'roles' => ['candidate'], 'is_active' => true]);
        // Use raw insert for an intentional ID collision, independent of mass assignment.
        DB::table('system_users')->where('username', $candidate->username)->update(['id' => $other->id]);
        $this->entry($other);
        $this->assertSame(0, DashboardMetrics::studentSubmittedApplicationsCount($candidate->id));
        $own = $this->entry($candidate);
        $this->assertSame(1, DashboardMetrics::studentSubmittedApplicationsCount($candidate->id));
        $own->update(['review_status' => 'approved', 'data' => ['registration_status' => 'approved']]);
        $this->assertSame(1, DashboardMetrics::studentSubmittedApplicationsCount($candidate->id));
    }

    #[DataProvider('managers')]
    public function test_manager_dashboard_widgets_are_visible_for_the_role(string $role): void
    {
        $this->actingAs($this->user($role));
        $widgets = (new Dashboard)->getWidgets();
        $this->assertNotEmpty($widgets);
        foreach ($widgets as $widget) {
            $this->assertTrue($widget::canView(), $widget.' hidden for '.$role);
        }
        $otherWidget = str_starts_with($role, 'cashier') ? AdminStatsOverview::class : CashierStatsOverview::class;
        $this->assertFalse($otherWidget::canView());
    }

    public function test_login_errors_are_generic_and_valid_login_and_throttling_remain(): void
    {
        $u = $this->user('candidate');
        foreach (['missing@example.test', $u->email, $u->username] as $login) {
            Livewire::test(Login::class)->set('data.login', $login)->set('data.password', 'incorrect-password')->call('authenticate')->assertHasErrors(['data.login' => __('auth.failed')]);
            $this->assertGuest();
        }
        // A separate client IP prevents the previous negative cases consuming this limit.
        $component = Livewire::withHeaders(['REMOTE_ADDR' => '127.0.0.22'])->test(Login::class)->set('data.login', $u->email)->set('data.password', 'incorrect-password');
        for ($i = 0; $i < 6; $i++) {
            $component->call('authenticate');
        }
        $this->assertStringContainsString('attempt', strtolower(json_encode($component->errors())));
        Auth::logout();
        // Existing authentication tests verify successful email and normalized phone login.
    }

    public function test_security_headers_preserve_same_origin_pdf_support(): void
    {
        $this->get('/login')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')->assertHeader('Content-Security-Policy', "base-uri 'self'; object-src 'self'; frame-ancestors 'self'");
    }
}
