<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Models\Role;
use App\Models\SystemUser;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\WorkflowSubmissionMessages;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowRecipientLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $admin = $this->user('recipient_localization_admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        Role::findOrCreate('cashier_manager', 'web')->forceFill([
            'label_en' => 'Cashier Manager',
            'name_kh' => 'អ្នកគ្រប់គ្រងបេឡា',
        ])->save();
        $this->actingAs($admin);
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_all_seven_recipient_pickers_and_selected_labels_follow_the_locale_without_changing_ids(string $locale): void
    {
        $fixture = $this->recipients();
        $ids = array_keys($fixture['labels']['en']);
        $workflow = $this->workflow($ids);
        app()->setLocale($locale);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $pickers = $this->pickers($component);
        $this->assertCount(7, $pickers);

        foreach ($pickers as $picker) {
            $options = $picker->getOptions();
            foreach ($fixture['labels'][$locale] as $id => $label) {
                $this->assertSame($label, $options[$id]);
            }
            $this->assertEquals($ids, $picker->getState());
            $this->assertSame($fixture['labels'][$locale], $picker->getOptionLabels());
            foreach ($fixture['excluded_ids'] as $id) {
                $this->assertArrayNotHasKey($id, $options);
            }
        }
    }

    public function test_saving_and_reopening_in_another_locale_preserves_the_selected_login_user_ids(): void
    {
        $fixture = $this->recipients();
        $allIds = array_keys($fixture['labels']['en']);
        $selectedIds = array_slice($allIds, 1, 2);
        $workflow = $this->workflow([$allIds[0]]);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        foreach ($this->pickers($component) as $picker) {
            $component->set($picker->getStatePath(), $selectedIds);
        }
        $component->call('save')->assertHasNoFormErrors();
        $this->assertSelections($workflow->fresh(), $selectedIds);

        app()->setLocale('km');
        $reopened = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $expectedLabels = array_intersect_key($fixture['labels']['km'], array_flip($selectedIds));
        foreach ($this->pickers($reopened) as $picker) {
            $this->assertEquals($selectedIds, $picker->getState());
            $this->assertSame($expectedLabels, $picker->getOptionLabels());
        }
        $reopened->call('save')->assertHasNoFormErrors();
        $this->assertSelections($workflow->fresh(), $selectedIds);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_personal_names_and_custom_identifiers_are_preserved_even_when_a_role_is_assigned(): void
    {
        $personal = $this->user('personal_name', ['name' => 'Cashier Manager Assistant']);
        $personal->assignRole('cashier_manager');
        $custom = $this->user('cashier1', ['name' => '']);
        $custom->assignRole('cashier_manager');
        $bilingual = $this->user('bilingual_name', [
            'name' => 'Sok Dara',
            'first_name_kh' => 'សុខ',
            'last_name_kh' => 'ដារ៉ា',
            'name_latin' => 'Sok Dara',
        ]);
        $bilingual->assignRole('cashier_manager');

        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $options = WorkflowSubmissionMessages::recipientOptions();
            $this->assertSame('Cashier Manager Assistant', $options[$personal->getKey()]);
            $this->assertSame('cashier1', $options[$custom->getKey()]);
            $this->assertSame($locale === 'km' ? 'សុខ ដារ៉ា' : 'Sok Dara', $options[$bilingual->getKey()]);
            $this->assertSame('សុខ ដារ៉ា', $bilingual->getFilamentName());
        }
    }

    private function pickers($component)
    {
        return collect($component->instance()->form->getFlatFields())
            ->filter(fn ($field): bool => str_ends_with($field->getStatePath(), '.staff_recipient_ids'))
            ->values();
    }

    private function assertSelections(WorkflowNotification $workflow, array $ids): void
    {
        $this->assertEquals($ids, data_get($workflow->stages, '0.data.submission_messages.staff_recipient_ids'));
        foreach (['accept', 'send_back', 'reject'] as $action) {
            $this->assertEquals($ids, data_get($workflow->stages, '1.data.review_actions.'.$action.'.staff_recipient_ids'));
        }
        foreach ([2, 3, 4] as $index) {
            $this->assertEquals($ids, data_get($workflow->stages, $index.'.data.audience_messages.staff_recipient_ids'));
        }
    }

    private function workflow(array $ids): WorkflowNotification
    {
        $stages = [
            ['type' => 'stage', 'data' => [
                'stage_name' => 'Submit',
                'stage_type' => 'form_submission',
                'submission_messages' => ['staff_recipient_ids' => $ids],
            ]],
            ['type' => 'stage', 'data' => [
                'stage_name' => 'Review',
                'stage_type' => 'review',
                'responsible_role' => ['admin'],
                'review_actions' => array_fill_keys(['accept', 'send_back', 'reject'], ['staff_recipient_ids' => $ids]),
            ]],
        ];
        foreach (['payment', 'awaiting_results', 'completed'] as $type) {
            $stages[] = ['type' => 'stage', 'data' => [
                'stage_name' => $type,
                'stage_type' => $type,
                'responsible_role' => ['admin'],
                'audience_messages' => ['staff_recipient_ids' => $ids],
            ]];
        }

        return WorkflowNotification::query()->create(['name' => 'Localized workflow recipients', 'stages' => $stages]);
    }

    private function recipients(): array
    {
        $generic = $this->user('cashier_manager_account', ['name' => '  CASHIER_Manager  ']);
        $generic->assignRole('cashier_manager');
        $personal = $this->user('personal_recipient', [
            'name' => 'Sok Dara',
            'first_name_kh' => 'សុខ',
            'last_name_kh' => 'ដារ៉ា',
            'name_latin' => 'Sok Dara',
        ]);
        $personal->assignRole('cashier_manager');
        SystemUser::query()->forceCreate([
            'name' => 'Different linked display name',
            'username' => $personal->username,
            'first_name_kh' => 'ឈ្មោះ',
            'last_name_kh' => 'ផ្សេង',
            'first_name_latin' => 'Different',
            'last_name_latin' => 'Name',
            'password' => 'password',
            'is_active' => true,
        ]);
        $linked = $this->user('linked_recipient');
        SystemUser::query()->forceCreate([
            'name' => 'Linked account display name',
            'username' => $linked->username,
            'first_name_kh' => 'ចាន់',
            'last_name_kh' => 'សុភា',
            'first_name_latin' => 'Chan',
            'last_name_latin' => 'Sophea',
            'password' => 'password',
            'is_active' => true,
        ]);
        $custom = $this->user('cashier1');
        $inactive = $this->user('inactive_recipient', ['is_active' => false]);
        $candidate = $this->user('candidate_recipient', ['registration_type' => 'student']);
        $deleted = $this->user('deleted_recipient');
        $deleted->delete();

        return [
            'labels' => [
                'en' => [
                    $generic->getKey() => 'Cashier Manager',
                    $personal->getKey() => 'Sok Dara',
                    $linked->getKey() => 'Chan Sophea',
                    $custom->getKey() => 'cashier1',
                ],
                'km' => [
                    $generic->getKey() => 'អ្នកគ្រប់គ្រងបេឡា',
                    $personal->getKey() => 'សុខ ដារ៉ា',
                    $linked->getKey() => 'ចាន់ សុភា',
                    $custom->getKey() => 'cashier1',
                ],
            ],
            'excluded_ids' => [$inactive->getKey(), $candidate->getKey(), $deleted->getKey()],
        ];
    }

    private function user(string $username, array $attributes = []): User
    {
        return User::query()->forceCreate(array_replace([
            'registration_type' => 'admin',
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ], $attributes));
    }
}
