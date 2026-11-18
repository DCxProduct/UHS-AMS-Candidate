<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowButtons\Pages\ListWorkflowButtons;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Database\Seeders\WorkflowButtonSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowButtonSeederTest extends TestCase
{
    use RefreshDatabase;

    private const DEFAULTS = [
        'submitted' => ['heroicon-o-paper-airplane', 'info'],
        'verify' => ['heroicon-o-shield-check', 'info'],
        'accept' => ['heroicon-o-check-circle', 'success'],
        'send_back' => ['heroicon-o-arrow-uturn-left', 'warning'],
        'reject' => ['heroicon-o-x-circle', 'danger'],
        'paid' => ['heroicon-o-banknotes', 'success'],
        'passed' => ['heroicon-o-check-circle', 'success'],
        'notify' => ['heroicon-o-bell-alert', 'info'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        Notification::fake();
        $admin = User::query()->create(['registration_type' => 'admin', 'name' => 'Button seed admin', 'username' => 'button-seed-admin', 'email' => 'button-seed-admin@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        app()->setLocale('en');
        parent::tearDown();
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_empty_catalog_receives_eight_real_localized_defaults_independently_of_current_locale(string $locale): void
    {
        $this->assertDatabaseCount('workflow_buttons', 8);
        WorkflowButton::query()->delete();
        app()->setLocale($locale);

        (new WorkflowButtonSeeder)->run();
        (new WorkflowButtonSeeder)->run();

        $buttons = WorkflowButton::query()->orderBy('id')->get();
        $this->assertCount(8, $buttons);
        $this->assertSame(array_keys(self::DEFAULTS), $buttons->pluck('action_key')->all());
        $component = Livewire::test(ListWorkflowButtons::class)->assertSuccessful()->assertCanSeeTableRecords($buttons);
        foreach ($buttons as $button) {
            [$icon, $color] = self::DEFAULTS[$button->action_key];
            $this->assertSame(Lang::get('workflow_stage_types.button_actions.'.$button->action_key, [], 'en'), $button->label_en);
            $this->assertSame(Lang::get('workflow_stage_types.button_actions.'.$button->action_key, [], 'km'), $button->label_km);
            $this->assertMatchesRegularExpression('/[\x{1780}-\x{17FF}]/u', $button->label_km);
            $this->assertSame($icon, $button->icon);
            $this->assertSame($color, $button->color);
            $this->assertTrue($button->is_active);
            $component->assertTableColumnStateSet('label', $button->label(), $button);
        }
        $this->assertDatabaseMissing('workflow_buttons', ['action_key' => 'unpaid']);
        $this->assertDatabaseMissing('workflow_buttons', ['action_key' => 'pending']);
        Notification::assertNothingSent();
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_seeding_is_additive_preserves_custom_inactive_buttons_and_does_not_rewrite_legacy_configuration_or_snapshots(): void
    {
        WorkflowButton::query()->delete();
        $inactive = WorkflowButton::query()->create(['label_en' => 'Keep inactive accept', 'label_km' => null, 'action_key' => 'accept', 'icon' => 'heroicon-o-pencil-square', 'color' => 'warning', 'is_active' => false]);
        WorkflowButton::query()->create(['label_en' => 'Keep alternate accept', 'label_km' => 'ទទួលពាក្យផ្ទាល់ខ្លួន', 'action_key' => 'accept', 'color' => 'danger', 'is_active' => true]);
        WorkflowButton::query()->create(['label_en' => 'Keep inactive notification', 'action_key' => 'notify', 'color' => 'gray', 'is_active' => false]);
        $customBefore = $this->rows('workflow_buttons');
        $type = WorkflowStageType::query()->create(['label_en' => 'Legacy unbound review', 'label_km' => 'ពិនិត្យចាស់', 'behavior' => 'review', 'enabled_actions' => null, 'review_actions' => ['accept' => ['status_message' => 'Keep stored review message']]]);
        $stages = [['type' => 'stage', 'data' => ['stage_name' => 'Keep workflow stage', 'stage_type' => 'review', 'stage_type_id' => $type->id, 'responsible_role' => 'registrar_officer', 'buttons' => [$inactive->toDefinition()]]]];
        $workflow = WorkflowNotification::withoutEvents(fn (): WorkflowNotification => WorkflowNotification::query()->create(['name' => 'Keep stored workflow', 'stages' => $stages]));
        $form = CustomForm::query()->create(['name' => 'Keep assigned form', 'slug' => 'keep-seeded-button-form', 'is_active' => true]);
        $workflow->forms()->attach($form);
        CustomFormEntry::withoutEvents(fn (): CustomFormEntry => CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->id, 'created_by' => auth()->id(), 'review_status' => 'pending', 'data' => ['registration_status' => 'pending'], 'workflow_snapshot' => ['schema_version' => 2, 'has_workflow' => true, 'workflow_id' => $workflow->id, 'stages' => $stages]]));
        $before = [];
        foreach (['workflow_stage_types', 'workflow_notifications', 'workflow_notification_forms', 'custom_form_entries', 'notifications'] as $table) {
            $before[$table] = $this->rows($table);
        }

        (new WorkflowButtonSeeder)->run();
        $afterFirst = $this->rows('workflow_buttons');
        (new WorkflowButtonSeeder)->run();

        $this->assertSame($afterFirst, $this->rows('workflow_buttons'));
        $this->assertSame($customBefore, array_slice($afterFirst, 0, count($customBefore)));
        $this->assertDatabaseCount('workflow_buttons', 9);
        $this->assertSame(2, WorkflowButton::query()->where('action_key', 'accept')->count());
        $this->assertSame(1, WorkflowButton::query()->where('action_key', 'notify')->count());
        $this->assertFalse($inactive->fresh()->is_active);
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, $this->rows($table), $table);
        }
        Notification::assertNothingSent();
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_data_migration_fills_missing_defaults_and_rollback_preserves_live_button_configuration(): void
    {
        $custom = WorkflowButton::query()->create(['label_en' => 'Live custom accept', 'action_key' => 'accept', 'is_active' => false, 'color' => 'warning']);
        $before = $custom->fresh()->getRawOriginal();
        WorkflowButton::query()->where('action_key', 'verify')->delete();
        $migration = require database_path('migrations/2026_11_19_000001_seed_default_workflow_buttons.php');

        $migration->up();
        $this->assertDatabaseCount('workflow_buttons', 9);
        $this->assertDatabaseHas('workflow_buttons', ['action_key' => 'verify']);
        $this->assertSame($before, $custom->fresh()->getRawOriginal());
        $afterUp = $this->rows('workflow_buttons');
        $migration->down();
        $this->assertSame($afterUp, $this->rows('workflow_buttons'));
    }

    private function rows(string $table): array
    {
        return DB::table($table)->get()->map(fn ($row): array => (array) $row)->all();
    }
}
