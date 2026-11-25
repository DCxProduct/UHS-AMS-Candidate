<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ClosingDates\Pages\ListClosingDates;
use App\Models\Role;
use App\Models\User;
use App\Support\SubmissionNextSteps;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class SubmissionNextStepsSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_submitted_page_stays_unchanged_in_both_languages_while_draft_uses_latest_settings_on_submission(): void
    {
        $form = $this->form();
        $completed = $this->entry($form);
        $draft = $this->entry($form, 'draft');
        $originalSnapshot = $completed->submission_next_steps_snapshot;
        $this->assertSame($this->settings('Old'), $originalSnapshot['settings']);
        $this->assertNull($draft->submission_next_steps_snapshot);

        SubmissionNextSteps::updateSettings($form->id, $this->settings('New'));
        $this->assertSame('Old heading', SubmissionNextSteps::contentForEntry($completed)['heading']);
        $this->assertSame('New heading', SubmissionNextSteps::contentForEntry($draft->fresh())['heading']);
        $this->assertNull($draft->fresh()->submission_next_steps_snapshot);
        app()->setLocale('km');
        $this->assertSame('Old ខ្មែរ', SubmissionNextSteps::contentForEntry($completed)['title']);
        $this->assertSame('Old បន្ត', SubmissionNextSteps::contentForEntry($completed)['continue_label']);
        app()->setLocale('en');

        $draft->forceFill(['review_status' => 'pending', 'data' => ['registration_status' => 'pending', 'answer' => 'Final answer']])->save();
        $this->assertSame('New heading', SubmissionNextSteps::contentForEntry($draft->fresh())['heading']);
        $this->assertSame($this->settings('New'), $draft->fresh()->submission_next_steps_snapshot['settings']);
        $this->assertSame('New heading', SubmissionNextSteps::contentForEntry($this->entry($form))['heading']);
        $this->assertSame($originalSnapshot, $completed->fresh()->submission_next_steps_snapshot);

        SubmissionNextSteps::updateSettings($form->id, [...$this->settings('New'), 'is_active' => false]);
        $this->assertSame('Old heading', SubmissionNextSteps::contentForEntry($completed)['heading']);
        $this->assertSame('New heading', SubmissionNextSteps::contentForEntry($draft)['heading']);
        $this->assertNull(SubmissionNextSteps::contentForEntry($this->entry($form)));
    }

    public function test_enabling_a_page_later_does_not_change_completed_entries_that_had_no_page(): void
    {
        $form = $this->form();
        $form->updateQuietly(['submission_next_steps' => null]);
        $completed = $this->entry($form);
        $snapshot = $completed->submission_next_steps_snapshot;
        $this->assertSame([], $snapshot['settings']);

        SubmissionNextSteps::updateSettings($form->id, $this->settings('Enabled later'));
        $this->assertNull(SubmissionNextSteps::contentForEntry($completed->fresh()));
        $this->assertSame($snapshot, $completed->fresh()->submission_next_steps_snapshot);
        $this->assertSame('Enabled later heading', SubmissionNextSteps::contentForEntry($this->entry($form))['heading']);
    }

    public function test_resubmission_partial_models_and_supplied_snapshot_cannot_replace_saved_page(): void
    {
        $form = $this->form();
        $entry = $this->entry($form);
        $snapshot = $entry->submission_next_steps_snapshot;
        SubmissionNextSteps::updateSettings($form->id, $this->settings('Changed'));

        $partial = CustomFormEntry::query()->select(['id', 'custom_form_id', 'data'])->findOrFail($entry->id);
        $partial->forceFill([
            'submission_next_steps_snapshot' => ['settings' => $this->settings('Forged')],
            'data' => ['registration_status' => 'pending', 'answer' => 'Edited answer'],
        ])->save();
        $this->assertSame($snapshot, $entry->fresh()->submission_next_steps_snapshot);
        $this->assertSame('Edited answer', $entry->fresh()->data['answer']);

        $new = (new CustomFormEntry)->forceFill([
            'custom_form_id' => $form->id,
            'data' => ['registration_status' => 'pending'],
            'submission_next_steps_snapshot' => ['settings' => $this->settings('Forged')],
        ]);
        $new->save();
        $this->assertSame($this->settings('Changed'), $new->fresh()->submission_next_steps_snapshot['settings']);
    }

    public function test_settings_dialog_freezes_legacy_completions_before_updating_the_form_and_leaves_drafts_unfrozen(): void
    {
        $form = $this->form();
        $completed = $this->entry($form);
        $draft = $this->entry($form, 'draft');
        DB::table('custom_form_entries')->where('id', $completed->id)->update(['submission_next_steps_snapshot' => null]);
        $before = $completed->fresh()->getAttributes();
        $admin = User::query()->forceCreate([
            'name' => 'Snapshot administrator', 'username' => 'snapshot_admin', 'registration_type' => 'admin',
            'email' => 'snapshot-admin@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);

        Livewire::test(ListClosingDates::class)->callAction('submission_next_steps', data: [
            'custom_form_id' => $form->id, ...$this->settings('Changed in dialog'),
        ])->assertHasNoActionErrors();

        $this->assertSame('Old heading', SubmissionNextSteps::contentForEntry($completed->fresh())['heading']);
        $this->assertSame($this->settings('Old'), $completed->fresh()->submission_next_steps_snapshot['settings']);
        $this->assertNull($draft->fresh()->submission_next_steps_snapshot);
        $this->assertEquals($this->settings('Changed in dialog'), $form->fresh()->submission_next_steps);
        foreach (['data', 'review_status', 'workflow_snapshot', 'updated_at'] as $column) {
            $this->assertSame($before[$column], $completed->fresh()->getAttributes()[$column]);
        }
    }

    public function test_upgrade_migration_preserves_current_completed_messages_without_freezing_drafts(): void
    {
        $form = $this->form();
        $completed = $this->entry($form);
        $draft = $this->entry($form, 'draft');
        $before = $completed->fresh()->getAttributes();
        Schema::table('custom_form_entries', fn (Blueprint $table) => $table->dropColumn('submission_next_steps_snapshot'));

        $migration = require database_path('migrations/2026_11_25_000001_add_submission_next_steps_snapshot_to_custom_form_entries.php');
        $migration->up();

        $this->assertSame($this->settings('Old'), $completed->fresh()->submission_next_steps_snapshot['settings']);
        $this->assertNull($draft->fresh()->submission_next_steps_snapshot);
        foreach (['data', 'review_status', 'workflow_snapshot', 'updated_at'] as $column) {
            $this->assertSame($before[$column], $completed->fresh()->getAttributes()[$column]);
        }
    }

    private function form(): CustomForm
    {
        return CustomForm::query()->create([
            'name' => 'Snapshot form', 'slug' => 'snapshot-form', 'is_active' => true,
            'submission_next_steps' => $this->settings('Old'),
        ]);
    }

    private function entry(CustomForm $form, string $status = 'pending'): CustomFormEntry
    {
        return CustomFormEntry::query()->create([
            'custom_form_id' => $form->id,
            'data' => ['registration_status' => $status, 'answer' => 'Original answer'],
        ]);
    }

    private function settings(string $prefix): array
    {
        return [
            'is_active' => true, 'heading_en' => $prefix.' heading', 'heading_km' => $prefix.' ចំណងជើង',
            'title_en' => $prefix.' title', 'title_km' => $prefix.' ខ្មែរ',
            'message_en' => $prefix.' instructions', 'message_km' => $prefix.' សារណែនាំ',
            'continue_label_en' => $prefix.' continue', 'continue_label_km' => $prefix.' បន្ត',
        ];
    }
}
