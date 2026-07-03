<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GeoLocationsSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = database_path('data/CambodiaGeographicalList2025.csv');

        if (! file_exists($csvPath)) {
            throw new \RuntimeException("CSV file not found at: {$csvPath}");
        }

        Schema::disableForeignKeyConstraints();
        DB::table('geo_locations')->truncate();
        Schema::enableForeignKeyConstraints();

        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open CSV file: {$csvPath}");
        }

        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = $this->detectDelimiter($firstLine ?: '');
        $header = fgetcsv($handle, 0, $delimiter);

        if ($header === false) {
            fclose($handle);
            throw new \RuntimeException("CSV file is empty: {$csvPath}");
        }

        $header = $this->normalizeHeader($header);

        $expectedHeaders = [
            'province_code',
            'province_kh',
            'province_en',
            'district_code',
            'district_kh',
            'district_en',
            'commune_code',
            'commune_kh',
            'commune_en',
            'village_code',
            'village_kh',
            'village_en',
        ];

        $missingHeaders = array_diff($expectedHeaders, $header);

        if (! empty($missingHeaders)) {
            fclose($handle);

            throw new \RuntimeException(
                'CSV headers do not match. Missing: ' . implode(', ', $missingHeaders) .
                ' | Found: ' . implode(', ', $header)
            );
        }

        $provinceMap = [];
        $districtMap = [];
        $communeMap = [];
        $villageMap = [];
        $now = now();

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                if (count($row) < count($header)) {
                    $row = array_pad($row, count($header), null);
                } elseif (count($row) > count($header)) {
                    $row = array_slice($row, 0, count($header));
                }

                $data = array_combine($header, $row);

                if ($data === false) {
                    continue;
                }

                $data = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $data);

                if (empty($data['province_code']) || empty($data['province_en'])) {
                    continue;
                }

                $provinceCode = $this->normalizeCode($data['province_code'], 2);
                $provinceKey = $provinceCode;

                if (! isset($provinceMap[$provinceKey])) {
                    $provinceMap[$provinceKey] = DB::table('geo_locations')->insertGetId([
                        'name_en' => $data['province_en'],
                        'name_kh' => $data['province_kh'] ?: null,
                        'code' => $provinceCode,
                        'type' => 'province',
                        'parent_id' => null,
                        'metadata' => null,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if (empty($data['district_code']) || empty($data['district_en'])) {
                    continue;
                }

                $districtCode = $this->normalizeChildCode($provinceCode, $data['district_code'], 4);
                $districtKey = $districtCode;

                if (! isset($districtMap[$districtKey])) {
                    $districtMap[$districtKey] = DB::table('geo_locations')->insertGetId([
                        'name_en' => $data['district_en'],
                        'name_kh' => $data['district_kh'] ?: null,
                        'code' => $districtCode,
                        'type' => 'district',
                        'parent_id' => $provinceMap[$provinceKey],
                        'metadata' => null,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if (empty($data['commune_code']) || empty($data['commune_en'])) {
                    continue;
                }

                $communeCode = $this->normalizeChildCode($districtCode, $data['commune_code'], 6);
                $communeKey = $communeCode;

                if (! isset($communeMap[$communeKey])) {
                    $communeMap[$communeKey] = DB::table('geo_locations')->insertGetId([
                        'name_en' => $data['commune_en'],
                        'name_kh' => $data['commune_kh'] ?: null,
                        'code' => $communeCode,
                        'type' => 'commune',
                        'parent_id' => $districtMap[$districtKey],
                        'metadata' => null,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if (empty($data['village_code']) || empty($data['village_en'])) {
                    continue;
                }

                $villageCode = $this->normalizeChildCode($communeCode, $data['village_code'], 8);
                $villageKey = $villageCode;

                if (! isset($villageMap[$villageKey])) {
                    $villageMap[$villageKey] = DB::table('geo_locations')->insertGetId([
                        'name_en' => $data['village_en'],
                        'name_kh' => $data['village_kh'] ?: null,
                        'code' => $villageCode,
                        'type' => 'village',
                        'parent_id' => $communeMap[$communeKey],
                        'metadata' => null,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            fclose($handle);
            DB::commit();
        } catch (\Throwable $e) {
            fclose($handle);
            DB::rollBack();

            throw $e;
        }
    }

    private function normalizeChildCode(string $parentCode, mixed $code, int $digits): string
    {
        $code = $this->onlyDigits($code);

        if ($code === '') {
            return '';
        }

        if (strlen($code) >= $digits) {
            return substr($code, 0, $digits);
        }

        $segmentLength = $digits - strlen($parentCode);
        $segment = str_pad($code, $segmentLength, '0', STR_PAD_LEFT);

        if (strlen($segment) > $segmentLength) {
            $segment = substr($segment, -$segmentLength);
        }

        return str_pad($parentCode . $segment, $digits, '0', STR_PAD_LEFT);
    }

    private function normalizeCode(mixed $code, int $digits): string
    {
        $code = $this->onlyDigits($code);

        if (strlen($code) >= $digits) {
            return substr($code, 0, $digits);
        }

        return str_pad($code, $digits, '0', STR_PAD_LEFT);
    }

    private function onlyDigits(mixed $value): string
    {
        return preg_replace('/[^0-9]/', '', trim((string) $value));
    }

    private function normalizeHeader(array $header): array
    {
        return array_map(function ($value) {
            $value = (string) $value;
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

            return strtolower(trim($value));
        }, $header);
    }

    private function detectDelimiter(string $line): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $bestDelimiter = ',';
        $maxCount = 0;

        foreach ($delimiters as $delimiter) {
            $count = substr_count($line, $delimiter);

            if ($count > $maxCount) {
                $maxCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
