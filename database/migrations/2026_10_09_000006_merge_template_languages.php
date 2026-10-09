<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Email and SMS templates use one text that admins write in any language,
 * instead of separate English and Khmer versions. Saved text is kept:
 * the English version, or the Khmer one when English is empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table): void {
            $table->string('subject')->nullable();
            $table->string('button')->nullable();
            $table->longText('body')->nullable();
        });

        foreach (DB::table('email_templates')->get() as $row) {
            DB::table('email_templates')->where('id', $row->id)->update([
                'subject' => $row->subject_en ?: $row->subject_km,
                'button' => $row->button_en ?: $row->button_km,
                'body' => $row->body_en ?: $row->body_km,
                'custom_variables' => $this->singleValueVariables($row->custom_variables),
            ]);
        }

        Schema::table('email_templates', function (Blueprint $table): void {
            $table->dropColumn(['subject_en', 'subject_km', 'button_en', 'button_km', 'body_en', 'body_km']);
        });

        Schema::table('sms_templates', function (Blueprint $table): void {
            $table->text('body')->nullable();
        });

        foreach (DB::table('sms_templates')->get() as $row) {
            DB::table('sms_templates')->where('id', $row->id)->update([
                'body' => $row->body_en ?: $row->body_km,
                'custom_variables' => $this->singleValueVariables($row->custom_variables),
            ]);
        }

        Schema::table('sms_templates', function (Blueprint $table): void {
            $table->dropColumn(['body_en', 'body_km']);
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table): void {
            foreach (['en', 'km'] as $locale) {
                $table->string("subject_{$locale}")->nullable();
                $table->string("button_{$locale}")->nullable();
                $table->longText("body_{$locale}")->nullable();
            }
        });

        DB::table('email_templates')->update([
            'subject_en' => DB::raw('subject'),
            'button_en' => DB::raw('button'),
            'body_en' => DB::raw('body'),
        ]);

        Schema::table('email_templates', function (Blueprint $table): void {
            $table->dropColumn(['subject', 'button', 'body']);
        });

        Schema::table('sms_templates', function (Blueprint $table): void {
            $table->text('body_en')->nullable();
            $table->text('body_km')->nullable();
        });

        DB::table('sms_templates')->update(['body_en' => DB::raw('body')]);

        Schema::table('sms_templates', function (Blueprint $table): void {
            $table->dropColumn('body');
        });
    }

    /**
     * Custom variables keep one value: the English one, or the Khmer one when English is empty.
     */
    private function singleValueVariables(?string $json): ?string
    {
        $items = json_decode((string) $json, true);

        if (! is_array($items)) {
            return $json;
        }

        return json_encode(array_map(fn ($item): mixed => is_array($item) ? [
            'name' => $item['name'] ?? null,
            'value' => $item['value'] ?? (filled($item['value_en'] ?? null) ? $item['value_en'] : ($item['value_km'] ?? null)),
        ] : $item, $items), JSON_UNESCAPED_UNICODE);
    }
};
