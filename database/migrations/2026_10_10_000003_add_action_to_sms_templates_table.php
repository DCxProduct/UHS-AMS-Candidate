<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The workflow action an SMS template is used for automatically (one template per action).
        Schema::table('sms_templates', function (Blueprint $table): void {
            $table->string('action')->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('sms_templates', function (Blueprint $table): void {
            $table->dropUnique(['action']);
            $table->dropColumn('action');
        });
    }
};
