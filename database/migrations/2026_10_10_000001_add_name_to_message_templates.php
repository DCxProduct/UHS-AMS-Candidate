<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Name of a template created by an admin; built-in templates use their translated name.
        foreach (['email_templates', 'sms_templates'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->string('name')->nullable()->after('key');
            });
        }
    }

    public function down(): void
    {
        foreach (['email_templates', 'sms_templates'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('name');
            });
        }
    }
};
