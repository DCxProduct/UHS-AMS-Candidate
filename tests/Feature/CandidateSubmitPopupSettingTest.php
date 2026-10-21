<?php

namespace Tests\Feature;

use App\Models\CandidateSubmitPopupSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CandidateSubmitPopupSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_one_popup_configuration_for_each_application_form(): void
    {
        $firstFormId = $this->insertForm('Bachelor Application', 'bachelor-application', true, 1);
        $secondFormId = $this->insertForm('Master Application', 'master-application', true, 2);
        $profileFormId = $this->insertForm('Profile', 'profile', true, 3);
        $this->insertForm('Archived Application', 'archived-application', false, 4);

        CandidateSubmitPopupSetting::ensureForEachCustomForm();

        $this->assertDatabaseCount('candidate_submit_popup_settings', 5);
        $this->assertDatabaseHas('candidate_submit_popup_settings', ['custom_form_id' => $firstFormId]);
        $this->assertDatabaseHas('candidate_submit_popup_settings', ['custom_form_id' => $secondFormId]);
        $this->assertDatabaseHas('candidate_submit_popup_settings', ['id' => 1, 'custom_form_id' => null]);
        $this->assertDatabaseHas('candidate_submit_popup_settings', ['custom_form_id' => $profileFormId]);
        $this->assertDatabaseHas('candidate_submit_popup_settings', ['custom_form_id' => 4]);
    }

    public function test_popup_content_is_independent_per_form_and_legacy_global_content_is_a_fallback(): void
    {
        $firstFormId = $this->insertForm('Bachelor Application', 'bachelor-application', true, 1);
        $secondFormId = $this->insertForm('Master Application', 'master-application', true, 2);
        CandidateSubmitPopupSetting::ensureForEachCustomForm();
        $global = CandidateSubmitPopupSetting::singleton();

        $first = CandidateSubmitPopupSetting::forCustomFormId($firstFormId);
        $second = CandidateSubmitPopupSetting::forCustomFormId($secondFormId);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($global->title_en, $first->title_en);
        $first->update(['title_en' => 'Bachelor confirmation']);

        $this->assertSame('Bachelor confirmation', CandidateSubmitPopupSetting::forCustomFormId($firstFormId)?->fresh()->title_en);
        $this->assertSame($global->title_en, CandidateSubmitPopupSetting::forCustomFormId($secondFormId)?->fresh()->title_en);
        $this->assertSame($global->title_en, CandidateSubmitPopupSetting::forCustomFormId(null)?->title_en);
    }

    public function test_admin_can_see_a_separate_setting_row_for_each_application_form(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        app()->setLocale('en');
        $firstFormId = $this->insertForm('Bachelor Application', 'bachelor-application', true, 1);
        $secondFormId = $this->insertForm('Master Application', 'master-application', true, 2);
        CandidateSubmitPopupSetting::ensureForEachCustomForm();

        $user = User::query()->forceCreate([
            'registration_type' => 'admin',
            'name' => 'Popup settings admin',
            'username' => 'popup-settings-admin',
            'email' => 'popup-settings-admin@example.test',
            'date_of_birth' => '1990-01-01',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->givePermissionTo(
            Permission::findOrCreate('ViewAny:CandidateSubmitPopupSetting', 'web'),
            Permission::findOrCreate('Update:CandidateSubmitPopupSetting', 'web'),
        );
        $this->actingAs($user);

        $this->get('/candidate-submit-popup-settings')
            ->assertOk()
            ->assertSee('Bachelor Application')
            ->assertSee('Master Application')
            ->assertSee('Created At')
            ->assertSee('Updated At');

        $this->assertDatabaseHas('candidate_submit_popup_settings', ['custom_form_id' => $firstFormId]);
        $this->assertDatabaseHas('candidate_submit_popup_settings', ['custom_form_id' => $secondFormId]);
    }

    public function test_a_deleted_form_setting_stays_deleted_and_candidate_uses_the_global_fallback(): void
    {
        $formId = $this->insertForm('Bachelor Application', 'bachelor-application', true, 1);
        CandidateSubmitPopupSetting::ensureForEachCustomForm();
        $specific = CandidateSubmitPopupSetting::query()->where('custom_form_id', $formId)->firstOrFail();
        $fallbackTitle = CandidateSubmitPopupSetting::singleton()->title_en;

        $specific->delete();

        $this->assertDatabaseMissing('candidate_submit_popup_settings', ['custom_form_id' => $formId]);
        $this->assertSame($fallbackTitle, CandidateSubmitPopupSetting::forCustomFormId($formId)?->title_en);
        $this->assertDatabaseMissing('candidate_submit_popup_settings', ['custom_form_id' => $formId]);
    }

    public function test_popup_active_state_is_stored_per_form(): void
    {
        $formId = $this->insertForm('Bachelor Application', 'bachelor-application', true, 1);
        CandidateSubmitPopupSetting::ensureForEachCustomForm();
        $setting = CandidateSubmitPopupSetting::query()->where('custom_form_id', $formId)->firstOrFail();

        $this->assertTrue($setting->is_active);

        $setting->update(['is_active' => false]);

        $this->assertFalse(CandidateSubmitPopupSetting::forCustomFormId($formId)->is_active);
        $this->assertDatabaseHas('candidate_submit_popup_settings', [
            'custom_form_id' => $formId,
            'is_active' => false,
        ]);
    }

    public function test_profile_uses_its_own_active_popup_setting(): void
    {
        $profileFormId = $this->insertForm('Profile', 'profile', true, 1);
        CandidateSubmitPopupSetting::ensureForEachCustomForm();
        $profileSetting = CandidateSubmitPopupSetting::query()->where('custom_form_id', $profileFormId)->firstOrFail();
        $profileSetting->update(['title_en' => 'Confirm profile details']);

        $this->assertSame(
            'Confirm profile details',
            CandidateSubmitPopupSetting::forCustomFormId($profileFormId)?->title_en,
        );
    }

    private function insertForm(string $name, string $slug, bool $isActive, int $displayOrder): int
    {
        return DB::table('custom_forms')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'schema' => json_encode([]),
            'is_active' => $isActive,
            'allowed_roles' => json_encode(['candidate']),
            'display_order' => $displayOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
