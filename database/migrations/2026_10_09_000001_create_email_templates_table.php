<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {


        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('header_title')->nullable();

            foreach (['en', 'km'] as $locale) {
                $table->string("subject_{$locale}")->nullable();
                $table->string("button_{$locale}")->nullable();
                $table->longText("body_{$locale}")->nullable();
            }

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
