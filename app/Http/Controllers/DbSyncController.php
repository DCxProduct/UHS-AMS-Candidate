<?php

namespace App\Http\Controllers;

use App\Services\DbSyncApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class DbSyncController extends Controller
{
    public function sync(Request $request, DbSyncApiService $syncApi): JsonResponse
    {
        abort_unless($request->user()?->registration_type === 'admin', 403);

        $validated = $request->validate([
            'dry_run' => ['sometimes', 'boolean'],
            'full_resync' => ['sometimes', 'boolean'],
        ]);

        try {
            $result = $syncApi->sync(
                dryRun: $validated['dry_run'] ?? false,
                fullResync: $validated['full_resync'] ?? false,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null,
                'errors' => [],
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Sync could not be completed right now. Please try again.',
                'data' => null,
                'errors' => [],
            ], 500);
        }

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'],
            'errors' => $result['errors'],
        ], $result['success'] ? 200 : 422);
    }
}
