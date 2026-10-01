<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            if (Schema::hasIndex('audit_logs', 'audit_logs_ip_address_index')) {
                $table->dropIndex('audit_logs_ip_address_index');
            }

            if (Schema::hasColumn('audit_logs', 'ip_address')) {
                $table->dropColumn('ip_address');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('audit_logs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('description');
                $table->index('ip_address');
            }
        });
    }
};
