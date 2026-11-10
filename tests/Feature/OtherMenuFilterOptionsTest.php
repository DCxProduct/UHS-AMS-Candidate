<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Admin\Resources\CandidateLists\Pages\ListCandidateLists;
use App\Filament\Admin\Resources\ClosingDates\Pages\ListClosingDates;
use App\Filament\Admin\Resources\GeoLocations\Pages\ListGeoLocations;
use App\Filament\Admin\Resources\Payments\Pages\ListPayments;
use App\Filament\Admin\Resources\SystemUsers\Pages\ListSystemUsers;
use App\Models\AuditLog;
use App\Models\ClosingDate;
use App\Models\DegreeLevel;
use App\Models\GeoLocation;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SystemUser;
use App\Models\User;
use App\Models\UserType;
use App\Support\FormEntryData;
use App\Support\UserTypeOptions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\TestCase;

class OtherMenuFilterOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['fieldOptionCache', 'majorIdLabelCache'] as $property) {
            (new ReflectionProperty(FormEntryData::class, $property))->setValue(null, []);
        }
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(12, 0));
        $admin = $this->user('filter_admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        foreach (['fieldOptionCache', 'majorIdLabelCache'] as $property) {
            (new ReflectionProperty(FormEntryData::class, $property))->setValue(null, []);
        }
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_closing_date_options_cascade_but_do_not_trap_their_own_selected_value(): void
    {
        $one = DegreeLevel::create(['key' => 'one', 'label_en' => 'Degree One', 'is_active' => true]);
        $two = DegreeLevel::create(['key' => 'two', 'label_en' => 'Degree Two', 'is_active' => true]);
        DegreeLevel::create(['key' => 'unused', 'label_en' => 'Unused Degree', 'is_active' => true]);
        foreach ([[$one, 'open', 'Alpha'], [$one, 'closed', 'Alpha Closed'], [$two, 'closed', 'Beta']] as [$degree, $status, $name]) {
            ClosingDate::create([
                'name' => $name, 'degree_level_id' => $degree->id, 'type' => 'custom_form:1',
                'status' => $status, 'start_date' => '2026-11-01', 'end_date' => '2026-11-30',
            ]);
        }

        $component = Livewire::test(ListClosingDates::class)
            ->filterTable('degree_level_id', $one->id)->filterTable('status', 'open');
        $this->assertSame([$one->id => 'Degree One'], $component->instance()->getTable()->getFilter('degree_level_id')->getOptions());
        $this->assertSame(['closed' => 'Closed', 'open' => 'Open'], $component->instance()->getTable()->getFilter('status')->getOptions());

        $component->filterTable('status', 'closed');
        $this->assertSame([$one->id => 'Degree One', $two->id => 'Degree Two'], $component->instance()->getTable()->getFilter('degree_level_id')->getOptions());
        $this->assertSame((string) $one->id, (string) $component->get('tableFilters.degree_level_id.value'));
        $component->searchTable('Beta');
        $this->assertSame([$two->id => 'Degree Two'], $component->instance()->getTable()->getFilter('degree_level_id')->getOptions());
    }

    public function test_payment_choices_respect_dates_receivers_and_search_without_inventing_a_receiver(): void
    {
        $other = $this->user('other_receiver');
        $unused = $this->user('unused_receiver');
        foreach ([['TODAY-A', auth()->id(), '2026-11-10 10:00:00'], ['TODAY-B', $other->id, '2026-11-10 11:00:00'], ['OLD', $unused->id, '2026-11-09 10:00:00']] as [$receipt, $receiver, $date]) {
            Payment::create(['receipt_number' => $receipt, 'received_by' => $receiver, 'datetime_pay' => $date, 'amount_usd' => 5]);
        }
        $component = Livewire::test(ListPayments::class)->set('tableFilters.payment_filters.received_by', null);
        $this->assertSame(['TODAY-A' => 'TODAY-A', 'TODAY-B' => 'TODAY-B'], $this->filterOptions($component, 'payment_filters.receipt_number'));
        $this->assertEqualsCanonicalizing([auth()->id(), $other->id], array_keys($this->filterOptions($component, 'payment_filters.received_by')));

        $component->set('tableFilters.payment_filters.receipt_number', 'TODAY-A');
        $this->assertSame([(string) auth()->id() => auth()->user()->name], $this->filterOptions($component, 'payment_filters.received_by'));
        $this->assertCount(2, $this->filterOptions($component, 'payment_filters.receipt_number'));
        $component->searchTable('TODAY-A');
        $this->assertSame(['TODAY-A' => 'TODAY-A'], $this->filterOptions($component, 'payment_filters.receipt_number'));
    }

    public function test_audit_choices_use_other_filters_and_keep_candidate_activity_excluded(): void
    {
        foreach ([['Payments', 'created', 'registrar'], ['Payments', 'updated', 'registrar'], ['Custom Forms', 'created', 'registrar'], ['Hidden Module', 'deleted', 'student']] as [$module, $action, $role]) {
            AuditLog::forceCreate([
                'actor_type' => User::class, 'actor_id' => auth()->id(), 'actor_name' => 'Audit Staff',
                'actor_role' => $role, 'module' => $module, 'action' => $action,
            ]);
        }
        $component = Livewire::test(ListAuditLogs::class)->filterTable('audit_log_filters', ['module' => 'Payments', 'action' => 'created']);
        $this->assertEqualsCanonicalizing(['created', 'updated'], array_keys($this->filterOptions($component, 'audit_log_filters.action')));
        $this->assertEqualsCanonicalizing(['Payments', 'Custom Forms'], array_keys($this->filterOptions($component, 'audit_log_filters.module')));
    }

    public function test_geo_location_choices_keep_only_ancestors_of_matching_visible_rows(): void
    {
        $province = GeoLocation::create(['type' => 'province', 'code' => '11', 'name_en' => 'Visible Province', 'is_active' => false]);
        $district = GeoLocation::create(['type' => 'district', 'code' => '1101', 'parent_id' => $province->id, 'name_en' => 'Visible District', 'is_active' => true]);
        $commune = GeoLocation::create(['type' => 'commune', 'code' => '110101', 'parent_id' => $district->id, 'name_en' => 'Visible Commune', 'is_active' => true]);
        GeoLocation::create(['type' => 'village', 'code' => '11010101', 'parent_id' => $commune->id, 'name_en' => 'Needle Village', 'is_active' => true]);
        GeoLocation::create(['type' => 'province', 'code' => '12', 'name_en' => 'Unrelated Province', 'is_active' => true]);

        $component = Livewire::test(ListGeoLocations::class)->searchTable('Needle');
        $this->assertSame(['village'], array_keys($this->filterOptions($component, 'geo_location_filter.type')));
        $this->assertSame([$province->id => 'Visible Province'], $this->filterOptions($component, 'geo_location_filter.province_id'));
        $component->set('tableFilters.geo_location_filter.type', 'village');
        $this->assertSame([$district->id => 'Visible District'], $this->filterOptions($component, 'geo_location_filter.district_id'));
    }

    public static function userMenus(): array
    {
        return [[ListCandidateLists::class, true], [ListSystemUsers::class, false]];
    }

    #[DataProvider('userMenus')]
    public function test_user_role_and_active_choices_only_include_roles_on_matching_rows(string $page, bool $candidates): void
    {
        $roles = $candidates ? ['candidate_facet_a', 'candidate_facet_b'] : ['cashier', 'registrar'];
        foreach ($roles as $role) {
            if ($candidates) {
                UserType::create(['key' => $role, 'label_en' => $role, 'label_kh' => $role, 'is_active' => true]);
            } else {
                Role::findOrCreate($role, 'web');
            }
        }
        foreach ([[$roles[0], true], [$roles[1], false], [$roles[1], true]] as $index => [$role, $active]) {
            SystemUser::create([
                'name' => 'Facet user '.$index, 'username' => 'facet_user_'.$index,
                'email' => 'facet_user_'.$index.'@example.test', 'password' => 'password',
                'roles' => [$role], 'is_active' => $active,
            ]);
        }
        foreach (['optionsCache' => [], 'candidateManagedRoleKeysCache' => null, 'normalizedUserTypeCache' => []] as $property => $value) {
            (new ReflectionProperty(UserTypeOptions::class, $property))->setValue(null, $value);
        }

        $component = Livewire::test($page)->filterTable('is_active', '1')->filterTable('roles', $roles[0]);
        $this->assertEqualsCanonicalizing($candidates ? $roles : [...$roles, 'admin'], array_keys($component->instance()->getTable()->getFilter('roles')->getOptions()));
        $this->assertSame([1], array_keys($component->instance()->getTable()->getFilter('is_active')->getOptions()));
        $component->filterTable('roles', $roles[1]);
        $this->assertEqualsCanonicalizing([0, 1], array_keys($component->instance()->getTable()->getFilter('is_active')->getOptions()));
    }

    private function filterOptions($component, string $path): array
    {
        return $component->instance()->getTableFiltersForm()->getComponentByStatePath($path)->getOptions();
    }

    private function user(string $username): User
    {
        return User::create([
            'registration_type' => 'admin', 'name' => $username, 'username' => $username,
            'email' => $username.'@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true,
        ]);
    }
}
