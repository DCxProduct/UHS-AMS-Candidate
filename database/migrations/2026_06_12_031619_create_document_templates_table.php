<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();

            $table->foreignId('custom_form_id')
                ->nullable()
                ->constrained('custom_forms')
                ->nullOnDelete();

            $table->string('model_class')->nullable();
            $table->longText('content')->nullable();
            $table->json('page_settings')->nullable();
            $table->json('extra_data_sources')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('document_templates');
    }
};
