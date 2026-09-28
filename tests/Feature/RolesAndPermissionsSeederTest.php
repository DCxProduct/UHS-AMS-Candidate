<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_receives_payment_type_and_exchange_rate_permissions(): void
    {
        $permissions = [
            'ViewAny:Payment',
            'ViewAny:UnpaidApplication',
            'ViewAny:PaymentType',
            'Create:PaymentType',
            'Update:PaymentType',
            'Delete:PaymentType',
            'ViewAny:ExchangeRate',
            'Update:ExchangeRate',
            'ViewAny:CandidateRequested',
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        Artisan::shouldReceive('call')->once()->andReturn(0);

        (new RolesAndPermissionsSeeder)->run();

        $cashier = \App\Models\Role::query()
            ->where('name', 'cashier')
            ->where('guard_name', 'web')
            ->firstOrFail();

        foreach ([
            'ViewAny:Payment',
            'ViewAny:UnpaidApplication',
            'ViewAny:PaymentType',
            'Create:PaymentType',
            'Update:PaymentType',
            'Delete:PaymentType',
            'ViewAny:ExchangeRate',
            'Update:ExchangeRate',
        ] as $permission) {
            $this->assertTrue($cashier->hasPermissionTo($permission), $permission);
        }

        $this->assertFalse($cashier->hasPermissionTo('ViewAny:CandidateRequested'));
    }

    public function test_registrar_receives_candidate_submit_popup_access_without_payment_settings_access(): void
    {
        $permissions = [
            'ViewAny:CandidateSubmitPopupSetting',
            'Update:CandidateSubmitPopupSetting',
            'ViewAny:CandidateRequested',
            'ViewAny:PaymentType',
            'Create:PaymentType',
            'Update:PaymentType',
            'Delete:PaymentType',
            'ViewAny:ExchangeRate',
            'Update:ExchangeRate',
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        Artisan::shouldReceive('call')->once()->andReturn(0);

        (new RolesAndPermissionsSeeder)->run();

        $registrar = \App\Models\Role::query()
            ->where('name', 'registrar')
            ->where('guard_name', 'web')
            ->firstOrFail();

        foreach ([
            'ViewAny:CandidateSubmitPopupSetting',
            'Update:CandidateSubmitPopupSetting',
            'ViewAny:CandidateRequested',
        ] as $permission) {
            $this->assertTrue($registrar->hasPermissionTo($permission), $permission);
        }

        foreach ([
            'ViewAny:PaymentType',
            'Create:PaymentType',
            'Update:PaymentType',
            'Delete:PaymentType',
            'ViewAny:ExchangeRate',
            'Update:ExchangeRate',
        ] as $permission) {
            $this->assertFalse($registrar->hasPermissionTo($permission), $permission);
        }
    }
}
