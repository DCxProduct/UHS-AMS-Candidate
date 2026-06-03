<x-filament-panels::page>
    @php
        $form = $this->getForm();
        $status = $this->getClosingDateStatus();
        $contacts = $this->getContacts();

        $telegramUrl = function (?string $telegram): string {
            $telegram = trim((string) $telegram);

            if ($telegram === '') {
                return '#';
            }

            return 'https://t.me/' . ltrim($telegram, '@');
        };

        $phoneUrl = function (?string $phone): string {
            return 'tel:' . preg_replace('/[^0-9+]/', '', (string) $phone);
        };

        $initials = function (string $name): string {
            return collect(explode(' ', $name))
                ->filter()
                ->map(fn ($word) => mb_substr($word, 0, 1))
                ->take(2)
                ->implode('');
        };
    @endphp

    <div class="uhs-contact-page">
        <style>
            .uhs-contact-page {
                --uhs-bg-card: #ffffff;
                --uhs-bg-soft: #f8fafc;
                --uhs-border: rgba(15, 23, 42, 0.10);
                --uhs-text: #111827;
                --uhs-muted: #64748b;
                --uhs-heading: #020617;
                --uhs-primary: #f59e0b;
                --uhs-primary-soft: rgba(245, 158, 11, 0.12);
                --uhs-danger: #ef4444;
                --uhs-danger-soft: rgba(239, 68, 68, 0.10);
                --uhs-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
                width: 100%;
            }

            .dark .uhs-contact-page {
                --uhs-bg-card: #18181b;
                --uhs-bg-soft: #111113;
                --uhs-border: rgba(255, 255, 255, 0.10);
                --uhs-text: #f4f4f5;
                --uhs-muted: #a1a1aa;
                --uhs-heading: #ffffff;
                --uhs-shadow: 0 22px 55px rgba(0, 0, 0, 0.25);
            }

            .uhs-contact-wrapper {
                max-width: 1180px;
                margin: 0 auto;
            }

            .uhs-contact-hero {
                text-align: center;
                margin-bottom: 1.75rem;
            }

            .uhs-contact-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.45rem;
                padding: 0.45rem 0.85rem;
                border-radius: 999px;
                background: var(--uhs-primary-soft);
                color: var(--uhs-primary);
                font-weight: 800;
                font-size: 0.82rem;
                margin-bottom: 0.85rem;
            }

            .uhs-contact-title {
                color: var(--uhs-heading);
                font-size: 2rem;
                line-height: 1.15;
                font-weight: 900;
                margin: 0;
                letter-spacing: -0.03em;
            }

            .uhs-contact-subtitle {
                max-width: 760px;
                margin: 0.75rem auto 0 auto;
                color: var(--uhs-muted);
                line-height: 1.75;
                font-size: 1rem;
            }

            .uhs-expired-box {
                max-width: 880px;
                margin: 0 auto 2rem auto;
                padding: 1rem 1.15rem;
                border-radius: 18px;
                border: 1px solid rgba(239, 68, 68, 0.30);
                background: var(--uhs-danger-soft);
                display: flex;
                gap: 0.9rem;
                align-items: flex-start;
            }

            .uhs-expired-icon {
                width: 42px;
                height: 42px;
                min-width: 42px;
                border-radius: 999px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: var(--uhs-danger);
                color: #ffffff;
                font-weight: 900;
                font-size: 1.15rem;
            }

            .uhs-expired-title {
                color: var(--uhs-danger);
                font-weight: 900;
                font-size: 1rem;
                margin-bottom: 0.25rem;
            }

            .uhs-expired-message {
                color: var(--uhs-muted);
                line-height: 1.65;
                font-size: 0.92rem;
            }

            .uhs-contact-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 1.25rem;
            }

            .uhs-contact-card {
                position: relative;
                overflow: hidden;
                border: 1px solid var(--uhs-border);
                background: var(--uhs-bg-card);
                border-radius: 24px;
                box-shadow: var(--uhs-shadow);
                padding: 1.5rem;
            }

            .uhs-contact-card::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 5px;
                background: linear-gradient(90deg, var(--uhs-primary), #fbbf24);
            }

            .uhs-person-row {
                display: flex;
                align-items: center;
                gap: 1rem;
                padding-bottom: 1.25rem;
                border-bottom: 1px solid var(--uhs-border);
            }

            .uhs-avatar {
                width: 72px;
                height: 72px;
                min-width: 72px;
                border-radius: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, rgba(245, 158, 11, 0.25), rgba(245, 158, 11, 0.08));
                color: var(--uhs-primary);
                border: 1px solid rgba(245, 158, 11, 0.25);
                font-weight: 900;
                font-size: 1.45rem;
            }

            .uhs-person-name {
                color: var(--uhs-heading);
                font-weight: 900;
                font-size: 1.05rem;
                text-transform: uppercase;
                line-height: 1.25;
            }

            .uhs-person-position {
                color: var(--uhs-muted);
                margin-top: 0.3rem;
                line-height: 1.45;
                font-size: 0.92rem;
            }

            .uhs-contact-actions {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 0.8rem;
                margin-top: 1.25rem;
            }

            .uhs-contact-action {
                min-height: 130px;
                border: 1px solid var(--uhs-border);
                border-radius: 18px;
                background: var(--uhs-bg-soft);
                padding: 1rem 0.75rem;
                text-decoration: none;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                transition: 0.2s ease;
            }

            .uhs-contact-action:hover {
                transform: translateY(-2px);
                border-color: rgba(245, 158, 11, 0.45);
                box-shadow: 0 14px 30px rgba(245, 158, 11, 0.10);
            }

            .uhs-action-icon {
                width: 46px;
                height: 46px;
                border-radius: 999px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #525252;
                color: #ffffff;
                font-size: 1.15rem;
                margin-bottom: 0.75rem;
            }

            .uhs-action-label {
                color: var(--uhs-heading);
                font-weight: 900;
                font-size: 0.85rem;
                margin-bottom: 0.35rem;
            }

            .uhs-action-value {
                color: var(--uhs-muted);
                font-size: 0.8rem;
                line-height: 1.4;
                word-break: break-word;
            }

            @media (max-width: 1024px) {
                .uhs-contact-grid {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 640px) {
                .uhs-contact-title {
                    font-size: 1.55rem;
                }

                .uhs-expired-box,
                .uhs-person-row {
                    flex-direction: column;
                }

                .uhs-contact-actions {
                    grid-template-columns: 1fr;
                }

                .uhs-contact-card {
                    padding: 1.15rem;
                }
            }
        </style>

        <div class="uhs-contact-wrapper">
            <div class="uhs-contact-hero">
                <div class="uhs-contact-badge">
                    <span>☎</span>
                    <span>{{ __('app.student_support') }}</span>
                </div>

                <h2 class="uhs-contact-title">
                    {{ __('app.contact_admissions_support') }}
                </h2>

                <p class="uhs-contact-subtitle">
                    {{ __('app.application_period_ended') }}
                </p>
            </div>

            @if (($status['status'] ?? null) === 'expired')
                <div class="uhs-expired-box">
                    <div class="uhs-expired-icon">!</div>

                    <div>
                        <div class="uhs-expired-title">
                            {{ __('app.form_has_expired', [
                                'name' => $form?->name ?? __('app.this_form'),
                            ]) }}
                        </div>

                        <div class="uhs-expired-message">
                            {{ $status['message'] ?? __('app.expired_default_message') }}
                        </div>

                        @if (! blank($status['start_date'] ?? null) && ! blank($status['end_date'] ?? null))
                            <div class="uhs-expired-message" style="margin-top: 0.4rem;">
                                {{ __('app.application_period') }}:
                                <strong>{{ $status['start_date'] }}</strong>
                                -
                                <strong>{{ $status['end_date'] }}</strong>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="uhs-contact-grid">
                @foreach ($contacts as $contact)
                    <div class="uhs-contact-card">
                        <div class="uhs-person-row">
                            <div class="uhs-avatar">
                                {{ $initials($contact['name']) }}
                            </div>

                            <div>
                                <div class="uhs-person-name">
                                    {{ $contact['name'] }}
                                </div>

                                <div class="uhs-person-position">
                                    {{ $contact['position'] }}
                                </div>
                            </div>
                        </div>

                        <div class="uhs-contact-actions">
                            <a class="uhs-contact-action" href="{{ $phoneUrl($contact['phone']) }}">
                                <div class="uhs-action-icon">☎</div>
                                <div class="uhs-action-label">{{ __('app.call_us') }}</div>
                                <div class="uhs-action-value">{{ $contact['phone'] }}</div>
                            </a>

                            <a class="uhs-contact-action" href="mailto:{{ $contact['email'] }}">
                                <div class="uhs-action-icon">✉</div>
                                <div class="uhs-action-label">{{ __('app.email_us') }}</div>
                                <div class="uhs-action-value">{{ $contact['email'] }}</div>
                            </a>

                            <a class="uhs-contact-action" href="{{ $telegramUrl($contact['telegram']) }}" target="_blank">
                                <div class="uhs-action-icon">✈</div>
                                <div class="uhs-action-label">{{ __('app.telegram') }}</div>
                                <div class="uhs-action-value">{{ $contact['telegram'] }}</div>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
