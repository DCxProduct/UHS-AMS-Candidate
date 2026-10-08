<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uploaded logo shown at the top of the email (stored on the public disk).
        Schema::table('email_templates', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('header_title');
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
