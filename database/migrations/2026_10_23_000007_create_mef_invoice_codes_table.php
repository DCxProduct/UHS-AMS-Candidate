<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'ViewAny:MefInvoiceCode',
        'Create:MefInvoiceCode',
        'Update:MefInvoiceCode',
        'Delete:MefInvoiceCode',
    ];

    public function up(): void
    {
        Schema::create('mef_invoice_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('mef_code', 100);
            $table->string('invoice_code', 100);
            $table->timestamps();
            $table->unique(['mef_code', 'invoice_code'], 'mef_invoice_codes_pair_unique');
        });

        $this->addCrudPermissions();
    }

    public function down(): void
    {
        $this->removeCrudPermissions();
        Schema::dropIfExists('mef_invoice_codes');
    }

    private function addCrudPermissions(): void
    {
        $tables = config('permission.table_names');

        if (
            ! is_array($tables)
            || ! Schema::hasTable($tables['permissions'] ?? 'permissions')
            || ! Schema::hasTable($tables['roles'] ?? 'roles')
            || ! Schema::hasTable($tables['role_has_permissions'] ?? 'role_has_permissions')
        ) {
            return;
        }

        foreach (self::PERMISSIONS as $name) {
            DB::table($tables['permissions'])->insertOrIgnore([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permissionIds = DB::table($tables['permissions'])
            ->where('guard_name', 'web')
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id', 'name');

        foreach ([
            'admin' => self::PERMISSIONS,
            'crud' => self::PERMISSIONS,
            'delete' => ['ViewAny:MefInvoiceCode', 'Delete:MefInvoiceCode'],
            'viewer' => ['ViewAny:MefInvoiceCode'],
            'data_entry' => ['ViewAny:MefInvoiceCode', 'Create:MefInvoiceCode', 'Update:MefInvoiceCode'],
            'registrar_officer' => self::PERMISSIONS,
        ] as $roleName => $permissionNames) {
            $roleId = DB::table($tables['roles'])
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->value('id');

            if (! $roleId) {
                continue;
            }

            foreach ($permissionNames as $name) {
                if (isset($permissionIds[$name])) {
                    DB::table($tables['role_has_permissions'])->insertOrIgnore([
                        'permission_id' => $permissionIds[$name],
                        'role_id' => $roleId,
                    ]);
                }
            }
        }
    }

    private function removeCrudPermissions(): void
    {
        $tables = config('permission.table_names');

        if (
            ! is_array($tables)
            || ! Schema::hasTable($tables['permissions'] ?? 'permissions')
            || ! Schema::hasTable($tables['role_has_permissions'] ?? 'role_has_permissions')
        ) {
            return;
        }

        $permissionIds = DB::table($tables['permissions'])
            ->where('guard_name', 'web')
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id');

        DB::table($tables['role_has_permissions'])
            ->whereIn('permission_id', $permissionIds)
            ->delete();
        DB::table($tables['permissions'])->whereIn('id', $permissionIds)->delete();
    }
};
