<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_form_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_form_id')->constrained('custom_forms')->cascadeOnDelete();
            $table->string('panel');
            $table->json('allowed_users')->nullable();
            $table->json('allowed_roles')->nullable();
            $table->boolean('isolate_user_data')->default(false);
            $table->json('permissions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_form_authorizations');
    }
};
