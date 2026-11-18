<?php

namespace Tests\Feature;

use App\Models\WorkflowNotification;
use App\Support\WorkflowStatusSearch;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkflowStatusSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
        DB::statement('PRAGMA case_sensitive_like = ON');
    }

    protected function tearDown(): void
    {
        DB::statement('PRAGMA case_sensitive_like = OFF');
        parent::tearDown();
    }

    public function test_frozen_stages_take_precedence_over_changed_templates_and_static_snapshots_stay_static(): void
    {
        $form = $this->form('frozen-status');
        $template = $this->template($form, [['data' => ['stage_type' => 'review', 'status_message' => 'Changed Template Review']]]);
        $wrapped = $this->entry($form, ['has_workflow' => true, 'stages' => [['data' => ['stage_type' => 'review', 'status_message' => 'Original Frozen Review']]]]);
        $flat = $this->entry($form, ['has_workflow' => true, 'stages' => [null, 'ignored scalar', ['stage_type' => 'approval', 'status_message' => 'Original Frozen Review']]]);
        $legacy = $this->entry($form, null);
        $static = $this->entry($form, ['has_workflow' => false, 'stages' => [['stage_type' => 'review', 'status_message' => 'Original Frozen Review']]]);
        $empty = $this->entry($form, []);

        $this->assertSame([$wrapped->id, $flat->id], $this->search('review', 'status_message', 'oRiGiNaL')->pluck('id')->all());
        $this->assertSame([$legacy->id], $this->search('review', 'status_message', 'cHaNgEd')->pluck('id')->all());
        $this->assertSame([$wrapped->id, $flat->id, $legacy->id], $this->search('review', 'status_message', 'REVIEW')->pluck('id')->all());
        $this->assertSame('Changed Template Review', $template->fresh()->stages[0]['data']['status_message']);
        $this->assertFalse($static->fresh()->workflow_snapshot['has_workflow']);
        $this->assertSame([], $empty->fresh()->workflow_snapshot);
    }

    public function test_nested_localized_text_is_matched_only_for_the_requested_stage_and_field(): void
    {
        $form = $this->form('nested-status');
        $stages = [
            ['stage_type' => 'review', 'status_message' => 'Unrelated Label'],
            ['data' => ['stage_type' => 'awaiting_results', 'result_statuses' => [
                'pending_status_message' => ['en' => 'Waiting Clinical Results', 'km' => 'រង់ចាំលទ្ធផល'],
                'passed_status_message' => 'Completed Clinical Results',
            ]]],
        ];
        $this->template($form, $stages);
        $frozen = $this->entry($form, ['has_workflow' => true, 'stages' => $stages]);
        $legacy = $this->entry($form, null);
        $this->assertSame([$frozen->id, $legacy->id], $this->search('awaiting_results', 'result_statuses.pending_status_message', 'WAITING')->pluck('id')->all());
        $this->assertSame([], $this->search('review', 'status_message', 'WAITING')->pluck('id')->all());
        $this->assertSame([], $this->search('awaiting_results', 'result_statuses.passed_status_message', 'WAITING')->pluck('id')->all());

        app()->setLocale('km');
        $this->assertSame([$frozen->id, $legacy->id], $this->search('awaiting_results', 'result_statuses.pending_status_message', 'រង់ចាំ')->pluck('id')->all());
    }

    public function test_stage_text_search_preserves_outer_form_status_and_visibility_constraints(): void
    {
        $form = $this->form('scoped-status');
        $otherForm = $this->form('outside-status');
        $stages = [['stage_type' => 'payment', 'status_message' => 'Payment Confirmed']];
        $this->template($form, $stages);
        $this->template($otherForm, $stages);
        $match = $this->entry($form, ['has_workflow' => true, 'stages' => $stages]);
        $legacy = $this->entry($form, null);
        $this->entry($otherForm, ['has_workflow' => true, 'stages' => $stages]);
        $this->entry($otherForm, null);
        $this->entry($form, ['has_workflow' => true, 'stages' => $stages], ['review_status' => 'failed']);
        $this->entry($form, null, ['form_entries_deleted_at' => now()]);
        $query = CustomFormEntry::query()->where('custom_form_id', $form->id)
            ->where('review_status', 'pending')->whereNull('form_entries_deleted_at');
        WorkflowStatusSearch::applyStageText($query, 'payment', 'status_message', 'pAyMeNt');
        $this->assertSame([$match->id, $legacy->id], $query->orderBy('id')->pluck('id')->all());
    }

    public function test_legacy_search_uses_the_first_template_when_old_assignments_have_duplicates(): void
    {
        Schema::table('workflow_notification_forms', fn (Blueprint $table) => $table->dropUnique('workflow_notification_forms_custom_form_id_unique'));
        $form = $this->form('duplicate-template-status');
        $this->template($form, [['stage_type' => 'review', 'status_message' => 'First Visible Label']]);
        $this->template($form, [['stage_type' => 'review', 'status_message' => 'Later Hidden Label']]);
        $legacy = $this->entry($form, null);

        $this->assertSame([$legacy->id], $this->search('review', 'status_message', 'FIRST')->pluck('id')->all());
        $this->assertSame([], $this->search('review', 'status_message', 'LATER')->pluck('id')->all());
    }

    public function test_pre_snapshot_schema_search_uses_legacy_template_labels(): void
    {
        $form = $this->form('before-snapshot-status');
        $this->template($form, [['stage_type' => 'review', 'status_message' => 'Legacy Configured Review']]);
        $legacy = $this->entry($form, null);
        Schema::table('custom_form_entries', fn (Blueprint $table) => $table->dropColumn('workflow_snapshot'));

        $this->assertSame([$legacy->id], $this->search('review', 'status_message', 'CONFIGURED')->pluck('id')->all());
        $this->assertSame([], $this->search('review', 'status_message', 'missing-label')->pluck('id')->all());
    }

    private function search(string $type, string $field, string $term)
    {
        return WorkflowStatusSearch::applyStageText(CustomFormEntry::query(), $type, $field, $term)->orderBy('id');
    }

    private function form(string $slug): CustomForm
    {
        return CustomForm::create(['name' => $slug, 'slug' => $slug, 'is_active' => true]);
    }

    private function template(CustomForm $form, array $stages): WorkflowNotification
    {
        $template = WorkflowNotification::create(['name' => $form->slug, 'stages' => $stages]);
        $template->forms()->attach($form);

        return $template;
    }

    private function entry(CustomForm $form, ?array $snapshot, array $attributes = []): CustomFormEntry
    {
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $form->id, 'data' => [], 'review_status' => 'pending',
            'workflow_snapshot' => $snapshot, ...$attributes,
        ])->saveQuietly();

        return $entry;
    }
}
