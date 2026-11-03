<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ams_payment_requests', 'payment_status')) {
            Schema::table('ams_payment_requests', function (Blueprint $table): void {
                $table->string('payment_status')->default('unpaid')->index();
            });
        }

        // Retain the workflow gate before removing old Candidate copies of AMS receipts.
        // Query-builder writes intentionally avoid payment events and workflow rollback.
        DB::transaction(function (): void {
            DB::table('payments')->where('integration_source', 'ams')
                ->orderBy('id')->chunkById(200, function ($payments): void {
                    foreach ($payments as $payment) {
                        if (! $payment->custom_form_entry_id) {
                            continue;
                        }

                        $link = DB::table('ams_payment_requests')
                            ->where('custom_form_entry_id', $payment->custom_form_entry_id)->first();

                        $state = [
                            'payment_status' => strtolower((string) $payment->status_payt) === 'paid' ? 'paid' : 'unpaid',
                            'external_payment_id' => $payment->integration_payment_id ?: $link?->external_payment_id,
                            'local_payment_id' => null,
                            'updated_at' => now(),
                        ];

                        // A newer authenticated callback is authoritative over
                        // a historic receipt copy awaiting cleanup.
                        if ($link && (int) $link->last_event_version > 0) {
                            unset($state['payment_status'], $state['external_payment_id']);
                        }

                        if ($link) {
                            DB::table('ams_payment_requests')->where('id', $link->id)->update($state);
                        } else {
                            DB::table('ams_payment_requests')->insert([
                                'custom_form_entry_id' => $payment->custom_form_entry_id,
                                'created_at' => now(),
                                ...$state,
                            ]);
                        }
                    }

                    DB::table('payments')->whereIn('id', $payments->pluck('id'))->delete();
                });
        });

        if (! Schema::hasIndex('ams_payment_requests', 'ams_payment_requests_external_payment_unique')) {
            Schema::table('ams_payment_requests', function (Blueprint $table): void {
                $table->unique('external_payment_id', 'ams_payment_requests_external_payment_unique');
            });
        }
    }

    public function down(): void
    {
        // Financial records remain in AMS; rollback must not recreate local receipts.
        Schema::table('ams_payment_requests', function (Blueprint $table): void {
            $table->dropUnique('ams_payment_requests_external_payment_unique');
            $table->dropColumn('payment_status');
        });
    }
};
