<?php

namespace Tests\Feature;

use App\Support\CaseInsensitiveSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CaseInsensitiveTableSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('table_search_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('owner_id')->default(1);
            $table->string('menu')->default('included');
            $table->decimal('amount', 12, 2)->nullable();
            $table->boolean('is_active')->nullable();
            $table->string('status_code')->nullable();
            $table->dateTime('event_at')->nullable();
            $table->json('data')->nullable();
        });
    }

    public function test_normalization_keeps_text_and_translates_all_khmer_digits_before_case_matching(): void
    {
        $this->assertSame('méd-0123456789', CaseInsensitiveSearch::normalize('  MÉD-០១២៣៤៥៦៧៨៩  '));
        $this->assertSame('18-វិច្ឆិកា-2026 16:07', CaseInsensitiveSearch::normalize('  ១៨-វិច្ឆិកា-២០២៦ ១៦:០៧ '));
        $this->assertSame('', CaseInsensitiveSearch::normalize('  '));
    }

    public function test_cast_search_matches_numeric_storage_formatted_currency_and_khmer_digits_in_columns_and_json(): void
    {
        $match = $this->record(['amount' => 1250.50, 'is_active' => true], ['amount' => 1250.50]);
        $other = $this->record(['amount' => 985.75, 'is_active' => false], ['amount' => 985.75]);
        $this->record();

        foreach (['amount', 'data->amount'] as $column) {
            foreach (['1250.50', '$1,250.50', '១,២៥០.៥០', '250.5'] as $search) {
                $this->assertSame([$match], CaseInsensitiveSearch::applyCast($this->searchQuery(), $column, $search)->pluck('id')->all(), $column.' '.$search);
            }
        }
        $this->assertSame([$match], CaseInsensitiveSearch::applyCast($this->searchQuery(), 'is_active', '១')->pluck('id')->all());
        $this->assertSame([$other], CaseInsensitiveSearch::applyCast($this->searchQuery(), 'is_active', '០')->pluck('id')->all());
    }

    public function test_mapped_search_matches_stored_values_and_localized_labels_without_matching_unrelated_rows(): void
    {
        $transfer = $this->record(['status_code' => 'WIRE'], ['method' => 'WIRE']);
        $this->record(['status_code' => 'cash'], ['method' => 'cash']);
        $this->record();
        $labels = ['wire' => ['en' => 'Bank Transfer', 'km' => 'ផ្ទេរប្រាក់'], 'cash' => 'Cash Payment'];

        foreach (['status_code', 'data->method'] as $column) {
            foreach (['WIRE', 'wire', 'Bank Transfer', 'bAnK tRaNsFeR', 'transfer', 'ផ្ទេរប្រាក់'] as $search) {
                $this->assertSame([$transfer], CaseInsensitiveSearch::applyMapped($this->searchQuery(), $column, $search, $labels)->pluck('id')->all(), $column.' '.$search);
            }
            $this->assertSame([], CaseInsensitiveSearch::applyMapped($this->searchQuery(), $column, 'unknown-method', $labels)->pluck('id')->all());
        }
    }

    public static function dateColumns(): array
    {
        return ['table datetime' => ['event_at'], 'JSON datetime' => ['data->event_at']];
    }

    #[DataProvider('dateColumns')]
    public function test_date_search_accepts_iso_localized_date_and_twenty_four_hour_time_formats(string $column): void
    {
        $match = $this->record(['event_at' => '2026-11-18 16:07:09'], ['event_at' => '2026-11-18T16:07:09Z']);
        $this->record(['event_at' => '2025-03-21 08:30:00'], ['event_at' => '2025-03-21T08:30:00Z']);
        $this->record();

        foreach ([
            '2026-11-18', '18-11-2026', '18-Nov-2026', '18-NOV-2026', '18-nOvEmBeR-2026',
            '18-វិច្ឆិកា-2026', '១៨-វិច្ឆិកា-២០២៦', '១៨-១១-២០២៦',
            '18-Nov-2026 16:07', '១៨-វិច្ឆិកា-២០២៦ ១៦:០៧', '16:07', '16:07:09',
        ] as $search) {
            $this->assertSame([$match], CaseInsensitiveSearch::applyDate($this->searchQuery(), $column, $search)->pluck('id')->all(), $column.' '.$search);
        }
    }

    public function test_date_search_tolerates_null_missing_and_invalid_json_date_values_and_still_matches_raw_text(): void
    {
        $match = $this->record(['event_at' => '2026-11-18 16:07:09'], ['event_at' => '2026-11-18 16:07:09']);
        $invalid = $this->record([], ['event_at' => 'NOT-A-DATE']);
        $this->record([], ['event_at' => null]);
        $this->record([], ['event_at' => ['unexpected' => true]]);
        $this->record([], ['event_at' => 42]);
        $this->record([], []);

        $this->assertSame([$match], CaseInsensitiveSearch::applyDate($this->searchQuery(), 'data->event_at', '18-Nov-2026')->pluck('id')->all());
        $this->assertSame([$match], CaseInsensitiveSearch::applyDate($this->searchQuery(), 'event_at', '18-Nov-2026')->pluck('id')->all());
        $this->assertSame([$invalid], CaseInsensitiveSearch::applyDate($this->searchQuery(), 'data->event_at', 'not-a-date')->pluck('id')->all());
    }

    public function test_numeric_date_and_label_alternatives_remain_grouped_with_outer_owner_and_menu_constraints(): void
    {
        $numeric = $this->record(['amount' => 1250.50]);
        $date = $this->record(['event_at' => '2026-11-18 16:07:09']);
        $mapped = $this->record(['status_code' => 'wire']);
        $this->record();
        foreach ([['owner_id' => 2], ['menu' => 'other-menu']] as $outside) {
            $this->record([
                ...$outside, 'amount' => 1250.50, 'event_at' => '2026-11-18 16:07:09', 'status_code' => 'wire',
            ]);
        }
        $scoped = fn (): Builder => $this->searchQuery()->where('owner_id', 1)->where('menu', 'included');
        $labels = ['wire' => 'Bank Transfer'];
        $this->assertSame([$numeric], CaseInsensitiveSearch::applyCast($scoped(), 'amount', '$1,250.50')->pluck('id')->all());
        $this->assertSame([$date], CaseInsensitiveSearch::applyDate($scoped(), 'event_at', '18-Nov-2026')->pluck('id')->all());
        $this->assertSame([$mapped], CaseInsensitiveSearch::applyMapped($scoped(), 'status_code', 'bank transfer', $labels)->pluck('id')->all());
        $query = $scoped()->where(function (Builder $search) use ($labels): void {
            CaseInsensitiveSearch::applyCast($search, 'amount', '$1,250.50');
            CaseInsensitiveSearch::applyDate($search, 'event_at', '18-Nov-2026', or: true);
            CaseInsensitiveSearch::applyMapped($search, 'status_code', 'bank transfer', $labels, or: true);
        });
        $this->assertSame([$numeric, $date, $mapped], $query->pluck('id')->all());
    }

    public function test_postgres_compilation_casts_search_values_to_text_and_never_parses_untrusted_json_as_a_date(): void
    {
        $connection = new PostgresConnection(static fn () => throw new LogicException('This grammar test must not open a connection.'), 'search-grammar', '', ['driver' => 'pgsql']);
        $query = (new Builder($connection->query()))->setModel(new TableSearchFixtureRecord);
        CaseInsensitiveSearch::applyCast($query, 'table_search_records.amount', '$1,250.50');
        CaseInsensitiveSearch::applyDate($query, 'table_search_records.event_at', '18-Nov-2026');
        CaseInsensitiveSearch::applyDate($query, 'data->event_at', 'not-a-date');
        $sql = $query->toSql();

        $this->assertStringContainsString('CAST("table_search_records"."amount" AS TEXT)', $sql);
        $this->assertStringContainsString('CAST("table_search_records"."event_at" AS TEXT)', $sql);
        $this->assertStringContainsString('CAST("data"->>\'event_at\' AS TEXT)', $sql);
        $this->assertStringNotContainsString('LOWER("table_search_records"."amount")', $sql);
        $this->assertStringNotContainsString('LOWER("table_search_records"."event_at")', $sql);
        $this->assertDoesNotMatchRegularExpression('/::(?:date|timestamp)|AS\s+(?:date|timestamp)|\bTO_DATE\s*\(/i', $sql);
        $this->assertContains('%not-a-date%', $query->getBindings());
    }

    private function searchQuery(): Builder
    {
        return TableSearchFixtureRecord::query()->orderBy('id');
    }

    private function record(array $attributes = [], ?array $data = null): int
    {
        return DB::table('table_search_records')->insertGetId([
            'owner_id' => 1, 'menu' => 'included',
            'data' => $data === null ? null : json_encode($data, JSON_THROW_ON_ERROR),
            ...$attributes,
        ]);
    }
}

class TableSearchFixtureRecord extends Model
{
    protected $table = 'table_search_records';

    public $timestamps = false;
}
