<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('custom_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('schema')->nullable(); // The component definition
            $table->boolean('is_active')->default(true);
            $table->json('allowed_roles')->nullable();
            $table->integer('display_order')->default(0);

            $table->foreignId('custom_form_id')
                ->nullable()
                ->constrained('custom_forms')
                ->nullOnDelete();
            $table->string('menu_placement')->default('sidebar')->after('form_type_field');
            $table->string('parent_sidebar')->nullable()->after('menu_placement');
            $table->string('sub_item_type')->nullable()->after('parent_sidebar');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_forms');
    }
};
