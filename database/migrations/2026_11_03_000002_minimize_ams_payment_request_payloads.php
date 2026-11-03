<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Keep workflow references, not duplicate request/receipt details.
        DB::table('ams_payment_requests')->orderBy('id')->chunkById(200, function ($requests): void {
            foreach ($requests as $request) {
                DB::table('ams_payment_requests')->where('id', $request->id)->update([
                    'payload' => json_encode(['application_id' => (string) $request->custom_form_entry_id], JSON_THROW_ON_ERROR),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Removed copies must not be reconstructed; application data remains intact.
    }
};
