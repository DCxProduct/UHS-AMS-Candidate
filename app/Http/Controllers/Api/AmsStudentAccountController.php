<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AmsStudentAccountTransfer;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AmsStudentAccountController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = config('ams.student_accounts_token');
        abort_unless(is_string($token) && $token !== '', 503, 'AMS integration is not configured.');
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401, 'Invalid AMS token.');

        $data = $request->validate([
            'student_id' => ['required', 'string', 'max:100'],
            'email' => ['present', 'nullable', 'email', 'max:150'],
            'phone' => ['present', 'nullable', 'string', 'max:30'],
            'first_name_latin' => ['required', 'string', 'max:100'],
            'last_name_latin' => ['required', 'string', 'max:100'],
        ]);

        try {
            $account = app(AmsStudentAccountTransfer::class)->receive($data);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Student ID or email conflicts with an existing account.'], 409);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'data' => ['id' => $account->id, 'student_id' => $account->student_id],
        ], $account->wasRecentlyCreated ? 201 : 200);
    }
}
