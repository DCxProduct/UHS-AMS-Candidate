@php
    use Illuminate\Support\Str;
    use Carbon\Carbon;

    $status = $record->review_status ?: 'pending';

    $statusText = __('review_applications.statuses.' . $status);

    $statusClass = match ($status) {
        'accepted' => 'is-accepted',
        'rejected' => 'is-rejected',
        default => 'is-pending',
    };

    $statusIcon = match ($status) {
        'accepted' => '✓',
        'rejected' => '×',
        default => '!',
    };

    $fieldLabel = function (string $key): string {
        $translationKey = 'review_applications.fields.' . $key;
        $translated = __($translationKey);

        return $translated !== $translationKey
            ? $translated
            : Str::headline($key);
    };

    $formatValue = function (mixed $value): string {
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return filled($value) ? (string) $value : '-';
    };

    $primaryKeys = [
        'student_id',
        'student_status',
        'first_name_kh',
        'last_name_kh',
        'first_name_en',
        'last_name_en',
        'gender',
        'date_of_birth',
        'phone_number',
        'email',
        'academic_year',
        'department',
        'type',
    ];

    $primaryData = collect($primaryKeys)
        ->filter(fn ($key) => array_key_exists($key, $data))
        ->mapWithKeys(fn ($key) => [$key => $data[$key]]);

    $otherData = collect($data)
        ->reject(fn ($value, $key) => in_array($key, $primaryKeys, true));

    $submittedAt = $record->created_at
        ? Carbon::parse($record->created_at)->format('d M Y H:i')
        : '-';

    $reviewedAt = $record->reviewed_at
        ? Carbon::parse($record->reviewed_at)->format('d M Y H:i')
        : '-';
@endphp

<div class="review-application-modal">
    <style>
        .review-application-modal {
            --card-bg: #ffffff;
            --soft-bg: #f8fafc;
            --border: #e5e7eb;
            --text: #111827;
            --muted: #6b7280;
            --blue: #2563eb;
            --blue-soft: #eff6ff;
            --green: #16a34a;
            --green-soft: #ecfdf5;
            --red: #dc2626;
            --red-soft: #fef2f2;
            --amber: #f59e0b;
            --amber-soft: #fffbeb;
            --shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
            color: var(--text);
        }

        .dark .review-application-modal {
            --card-bg: #18181b;
            --soft-bg: #111113;
            --border: rgba(255, 255, 255, 0.12);
            --text: #f4f4f5;
            --muted: #a1a1aa;
            --blue-soft: rgba(37, 99, 235, 0.14);
            --green-soft: rgba(22, 163, 74, 0.14);
            --red-soft: rgba(220, 38, 38, 0.14);
            --amber-soft: rgba(245, 158, 11, 0.14);
            --shadow: 0 18px 45px rgba(0, 0, 0, 0.28);
        }

        .review-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 22px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .review-header {
            background: linear-gradient(135deg, #1d4ed8, #2563eb, #60a5fa);
            padding: 22px;
            color: white;
        }

        .review-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .review-title-wrap {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .review-status-icon {
            width: 52px;
            height: 52px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 900;
        }

        .review-title {
            font-size: 20px;
            font-weight: 900;
            line-height: 1.2;
        }

        .review-subtitle {
            margin-top: 5px;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.86);
        }

        .review-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border-radius: 999px;
            padding: 8px 13px;
            font-size: 12px;
            font-weight: 900;
            border: 1px solid currentColor;
            background: white;
        }

        .review-badge.is-pending {
            color: #b45309;
        }

        .review-badge.is-accepted {
            color: #15803d;
        }

        .review-badge.is-rejected {
            color: #b91c1c;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            padding: 18px;
            background: var(--card-bg);
        }

        .summary-item {
            border: 1px solid var(--border);
            background: var(--soft-bg);
            border-radius: 16px;
            padding: 15px;
        }

        .summary-label {
            font-size: 12px;
            color: var(--muted);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .summary-value {
            margin-top: 7px;
            font-size: 15px;
            font-weight: 900;
            color: var(--text);
            word-break: break-word;
        }

        .note-box {
            margin: 0 18px 18px 18px;
            border-radius: 16px;
            border: 1px solid rgba(220, 38, 38, 0.25);
            background: var(--red-soft);
            padding: 15px;
        }

        .note-title {
            color: var(--red);
            font-size: 14px;
            font-weight: 900;
            margin-bottom: 6px;
        }

        .note-text {
            color: var(--text);
            font-size: 14px;
            line-height: 1.7;
        }

        .section-card {
            margin-top: 18px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
        }

        .section-head {
            padding: 17px 20px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(90deg, var(--blue-soft), transparent);
        }

        .section-title {
            font-size: 16px;
            font-weight: 900;
            color: var(--text);
        }

        .section-desc {
            margin-top: 4px;
            font-size: 13px;
            color: var(--muted);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            padding: 18px;
        }

        .info-item {
            border: 1px solid var(--border);
            background: var(--soft-bg);
            border-radius: 16px;
            padding: 14px;
        }

        .info-label {
            font-size: 12px;
            font-weight: 900;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin-bottom: 6px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 800;
            color: var(--text);
            line-height: 1.6;
            word-break: break-word;
        }

        .field-list {
            padding: 18px;
        }

        .field-row {
            display: grid;
            grid-template-columns: 260px minmax(0, 1fr);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .field-name {
            background: var(--soft-bg);
            padding: 13px 15px;
            font-size: 13px;
            font-weight: 900;
            color: var(--text);
            border-right: 1px solid var(--border);
        }

        .field-value {
            padding: 13px 15px;
            font-size: 14px;
            color: var(--text);
            line-height: 1.7;
            word-break: break-word;
        }

        .code-value {
            display: block;
            background: var(--soft-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            font-size: 12px;
            line-height: 1.6;
            white-space: pre-wrap;
            color: var(--text);
            overflow-x: auto;
        }

        .empty-box {
            margin: 18px;
            border: 1px dashed var(--border);
            border-radius: 18px;
            padding: 32px;
            text-align: center;
            color: var(--muted);
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .summary-grid,
            .info-grid {
                grid-template-columns: 1fr;
            }

            .field-row {
                grid-template-columns: 1fr;
            }

            .field-name {
                border-right: 0;
                border-bottom: 1px solid var(--border);
            }
        }
    </style>

    <div class="review-card">
        <div class="review-header">
            <div class="review-header-row">
                <div class="review-title-wrap">
                    <div class="review-status-icon">
                        {{ $statusIcon }}
                    </div>

                    <div>
                        <div class="review-title">
                            {{ __('review_applications.details_title') }}
                        </div>

                        <div class="review-subtitle">
                            {{ __('review_applications.submitted_at') }}:
                            <strong>{{ $submittedAt }}</strong>
                        </div>
                    </div>
                </div>

                <div class="review-badge {{ $statusClass }}">
                    {{ $statusText }}
                </div>
            </div>
        </div>

        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">
                    {{ __('review_applications.student') }}
                </div>
                <div class="summary-value">
                    {{ $record->creator?->name ?? '-' }}
                </div>
            </div>

            <div class="summary-item">
                <div class="summary-label">
                    {{ __('review_applications.review_status') }}
                </div>
                <div class="summary-value">
                    {{ $statusText }}
                </div>
            </div>

            <div class="summary-item">
                <div class="summary-label">
                    {{ __('review_applications.reviewed_at') }}
                </div>
                <div class="summary-value">
                    {{ $reviewedAt }}
                </div>
            </div>
        </div>

        @if (filled($record->review_note))
            <div class="note-box">
                <div class="note-title">
                    {{ __('review_applications.review_note') }}
                </div>

                <div class="note-text">
                    {{ $record->review_note }}
                </div>
            </div>
        @endif
    </div>

    @if ($primaryData->isNotEmpty())
        <div class="section-card">
            <div class="section-head">
                <div class="section-title">
                    {{ __('review_applications.application_information') }}
                </div>

                <div class="section-desc">
                    {{ __('review_applications.details_title') }}
                </div>
            </div>

            <div class="info-grid">
                @foreach ($primaryData as $key => $value)
                    <div class="info-item">
                        <div class="info-label">
                            {{ $fieldLabel($key) }}
                        </div>

                        <div class="info-value">
                            @if (is_array($value))
                                <pre class="code-value">{{ $formatValue($value) }}</pre>
                            @else
                                {{ $formatValue($value) }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="section-card">
        <div class="section-head">
            <div class="section-title">
                {{ __('review_applications.all_fields') }}
            </div>

            <div class="section-desc">
                {{ count($data) }} {{ __('review_applications.fields_count') }}
            </div>
        </div>

        @if ($otherData->isNotEmpty())
            <div class="field-list">
                @foreach ($otherData as $key => $value)
                    <div class="field-row">
                        <div class="field-name">
                            {{ $fieldLabel($key) }}
                        </div>

                        <div class="field-value">
                            @if (is_array($value))
                                <pre class="code-value">{{ $formatValue($value) }}</pre>
                            @else
                                {{ $formatValue($value) }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-box">
                {{ __('review_applications.no_data') }}
            </div>
        @endif
    </div>
</div>
