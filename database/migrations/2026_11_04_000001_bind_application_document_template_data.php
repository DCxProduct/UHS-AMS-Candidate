<?php

use App\Support\DocumentTemplateDataBindings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BACKUPS = 'document_template_data_binding_backups';

    public function up(): void
    {
        foreach (['document_templates', 'custom_forms', 'custom_form_fields'] as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        if (! Schema::hasTable(self::BACKUPS)) {
            Schema::create(self::BACKUPS, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('document_template_id')->unique();
                $table->longText('original_content')->nullable();
                $table->string('patched_content_hash', 64);
                $table->timestamp('original_updated_at')->nullable();
                $table->timestamp('created_at');
            });
        }

        DB::transaction(function (): void {
            DB::table('document_templates')
                ->where('type', 'like', 'custom_form_%')
                ->whereNotNull('content')
                ->orderBy('id')
                ->lockForUpdate()
                ->chunkById(100, function ($templates): void {
                    foreach ($templates as $template) {
                        if (
                            ! preg_match('/^custom_form_(\d+)$/', (string) $template->type, $match)
                            || ! DB::table('custom_forms')->where('id', $match[1])->exists()
                            || DB::table(self::BACKUPS)->where('document_template_id', $template->id)->exists()
                        ) {
                            continue;
                        }

                        $formIds = DB::table('custom_forms')
                            ->where('custom_form_id', $match[1])
                            ->where('menu_placement', 'sub_item')
                            ->where('is_active', true)
                            ->pluck('id')
                            ->prepend((int) $match[1]);
                        $fieldNames = DB::table('custom_form_fields')
                            ->whereIn('custom_form_id', $formIds)
                            ->whereNotNull('name')
                            ->pluck('name')->all();
                        $content = DocumentTemplateDataBindings::upgrade((string) $template->content, $fieldNames);

                        if ($content === (string) $template->content) {
                            continue;
                        }

                        DB::table(self::BACKUPS)->insert([
                            'document_template_id' => $template->id,
                            'original_content' => $template->content,
                            'patched_content_hash' => hash('sha256', $content),
                            'original_updated_at' => $template->updated_at,
                            'created_at' => now(),
                        ]);
                        DB::table('document_templates')->where('id', $template->id)->update([
                            'content' => $content,
                            'updated_at' => now(),
                        ]);
                    }
                });
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::BACKUPS)) {
            return;
        }

        if (Schema::hasTable('document_templates')) {
            DB::transaction(function (): void {
                foreach (DB::table(self::BACKUPS)->orderBy('id')->get() as $backup) {
                    $template = DB::table('document_templates')
                        ->where('id', $backup->document_template_id)->lockForUpdate()->first(['id', 'content']);

                    // Keep any template edits made after this migration.
                    if (! $template || ! hash_equals($backup->patched_content_hash, hash('sha256', (string) $template->content))) {
                        continue;
                    }

                    DB::table('document_templates')->where('id', $template->id)->update([
                        'content' => $backup->original_content,
                        'updated_at' => $backup->original_updated_at,
                    ]);
                }
            });
        }

        Schema::dropIfExists(self::BACKUPS);
    }
};
