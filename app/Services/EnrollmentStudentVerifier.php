<?php

namespace App\Services;

use App\Models\UploadExcelCandidate;
use Illuminate\Support\Carbon;

class EnrollmentStudentVerifier
{
    public function verify(string $academicYear, string $seatNumber, string $dateOfBirth): ?UploadExcelCandidate
    {
        $academicYear = trim($academicYear);
        $seatNumber = trim($seatNumber);
        $dateOfBirth = Carbon::parse($dateOfBirth)->format('Y-m-d');

        return UploadExcelCandidate::query()
            ->where('academic_year', $academicYear)
            ->where('seat_number', $seatNumber)
            ->whereDate('date_of_birth', $dateOfBirth)
            ->first();
    }
}
