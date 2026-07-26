<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AdminOnly;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

class DatabaseBackup extends Page implements HasForms
{
    use AdminOnly;
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'database-backup';

    protected string $view = 'filament.pages.database-backup';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'tables' => [],
        ]);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('backup.title');
    }

    public function getTitle(): string
    {
        return __('backup.title');
    }

    public function getHeading(): string
    {
        return __('backup.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('backup.sections.export'))
                    ->description(__('backup.sections.export_description'))
                    ->schema([
                        Select::make('tables')
                            ->label(__('backup.fields.tables'))
                            ->placeholder(__('backup.placeholders.tables'))
                            ->options($this->getTableOptions())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->validationMessages([
                                'required' => __('backup.validation.tables_required'),
                            ]),
                    ]),
            ]);
    }

    public function export(): BinaryFileResponse | StreamedResponse | null
    {
        $tables = $this->selectedTables();

        if ($tables === []) {
            Notification::make()
                ->title(__('backup.notifications.no_tables'))
                ->warning()
                ->send();

            return null;
        }

        if (! class_exists(ZipArchive::class)) {
            Notification::make()
                ->title(__('backup.notifications.zip_extension_missing'))
                ->danger()
                ->send();

            return null;
        }

        $filename = 'database-backup-' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        $tempPath = $this->createXlsxFile($tables);

        return response()->download(
            $tempPath,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function erase(): void
    {
        $tables = $this->selectedTables();

        if ($tables === []) {
            Notification::make()
                ->title(__('backup.notifications.no_tables'))
                ->warning()
                ->send();

            return;
        }

        try {
            $this->eraseTables($tables);

            $this->data = [
                'tables' => [],
            ];

            $this->form->fill($this->data);

            Notification::make()
                ->title(__('backup.notifications.erased'))
                ->success()
                ->send();

            $this->redirect(static::getUrl(), navigate: false);
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title(__('backup.notifications.erase_failed'))
                ->danger()
                ->send();
        }
    }

    protected function getTableOptions(): array
    {
        return $this->tableCollection()
            ->mapWithKeys(fn (string $table): array => [$table => $table])
            ->all();
    }

    protected function selectedTables(): array
    {
        $data = $this->form->getState();
        $tableOptions = $this->getTableOptions();

        return collect($data['tables'] ?? [])
            ->filter(fn ($table): bool => is_string($table) && array_key_exists($table, $tableOptions))
            ->values()
            ->all();
    }

    protected function listDatabaseTables(): array
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => collect(DB::select("
                select tablename as name
                from pg_tables
                where schemaname = 'public'
                order by tablename
            "))->pluck('name')->all(),
            'mysql', 'mariadb' => collect(DB::select('SHOW TABLES'))
                ->map(fn (object $row): string => (string) collect((array) $row)->first())
                ->values()
                ->all(),
            'sqlite' => collect(DB::select("
                select name
                from sqlite_master
                where type = 'table'
                  and name not like 'sqlite_%'
                order by name
            "))->pluck('name')->all(),
            default => [],
        };
    }

    protected function tableCollection(): \Illuminate\Support\Collection
    {
        return collect($this->listDatabaseTables())
            ->reject(fn (string $table): bool => Str::startsWith($table, ['migrations', 'password_reset_tokens', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks', 'sessions']))
            ->values();
    }

    protected function eraseTables(array $tables): void
    {
        $driver = DB::connection()->getDriverName();

        match ($driver) {
            'pgsql' => $this->erasePostgresTables($tables),
            'mysql', 'mariadb' => $this->eraseMySqlTables($tables),
            'sqlite' => $this->eraseSqliteTables($tables),
            default => $this->eraseGenericTables($tables),
        };
    }

    protected function erasePostgresTables(array $tables): void
    {
        foreach ($tables as $table) {
            $qualifiedTable = $this->postgresQualifiedTable($table);

            try {
                DB::statement('TRUNCATE TABLE ' . $qualifiedTable . ' RESTART IDENTITY CASCADE');
            } catch (Throwable) {
                DB::statement('DELETE FROM ' . $qualifiedTable);
                $this->resetPostgresIdentity($table);
            }
        }
    }

    protected function eraseMySqlTables(array $tables): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                DB::statement('TRUNCATE TABLE ' . $this->quoteIdentifier($table));
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    protected function eraseSqliteTables(array $tables): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            foreach ($tables as $table) {
                DB::table($table)->delete();
            }

            $placeholders = implode(', ', array_fill(0, count($tables), '?'));
            DB::delete('DELETE FROM sqlite_sequence WHERE name IN (' . $placeholders . ')', $tables);
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    protected function eraseGenericTables(array $tables): void
    {
        foreach ($tables as $table) {
            DB::table($table)->delete();
        }
    }

    protected function createXlsxFile(array $tables): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'db-backup-');

        if (! $tempPath) {
            abort(500, 'Unable to create temporary backup file.');
        }

        $zip = new ZipArchive();

        if ($zip->open($tempPath, ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Unable to create backup archive.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml(count($tables)));
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($tables));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml(count($tables)));
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        foreach (array_values($tables) as $index => $table) {
            $zip->addFromString(
                'xl/worksheets/sheet' . ($index + 1) . '.xml',
                $this->worksheetXml($table)
            );
        }

        $zip->close();

        return $tempPath;
    }

    protected function contentTypesXml(int $worksheetCount): string
    {
        $worksheetOverrides = '';

        for ($i = 1; $i <= $worksheetCount; $i++) {
            $worksheetOverrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $worksheetOverrides
            . '</Types>';
    }

    protected function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    protected function workbookXml(array $tables): string
    {
        $sheets = '';

        foreach (array_values($tables) as $index => $table) {
            $sheets .= '<sheet name="' . $this->xml($this->worksheetName($table)) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheets . '</sheets>'
            . '</workbook>';
    }

    protected function workbookRelationshipsXml(int $worksheetCount): string
    {
        $relationships = '';

        for ($i = 1; $i <= $worksheetCount; $i++) {
            $relationships .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }

        $relationships .= '<Relationship Id="rId' . ($worksheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $relationships
            . '</Relationships>';
    }

    protected function stylesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="2">
        <font>
            <sz val="11"/>
            <name val="Calibri"/>
        </font>
        <font>
            <b/>
            <sz val="11"/>
            <name val="Calibri"/>
        </font>
    </fonts>
    <fills count="3">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/><bgColor indexed="64"/></patternFill></fill>
    </fills>
    <borders count="1">
        <border><left/><right/><top/><bottom/><diagonal/></border>
    </borders>
    <cellStyleXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    </cellStyleXfs>
    <cellXfs count="2">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
    </cellXfs>
</styleSheet>
XML;
    }

    protected function worksheetXml(string $table): string
    {
        $columns = DB::getSchemaBuilder()->getColumnListing($table);
        $rows = [];

        if ($columns !== []) {
            $headerCells = [];

            foreach (array_values($columns) as $columnIndex => $column) {
                $headerCells[] = $this->inlineStringCell($columnIndex, 1, (string) $column, 1);
            }

            $rows[] = '<row r="1">' . implode('', $headerCells) . '</row>';
        }

        $rowNumber = 2;

        foreach (DB::table($table)->cursor() as $record) {
            $cells = [];

            foreach (array_values($columns) as $columnIndex => $column) {
                $cells[] = $this->worksheetCell(
                    $columnIndex,
                    $rowNumber,
                    data_get((array) $record, $column),
                );
            }

            $rows[] = '<row r="' . $rowNumber . '">' . implode('', $cells) . '</row>';
            $rowNumber++;
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . '</worksheet>';
    }

    protected function worksheetCell(int $columnIndex, int $rowNumber, mixed $value): string
    {
        [$type, $formatted] = $this->spreadsheetCell($value);

        if ($type === 'Number') {
            return '<c r="' . $this->cellReference($columnIndex, $rowNumber) . '"><v>' . $this->xml($formatted) . '</v></c>';
        }

        return $this->inlineStringCell($columnIndex, $rowNumber, $formatted);
    }

    protected function inlineStringCell(int $columnIndex, int $rowNumber, string $value, int $style = 0): string
    {
        $styleAttribute = $style > 0 ? ' s="' . $style . '"' : '';

        return '<c r="' . $this->cellReference($columnIndex, $rowNumber) . '" t="inlineStr"' . $styleAttribute . '><is><t xml:space="preserve">' . $this->xml($value) . '</t></is></c>';
    }

    protected function spreadsheetCell(mixed $value): array
    {
        if ($value === null) {
            return ['String', ''];
        }

        if (is_bool($value)) {
            return ['String', $value ? 'true' : 'false'];
        }

        if (is_int($value) || is_float($value)) {
            return ['Number', (string) $value];
        }

        if (is_array($value) || is_object($value)) {
            return ['String', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''];
        }

        $string = (string) $value;

        if (is_numeric($string) && ! Str::startsWith($string, ['0']) && strlen($string) < 15) {
            return ['Number', $string];
        }

        return ['String', $string];
    }

    protected function worksheetName(string $table): string
    {
        return Str::of($table)
            ->replace(['\\', '/', '?', '*', '[', ']'], '-')
            ->limit(31, '')
            ->toString();
    }

    protected function quoteIdentifier(string $identifier): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql', 'sqlite' => '"' . str_replace('"', '""', $identifier) . '"',
            'mysql', 'mariadb' => '`' . str_replace('`', '``', $identifier) . '`',
            default => $identifier,
        };
    }

    protected function postgresQualifiedTable(string $table): string
    {
        return '"public".' . $this->quoteIdentifier($table);
    }

    protected function resetPostgresIdentity(string $table): void
    {
        $sequence = DB::selectOne(
            "select pg_get_serial_sequence(?, 'id') as sequence_name",
            ['public.' . $table],
        );

        $sequenceName = data_get($sequence, 'sequence_name');

        if (! is_string($sequenceName) || trim($sequenceName) === '') {
            return;
        }

        DB::statement('ALTER SEQUENCE ' . $sequenceName . ' RESTART WITH 1');
    }

    protected function cellReference(int $columnIndex, int $rowNumber): string
    {
        return $this->columnLetters($columnIndex) . $rowNumber;
    }

    protected function columnLetters(int $columnIndex): string
    {
        $letters = '';
        $index = $columnIndex + 1;

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letters = chr(65 + $remainder) . $letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    protected function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
