<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AmsExcelStudentAccountImport;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class AmsExcelStudentAccountController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = config('ams.student_accounts_token');
        abort_unless(is_string($token) && $token !== '', 503, 'AMS integration is not configured.');
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401, 'Invalid AMS token.');

        $data = $request->validate([
            'version' => ['required', 'in:1'],
            'source_system' => ['required', 'string', 'max:100'],
            'source_user_id' => ['required', 'string', 'max:100'],
            'student_id' => ['required', 'string', 'max:100'],
            'first_name_latin' => ['required', 'string', 'max:100'],
            'last_name_latin' => ['required', 'string', 'max:100'],
            'email' => ['present', 'nullable', 'email', 'max:150', 'required_without:phone'],
            'phone' => ['present', 'nullable', 'string', 'max:30', 'required_without:email'],
            'password_hash' => ['required', 'string', 'max:255', static function ($attribute, $value, $fail): void {
                if (! Hash::isHashed($value) || ! Hash::verifyConfiguration($value)) {
                    $fail('A password hash compatible with Candidate is required.');
                }
            }],
            'name_khmer' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d'],
            'profile' => ['required', 'array:version,data,locations'],
            'profile.version' => ['required', 'in:1'],
            'profile.data' => ['present', 'array', 'max:200'],
            'profile.locations' => ['present', 'array', 'max:100'],
            'profile.locations.*' => ['string', 'max:100'],
        ]);

        try {
            $receipt = app(AmsExcelStudentAccountImport::class)->receive($data);
        } catch (UniqueConstraintViolationException|DomainException) {
            return response()->json(['message' => 'Account identity conflicts with an existing account.'], 409);
        }

        return response()->json(['data' => [
            'student_id' => $receipt->student_id,
            'id' => $receipt->new_student_account_id,
            'profile_entry_id' => $receipt->profile_entry_id,
            'profile_synced' => true,
        ]], $receipt->wasRecentlyCreated ? 201 : 200);
    }
}
