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

                $data = array_map(function ($value) {
                    return is_string($value) ? trim($value) : $value;
                }, $data);

                if (empty($data['province_code']) || empty($data['province_en'])) {
                    continue;
                }

                $provinceCode = $this->provinceCode($data['province_code']);
                $provinceKey = $provinceCode;

                if (! isset($provinceMap[$provinceKey])) {
                    $provinceMap[$provinceKey] = DB::table('geo_locations')->insertGetId([
                        'name_en'    => $data['province_en'],
                        'code'       => $provinceCode,
                        'name_kh'    => $data['province_kh'] ?: null,
                        'type'       => 'province',
                        'parent_id'  => null,
                        'metadata'   => null,
                        'is_active'  => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if (empty($data['district_code']) || empty($data['district_en'])) {
                    continue;
                }

                $districtCode = $this->childCode($provinceCode, $data['district_code'], 'district');
                $districtKey = $districtCode;

                if (! isset($districtMap[$districtKey])) {
                    $districtMap[$districtKey] = DB::table('geo_locations')->insertGetId([
                        'name_en'    => $data['district_en'],
                        'code'       => $districtCode,
                        'name_kh'    => $data['district_kh'] ?: null,
                        'type'       => 'district',
                        'parent_id'  => $provinceMap[$provinceKey],
                        'metadata'   => null,
                        'is_active'  => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if (empty($data['commune_code']) || empty($data['commune_en'])) {
                    continue;
                }

                $communeCode = $this->childCode($districtCode, $data['commune_code'], 'commune');
                $communeKey = $communeCode;

                if (! isset($communeMap[$communeKey])) {
                    $communeMap[$communeKey] = DB::table('geo_locations')->insertGetId([
                        'name_en'    => $data['commune_en'],
                        'code'       => $communeCode,
                        'name_kh'    => $data['commune_kh'] ?: null,
                        'type'       => 'commune',
                        'parent_id'  => $districtMap[$districtKey],
                        'metadata'   => null,
                        'is_active'  => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if (empty($data['village_code']) || empty($data['village_en'])) {
                    continue;
                }

                $villageCode = $this->childCode($communeCode, $data['village_code'], 'village');
                $villageKey = $villageCode;

                if (! isset($villageMap[$villageKey])) {
                    $villageMap[$villageKey] = DB::table('geo_locations')->insertGetId([
                        'name_en'    => $data['village_en'],
                        'code'       => $villageCode,
                        'name_kh'    => $data['village_kh'] ?: null,
                        'type'       => 'village',
                        'parent_id'  => $communeMap[$communeKey],
                        'metadata'   => null,
                        'is_active'  => true,
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

    private function provinceCode(mixed $code): string
    {
        $code = $this->onlyDigits($code);

        if (strlen($code) > 2) {
            $code = substr($code, -2);
        }

        return str_pad($code, 2, '0', STR_PAD_LEFT);
    }

    private function childCode(string $parentCode, mixed $code, string $type): string
    {
        $segment = $this->lastTwoDigits($code);

        $fullCode = $parentCode . $segment;

        $digits = match ($type) {
            'district' => 4,
            'commune' => 6,
            'village' => 8,
            default => strlen($fullCode),
        };

        if (strlen($fullCode) > $digits) {
            $fullCode = substr($fullCode, -$digits);
        }

        return str_pad($fullCode, $digits, '0', STR_PAD_LEFT);
    }

    private function lastTwoDigits(mixed $code): string
    {
        $code = $this->onlyDigits($code);

        if (strlen($code) > 2) {
            $code = substr($code, -2);
        }

        return str_pad($code, 2, '0', STR_PAD_LEFT);
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