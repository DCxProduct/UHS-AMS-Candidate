<x-filament-panels::page>
    @php
        $studentUser = $studentUser ?? [
            'name' => __('app.student'),
            'email' => null,
            'phone' => null,
            'initial' => 'S',
        ];

        $academicYear = $academicYear ?? [
            'id' => null,
            'label' => now()->year,
        ];

        $summary = $summary ?? [
            'total_forms' => 0,
            'submitted_forms' => 0,
            'available_forms' => 0,
            'waiting_review' => 0,
            'approved_forms' => 0,
            'overall_progress' => 0,
        ];

        $forms = $forms ?? [];
        $monthly = $monthly ?? [];

        $primaryForm = collect($forms)->firstWhere('slug', 'enrollment') ?? ($forms[0] ?? null);

        $studentName = $studentUser['name'] ?? __('app.student');
        $studentEmail = $studentUser['email'] ?: __('app.no_email');
        $studentPhone = $studentUser['phone'] ?: __('app.no_phone_number');
        $studentInitial = $studentUser['initial'] ?? 'S';

        $formName = $primaryForm['name'] ?? __('app.enrollment');
        $formStatusLabel = $primaryForm['form_status_label'] ?? __('app.open');
        $formStatusKey = $primaryForm['form_status_key'] ?? 'open';

        $submitLimitLabel = $primaryForm['submit_limit_label'] ?? __('app.can_submit');
        $reviewStatusLabel = $primaryForm['review_status_label'] ?? __('app.not_submitted');
        $reviewStatusKey = $primaryForm['review_status_key'] ?? 'not_submitted';

        $progressPercent = (int) ($primaryForm['progress_percent'] ?? 0);
        $overallProgress = (int) ($summary['overall_progress'] ?? $progressPercent);

        $nextStep = $primaryForm['next_step'] ?? [
            'icon' => '✍',
            'title' => __('app.form_not_submitted_title', ['name' => $formName]),
            'message' => __('app.form_not_submitted_message'),
        ];

        $statusBadgeClass = match ($formStatusKey) {
            'open' => 'badge-success',
            'expired' => 'badge-danger',
            'not_open_yet' => 'badge-warning',
            default => 'badge-gray',
        };

        $reviewBadgeClass = match ($reviewStatusKey) {
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            'need_correction' => 'badge-warning',
            'waiting_review' => 'badge-warning',
            default => 'badge-gray',
        };

        $timelineStep = match ($reviewStatusKey) {
            'approved', 'rejected' => 4,
            'need_correction' => 3,
            'waiting_review' => 2,
            default => 1,
        };

        $latestMonth = collect($monthly)->last();

        $chartMonth = $latestMonth['label'] ?? now()->format('m/Y');
        $chartMonthCount = (int) ($latestMonth['count'] ?? 0);
        $chartMonthPercent = (int) ($latestMonth['percent'] ?? 0);
    @endphp

    <style>
        h1.fi-header-heading {
            font-size: 32px !important;
            font-weight: 900 !important;
            line-height: 1.2 !important;
            letter-spacing: -0.02em;
        }

        .hero-title {
            font-size: 32px !important;
            font-weight: 900;
            color: var(--text-main);
            line-height: 1.2;
            margin: 0;
            letter-spacing: -0.02em;
        }

        .student-dashboard-wrapper {
            --bg-card: #ffffff;
            --bg-soft: #f8fafc;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e5e7eb;
            --warning: #f59e0b;
            --warning-soft: rgba(245, 158, 11, 0.12);
            --success: #22c55e;
            --success-soft: rgba(34, 197, 94, 0.12);
            --danger: #ef4444;
            --danger-soft: rgba(239, 68, 68, 0.12);
            --blue: #2563eb;
            --shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            width: 100%;
        }

        .dark .student-dashboard-wrapper {
            --bg-card: #18181b;
            --bg-soft: #23232a;
            --text-main: #ffffff;
            --text-muted: #a1a1aa;
            --border: rgba(255, 255, 255, .08);
            --shadow: 0 12px 32px rgba(0, 0, 0, .35);
        }

        .dash-grid {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 1rem;
        }

        .dash-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 22px;
            box-shadow: var(--shadow);
            padding: 1.25rem;
        }

        .hero-card {
            grid-column: span 8;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background:
                linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, rgba(245, 158, 11, .10) 100%),
                var(--bg-card);
        }

        .profile-card {
            grid-column: span 4;
            min-height: 120px;
            display: flex;
            align-items: center;
        }

        .hero-title {
            font-size: 2rem;
            font-weight: 900;
            color: var(--text-main);
            line-height: 1.2;
        }

        .hero-subtitle {
            margin-top: .6rem;
            color: var(--text-muted);
            font-size: .95rem;
            line-height: 1.65;
        }

        .profile-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            width: 100%;
        }

        .avatar-box {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            background: var(--warning-soft);
            border: 1px solid rgba(245, 158, 11, .20);
            color: var(--warning);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1.8rem;
            flex-shrink: 0;
        }

        .profile-name {
            font-size: 1.2rem;
            font-weight: 900;
            color: var(--text-main);
        }

        .profile-meta {
            color: var(--text-muted);
            font-size: .95rem;
            margin-top: .15rem;
        }

        .small-card {
            grid-column: span 3;
            min-height: 112px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .small-label {
            font-size: .9rem;
            font-weight: 800;
            color: var(--text-muted);
        }

        .small-value {
            font-size: 1.55rem;
            font-weight: 900;
            color: var(--text-main);
            line-height: 1.15;
            margin-top: .35rem;
        }

        .small-sub {
            margin-top: .35rem;
            color: var(--text-muted);
            font-size: .85rem;
            line-height: 1.45;
        }

        .icon-pill {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            background: var(--warning-soft);
            border: 1px solid rgba(245, 158, 11, .20);
            color: var(--warning);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 900;
            text-align: center;
            flex-shrink: 0;
        }

        .chart-card {
            grid-column: span 3;
            min-height: 270px;
        }

        .card-title {
            font-size: 1.15rem;
            font-weight: 900;
            color: var(--text-main);
            margin-bottom: .9rem;
        }

        .bar-chart-wrap {
            height: 180px;
            display: flex;
            align-items: end;
            justify-content: center;
            gap: 1.25rem;
            padding-top: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .bar-single {
            width: 56px;
            border-radius: 14px 14px 0 0;
            background: linear-gradient(180deg, #fbbf24 0%, #f97316 100%);
            height: {{ ((int) ($summary['submitted_forms'] ?? 0)) > 0 ? '90px' : '18px' }};
            position: relative;
        }

        .bar-single::before {
            content: "{{ (int) ($summary['submitted_forms'] ?? 0) }}";
            position: absolute;
            top: -24px;
            left: 50%;
            transform: translateX(-50%);
            color: var(--text-main);
            font-weight: 800;
            font-size: .95rem;
        }

        .bar-label {
            margin-top: .8rem;
            text-align: center;
            color: var(--text-muted);
            font-size: .9rem;
        }

        .donut-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            height: 190px;
        }

        .donut {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background:
                conic-gradient(var(--blue) 0 {{ $overallProgress }}%, #e5e7eb {{ $overallProgress }}% 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dark .donut {
            background:
                conic-gradient(var(--blue) 0 {{ $overallProgress }}%, #3f3f46 {{ $overallProgress }}% 100%);
        }

        .donut-inner {
            width: 86px;
            height: 86px;
            border-radius: 50%;
            background: var(--bg-card);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: var(--text-main);
            font-weight: 900;
            line-height: 1.1;
            font-size: 1rem;
        }

        .legend-wrap {
            display: flex;
            flex-direction: column;
            gap: .75rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: .55rem;
            color: var(--text-muted);
            font-size: .9rem;
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            flex-shrink: 0;
        }

        .dot-blue {
            background: var(--blue);
        }

        .dot-pink {
            background: #ec4899;
        }

        .timeline-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: .6rem;
        }

        .timeline-item {
            display: flex;
            align-items: center;
            gap: .8rem;
            color: var(--text-muted);
            font-size: 1rem;
            font-weight: 700;
        }

        .timeline-number {
            width: 32px;
            height: 32px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .9rem;
            font-weight: 900;
            border: 1px solid var(--border);
            background: #f1f5f9;
            color: #64748b;
        }

        .dark .timeline-number {
            background: #27272a;
            color: #a1a1aa;
        }

        .timeline-active .timeline-number {
            background: linear-gradient(180deg, #fbbf24, #f97316);
            color: #fff;
            border-color: transparent;
        }

        .timeline-active .timeline-text {
            color: var(--text-main);
        }

        .month-row {
            display: grid;
            grid-template-columns: 110px 1fr 25px;
            gap: .8rem;
            align-items: center;
            margin-top: 1.3rem;
        }

        .month-label,
        .month-value {
            color: var(--text-muted);
            font-size: .95rem;
        }

        .month-bar-bg {
            height: 14px;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .dark .month-bar-bg {
            background: #3f3f46;
        }

        .month-bar-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #fbbf24, #f59e0b);
        }

        .message-card {
            grid-column: span 12;
            display: flex;
            gap: 1rem;
            align-items: center;
            min-height: 92px;
        }

        .message-icon {
            width: 46px;
            height: 46px;
            border-radius: 999px;
            background: var(--warning);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            font-weight: 900;
            flex-shrink: 0;
        }

        .message-title {
            color: var(--text-main);
            font-size: 1.2rem;
            font-weight: 900;
        }

        .message-text {
            color: var(--text-muted);
            margin-top: .25rem;
            font-size: .95rem;
            line-height: 1.6;
        }

        .table-card {
            grid-column: span 12;
            padding: 0;
            overflow: hidden;
        }

        .table-head {
            padding: 1.35rem 1.4rem 1rem;
            border-bottom: 1px solid var(--border);
        }

        .table-title {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--text-main);
        }

        .table-subtitle {
            margin-top: .35rem;
            color: var(--text-muted);
            font-size: .92rem;
        }

        .table-scroll {
            overflow-x: auto;
            padding: 1rem;
        }

        .student-table {
            width: 100%;
            min-width: 1100px;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 18px;
        }

        .student-table thead th {
            background: #f8fafc;
            color: var(--text-main);
            font-size: .9rem;
            font-weight: 900;
            text-align: left;
            padding: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .dark .student-table thead th {
            background: #23232a;
        }

        .student-table tbody td {
            padding: 1rem;
            color: var(--text-main);
            border-bottom: 1px solid var(--border);
            font-size: .95rem;
        }

        .student-table tbody tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .42rem .9rem;
            border-radius: 999px;
            font-size: .85rem;
            font-weight: 900;
            line-height: 1;
            white-space: nowrap;
        }

        .badge-success {
            background: var(--success-soft);
            color: var(--success);
            border: 1px solid rgba(34, 197, 94, .20);
        }

        .badge-warning {
            background: var(--warning-soft);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, .20);
        }

        .badge-danger {
            background: var(--danger-soft);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, .20);
        }

        .badge-gray {
            background: rgba(148, 163, 184, .12);
            color: var(--text-muted);
            border: 1px solid var(--border);
        }

        @media (max-width: 1400px) {
            .hero-card,
            .profile-card {
                grid-column: span 12;
            }

            .small-card,
            .chart-card {
                grid-column: span 6;
            }
        }

        @media (max-width: 900px) {
            .small-card,
            .chart-card {
                grid-column: span 12;
            }

            .hero-title {
                font-size: 1.6rem;
            }
        }
    </style>

    <div class="student-dashboard-wrapper">
        <div class="dash-grid">
            <div class="dash-card hero-card">
                <div class="hero-title">
                    {{ __('app.welcome_back') }}, {{ $studentName }}
                </div>

                <div class="hero-subtitle">
                    {{ __('app.track_enrollment_description') }}
                </div>
            </div>

            <div class="dash-card profile-card">
                <div class="profile-row">
                    <div class="avatar-box">
                        {{ $studentInitial }}
                    </div>

                    <div>
                        <div class="profile-name">{{ $studentName }}</div>
                        <div class="profile-meta">{{ $studentEmail }}</div>
                        <div class="profile-meta">{{ $studentPhone }}</div>
                    </div>
                </div>
            </div>

            <div class="dash-card small-card">
                <div>
                    <div class="small-label">{{ __('app.current_academic_year') }}</div>
                    <div class="small-value">{{ $academicYear['label'] }}</div>
                </div>

                <div class="icon-pill">📅</div>
            </div>

            <div class="dash-card small-card">
                <div>
                    <div class="small-label">{{ __('app.submit_limit') }}</div>

                    <div class="badge badge-warning" style="margin-top:.45rem;">
                        {{ $submitLimitLabel }}
                    </div>

                    <div class="small-sub">
                        {{ __('app.one_submission_per_academic_year') }}
                    </div>
                </div>

                <div class="icon-pill" style="font-size:1.4rem;">
                    1x<br>
                    <span style="font-size:.7rem;line-height:1;">
                        {{ __('app.per_year') }}
                    </span>
                </div>
            </div>

            <div class="dash-card small-card">
                <div>
                    <div class="small-label">{{ __('app.enrollment_form_status') }}</div>

                    <div style="margin-top:.55rem;">
                        <span class="badge {{ $statusBadgeClass }}">
                            {{ $formStatusLabel }}
                        </span>
                    </div>
                </div>

                <div class="icon-pill">📝</div>
            </div>

            <div class="dash-card small-card">
                <div>
                    <div class="small-label">{{ __('app.your_enrollment_status') }}</div>

                    <div style="margin-top:.55rem;">
                        <span class="badge {{ $reviewBadgeClass }}">
                            {{ $reviewStatusLabel }}
                        </span>
                    </div>
                </div>

                <div class="icon-pill">🎓</div>
            </div>

            <div class="dash-card chart-card">
                <div class="card-title">{{ __('app.enrollment_by_year') }}</div>

                <div class="bar-chart-wrap">
                    <div>
                        <div class="bar-single"></div>
                        <div class="bar-label">{{ $academicYear['label'] }}</div>
                    </div>
                </div>
            </div>

            <div class="dash-card chart-card">
                <div class="card-title">{{ __('app.enrollment_status') }}</div>

                <div class="donut-wrap">
                    <div class="donut">
                        <div class="donut-inner">
                            {{ $overallProgress }}%<br>
                            {{ __('app.progress') }}
                        </div>
                    </div>

                    <div class="legend-wrap">
                        <div class="legend-item">
                            <span class="legend-dot dot-blue"></span>
                            <span>{{ $reviewStatusLabel }}</span>
                        </div>

                        <div class="legend-item">
                            <span class="legend-dot dot-pink"></span>
                            <span>{{ $formStatusLabel }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dash-card chart-card">
                <div class="card-title">{{ __('app.enrollment_progress') }}</div>

                <div class="timeline-list">
                    <div class="timeline-item {{ $timelineStep >= 1 ? 'timeline-active' : '' }}">
                        <div class="timeline-number">1</div>
                        <div class="timeline-text">{{ __('app.start_enrollment') }}</div>
                    </div>

                    <div class="timeline-item {{ $timelineStep >= 2 ? 'timeline-active' : '' }}">
                        <div class="timeline-number">2</div>
                        <div class="timeline-text">{{ __('app.submitted_once') }}</div>
                    </div>

                    <div class="timeline-item {{ $timelineStep >= 3 ? 'timeline-active' : '' }}">
                        <div class="timeline-number">3</div>
                        <div class="timeline-text">{{ __('app.registrar_review') }}</div>
                    </div>

                    <div class="timeline-item {{ $timelineStep >= 4 ? 'timeline-active' : '' }}">
                        <div class="timeline-number">4</div>
                        <div class="timeline-text">{{ __('app.final_result') }}</div>
                    </div>
                </div>
            </div>

            <div class="dash-card chart-card">
                <div class="card-title">{{ __('app.submission_by_month') }}</div>

                <div class="month-row">
                    <div class="month-label">{{ $chartMonth }}</div>

                    <div class="month-bar-bg">
                        <div
                            class="month-bar-fill"
                            style="width: {{ max($chartMonthPercent, $chartMonthCount > 0 ? 15 : 0) }}%;"
                        ></div>
                    </div>

                    <div class="month-value">{{ $chartMonthCount }}</div>
                </div>
            </div>

            <div class="dash-card message-card">
                <div class="message-icon">
                    {{ $nextStep['icon'] }}
                </div>

                <div>
                    <div class="message-title">{{ $nextStep['title'] }}</div>
                    <div class="message-text">{{ $nextStep['message'] }}</div>
                </div>
            </div>

            <div class="dash-card table-card">
                <div class="table-head">
                    <div class="table-title">{{ __('app.enrollment_data') }}</div>

                    <div class="table-subtitle">
                        {{ __('app.showing_only_your_enrollment_record') }}
                    </div>
                </div>

                <div class="table-scroll">
                    <table class="student-table">
                        <thead>
                        <tr>
                            <th>{{ __('app.academic_year') }}</th>
                            <th>{{ __('app.application') }}</th>
                            <th>{{ __('app.period') }}</th>
                            <th>{{ __('app.submit_limit') }}</th>
                            <th>{{ __('app.form_status') }}</th>
                            <th>{{ __('app.your_status') }}</th>
                            <th>{{ __('app.submitted_date') }}</th>
                        </tr>
                        </thead>

                        <tbody>
                        @forelse ($forms as $form)
                            @php
                                $rowStatusBadge = match ($form['form_status_key'] ?? 'open') {
                                    'open' => 'badge-success',
                                    'expired' => 'badge-danger',
                                    'not_open_yet' => 'badge-warning',
                                    default => 'badge-gray',
                                };

                                $rowReviewBadge = match ($form['review_status_key'] ?? 'waiting_review') {
                                    'approved' => 'badge-success',
                                    'rejected' => 'badge-danger',
                                    'need_correction' => 'badge-warning',
                                    'waiting_review' => 'badge-warning',
                                    default => 'badge-gray',
                                };
                            @endphp

                            <tr>
                                <td>{{ $academicYear['label'] }}</td>
                                <td><strong>{{ $form['name'] }}</strong></td>
                                <td>{{ $form['period'] }}</td>

                                <td>
                                    <span class="badge badge-warning">
                                        {{ $form['submit_limit_label'] }}
                                    </span>
                                </td>

                                <td>
                                    <span class="badge {{ $rowStatusBadge }}">
                                        {{ $form['form_status_label'] }}
                                    </span>
                                </td>

                                <td>
                                    <span class="badge {{ $rowReviewBadge }}">
                                        {{ $form['review_status_label'] }}
                                    </span>
                                </td>

                                <td>{{ $form['submitted_at'] ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    {{ __('app.no_student_submissions') }}
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
