<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('username', 100)->nullable()->unique();
            $table->string('email', 150)->nullable()->unique();
            $table->string('phone', 30)->nullable()->unique();
            $table->string('password');
            $table->string('avatar')->nullable();
            $table->json('roles')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('username');
            $table->index('email');
            $table->index('phone');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_users');
    }
};
