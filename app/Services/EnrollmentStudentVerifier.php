<?php

namespace App\Services;

use App\Models\UploadExcelStudent;
use Carbon\Carbon;

class EnrollmentStudentVerifier
{
    public function verify(string $academicYear, string $seatNumber, string $dateOfBirth): ?UploadExcelStudent
    {
        try {
            $dateOfBirth = Carbon::parse($dateOfBirth)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }

        return UploadExcelStudent::query()
            ->where('academic_year', trim($academicYear))
            ->where('seat_number', trim($seatNumber))
            ->whereDate('date_of_birth', $dateOfBirth)
            ->first();
    }
}