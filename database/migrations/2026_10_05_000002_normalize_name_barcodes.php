<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_templates') || ! Schema::hasColumn('document_templates', 'content')) {
            return;
        }

        DB::table('document_templates')
            ->where('content', 'like', '%#barcode%')
            ->get(['id', 'content'])
            ->each(function (object $template): void {
                $content = preg_replace_callback(
                    '/{{\s*#barcode\s+data\.first_name_(kh|en)\s+type=([a-zA-Z0-9_]+)\s+width=(\d+)\s+height=(\d+)\s*}}\s*{{\s*#barcode\s+data\.last_name_\1\s+type=\2\s+width=\3\s+height=\4\s*}}/i',
                    static fn (array $matches): string => '{{#barcode data.first_name_'.$matches[1].'|data.last_name_'.$matches[1].' type='.$matches[2].' width='.$matches[3].' height='.$matches[4].'}}',
                    (string) $template->content
                );

                if ($content !== null && $content !== $template->content) {
                    DB::table('document_templates')
                        ->where('id', $template->id)
                        ->update([
                            'content' => $content,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('document_templates') || ! Schema::hasColumn('document_templates', 'content')) {
            return;
        }

        DB::table('document_templates')
            ->where('content', 'like', '%#barcode%')
            ->get(['id', 'content'])
            ->each(function (object $template): void {
                $content = preg_replace_callback(
                    '/{{\s*#barcode\s+data\.first_name_(kh|en)\|data\.last_name_\1\s+type=([a-zA-Z0-9_]+)\s+width=(\d+)\s+height=(\d+)\s*}}/i',
                    static fn (array $matches): string => '{{#barcode data.first_name_'.$matches[1].' type='.$matches[2].' width='.$matches[3].' height='.$matches[4].'}}{{#barcode data.last_name_'.$matches[1].' type='.$matches[2].' width='.$matches[3].' height='.$matches[4].'}}',
                    (string) $template->content
                );

                if ($content !== null && $content !== $template->content) {
                    DB::table('document_templates')
                        ->where('id', $template->id)
                        ->update([
                            'content' => $content,
                            'updated_at' => now(),
                        ]);
                }
            });
    }
};
