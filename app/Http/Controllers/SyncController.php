<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    public function __invoke(Request $request)
    {
        $url = rtrim((string) env('DB_SYNC_API_URL', 'https://db-sync-api.vanny.monster/api/sync'), '/');
        $token = (string) env('DB_SYNC_API_TOKEN', '');

        $payload = $request->input('payload', env('DB_SYNC_API_PAYLOAD', ''));

        if (is_string($payload) && $payload !== '') {
            $decodedPayload = json_decode($payload, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedPayload)) {
                $payload = $decodedPayload;
            } else {
                $payload = ['payload' => $payload];
            }
        }

        if (! is_array($payload)) {
            $payload = [];
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => $token !== '' ? 'Bearer ' . $token : null,
        ])->post($url, $payload);

        if ($response->failed()) {
            Log::error('Sync API failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return back()->with('danger', 'Sync failed. Please try again.');
        }

        return back()->with('success', 'Sync completed successfully.');
    }
}
