<x-filament-panels::page>
    <style>
        .sync-page {
            min-height: 70vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 24px;
            background: #f8fafc;
        }

        .sync-card {
            width: 100%;
            max-width: 100%;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            padding: 32px;
        }

        .sync-card-centered {
            text-align: center;
        }

        .sync-icon {
            width: 64px;
            height: 64px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #eff6ff;
            color: #2563eb;
            margin-bottom: 18px;
        }

        .sync-title {
            margin: 0 0 10px;
            font-size: 28px;
            font-weight: 700;
            color: #111827;
        }

        .sync-text {
            margin: 0;
            font-size: 15px;
            color: #6b7280;
            line-height: 1.6;
        }

        .sync-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 132px;
            min-height: 46px;
            margin-top: 24px;
            padding: 12px 22px;
            border: none;
            border-radius: 8px;
            background: #2563eb;
            color: #ffffff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25);
            transition: background-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        .sync-button:hover:not(:disabled) {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.3);
        }

        .sync-button:disabled {
            cursor: wait;
            opacity: 0.78;
        }

        .sync-button-icon {
            width: 18px;
            height: 18px;
            flex: 0 0 18px;
        }

        .sync-button-icon.is-spinning {
            animation: sync-spin 0.9s linear infinite;
        }

        .sync-alert {
            display: none;
            margin-top: 22px;
            padding: 14px 16px;
            border-radius: 8px;
            text-align: left;
            font-size: 14px;
            line-height: 1.5;
        }

        .sync-alert.is-visible {
            display: block;
        }

        .sync-alert.is-success {
            color: #166534;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
        }

        .sync-alert.is-error {
            color: #991b1b;
            background: #fef2f2;
            border: 1px solid #fecaca;
        }

        .sync-alert-title {
            display: block;
            margin-bottom: 2px;
            font-weight: 700;
        }

        @keyframes sync-spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ── Results section ── */
        .sync-results {
            display: none;
            margin-top: 28px;
            border-top: 1px solid #e5e7eb;
            padding-top: 24px;
            text-align: left;
        }

        .sync-results.is-visible {
            display: block;
        }

        .sync-status-banner {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }

        .sync-status-banner.is-success {
            color: #166534;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
        }

        .sync-status-banner.is-error {
            color: #991b1b;
            background: #fef2f2;
            border: 1px solid #fecaca;
        }

        .sync-status-icon {
            width: 24px;
            height: 24px;
            flex: 0 0 24px;
        }

        .sync-status-content {
            flex: 1;
        }

        .sync-status-title {
            font-weight: 700;
            display: block;
        }

        .sync-status-message {
            display: block;
            margin-top: 2px;
        }

        .sync-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .sync-mode-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .sync-mode-badge.is-full_resync {
            color: #7c3aed;
            background: #f5f3ff;
            border: 1px solid #ddd6fe;
        }

        .sync-mode-badge.is-incremental {
            color: #0369a1;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
        }

        .sync-mode-badge.is-auto {
            color: #b45309;
            background: #fffbeb;
            border: 1px solid #fde68a;
        }

        /* ── Stats grid ── */
        .sync-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .sync-stat {
            text-align: center;
            padding: 16px 8px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .sync-stat-value {
            display: block;
            font-size: 24px;
            font-weight: 800;
            color: #111827;
        }

        .sync-stat-label {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* ── Per-table list ── */
        .sync-tables {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }

        .sync-tables-header {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 12px;
            padding: 10px 16px;
            background: #f3f4f6;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
        }

        .sync-table-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 12px;
            padding: 12px 16px;
            align-items: center;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
            transition: background 0.15s;
        }

        .sync-table-row:last-child {
            border-bottom: none;
        }

        .sync-table-row:hover {
            background: #f9fafb;
        }

        .sync-table-name {
            font-weight: 600;
            color: #111827;
            font-family: ui-monospace, monospace;
            font-size: 13px;
        }

        .sync-table-counts {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sync-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            padding: 1px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .sync-pill-fetched {
            color: #6b7280;
            background: #f3f4f6;
        }

        .sync-pill-synced {
            color: #166534;
            background: #dcfce7;
        }

        .sync-pill-unchanged {
            color: #92400e;
            background: #fef3c7;
        }

        .sync-table-status {
            font-size: 12px;
            font-weight: 600;
            text-align: right;
        }

        .sync-table-status.is-synced {
            color: #16a34a;
        }

        .sync-table-status.is-unchanged {
            color: #d97706;
        }

        .sync-table-status.is-error {
            color: #dc2626;
        }

        .sync-no-tables {
            text-align: center;
            padding: 24px;
            color: #9ca3af;
            font-size: 14px;
        }

        /* ── Responsive ── */
        @media (max-width: 600px) {
            .sync-card {
                padding: 20px;
            }
            .sync-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            .sync-tables-header,
            .sync-table-row {
                grid-template-columns: 1fr auto;
            }
            .sync-tables-header span:last-child,
            .sync-table-row .sync-table-status {
                display: none;
            }
        }
    </style>

    <div
        class="sync-page"
        data-sync-page
        data-auto-run="{{ request()->boolean('run') ? 'true' : 'false' }}"
        data-sync-url="{{ route('sync.run') }}"
        data-csrf-token="{{ csrf_token() }}"
    >
        <div class="sync-card">
            <div class="sync-card-centered">
                <div class="sync-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="32" height="32">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992M2.985 19.644v-4.992m0 0h4.992m-4.992 0 3.181-3.183a8.25 8.25 0 0 1 13.803 3.183m0 0a8.25 8.25 0 0 1-13.803 3.183" />
                    </svg>
                </div>

                <h2 class="sync-title">{{ __('sync.heading') }}</h2>
                <p class="sync-text">{{ __('sync.description') }}</p>

                <button type="button" class="sync-button" data-sync-button>
                    <svg class="sync-button-icon" data-sync-button-icon xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992M2.985 19.644v-4.992m0 0h4.992m-4.992 0 3.181-3.183a8.25 8.25 0 0 1 13.803 3.183m0 0a8.25 8.25 0 0 1-13.803 3.183" />
                    </svg>
                    <span data-sync-button-label data-sync-now="{{ __('sync.sync_now') }}" data-syncing="{{ __('sync.syncing') }}">
                        {{ __('sync.sync_now') }}
                    </span>
                </button>

                <div class="sync-alert" data-sync-alert role="status" aria-live="polite">
                    <strong class="sync-alert-title" data-sync-alert-title></strong>
                    <span data-sync-alert-message></span>
                </div>
            </div>

            {{-- Results summary --}}
            <div class="sync-results" data-sync-results>
                {{-- Status banner --}}
                <div class="sync-status-banner" data-sync-status-banner>
                    <svg class="sync-status-icon" data-sync-status-icon fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="sync-status-content">
                        <span class="sync-status-title" data-sync-status-title></span>
                        <span class="sync-status-message" data-sync-status-message></span>
                    </div>
                </div>

                {{-- Meta: mode badge --}}
                <div class="sync-meta" data-sync-meta></div>

                {{-- Stats cards --}}
                <div class="sync-stats">
                    <div class="sync-stat">
                        <span class="sync-stat-value" data-sync-stat-tables>0</span>
                        <span class="sync-stat-label">{{ __('sync.stat_tables') }}</span>
                    </div>
                    <div class="sync-stat">
                        <span class="sync-stat-value" data-sync-stat-fetched>0</span>
                        <span class="sync-stat-label">{{ __('sync.stat_fetched') }}</span>
                    </div>
                    <div class="sync-stat">
                        <span class="sync-stat-value" data-sync-stat-synced>0</span>
                        <span class="sync-stat-label">{{ __('sync.stat_synced') }}</span>
                    </div>
                    <div class="sync-stat">
                        <span class="sync-stat-value" data-sync-stat-unchanged>0</span>
                        <span class="sync-stat-label">{{ __('sync.stat_unchanged') }}</span>
                    </div>
                </div>

                {{-- Per-table list --}}
                <div class="sync-tables" data-sync-tables>
                    <div class="sync-tables-header">
                        <span>{{ __('sync.col_table') }}</span>
                        <span>{{ __('sync.col_counts') }}</span>
                        <span>{{ __('sync.col_status') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.syncLang = {
            successTitle: @json(__('sync.success_title')),
            successMessage: @json(__('sync.success_message')),
            errorTitle: @json(__('sync.error_title')),
            errorMessage: @json(__('sync.error_message')),
            connectionError: @json(__('sync.connection_error')),
            labelTables: @json(__('sync.stat_tables')),
            labelFetched: @json(__('sync.stat_fetched')),
            labelSynced: @json(__('sync.stat_synced')),
            labelUnchanged: @json(__('sync.stat_unchanged')),
            labelTable: @json(__('sync.col_table')),
            labelCounts: @json(__('sync.col_counts')),
            labelStatus: @json(__('sync.col_status')),
            modeFull: @json(__('sync.mode_full')),
            modeIncremental: @json(__('sync.mode_incremental')),
            modeAuto: @json(__('sync.mode_auto')),
            statusSynced: @json(__('sync.status_synced')),
            statusUnchanged: @json(__('sync.status_unchanged')),
            statusErrors: @json(__('sync.status_errors')),
        };
    </script>

    <script>
        (() => {
            const page = document.querySelector('[data-sync-page]');

            if (!page) {
                return;
            }

            const button = page.querySelector('[data-sync-button]');
            const icon = page.querySelector('[data-sync-button-icon]');
            const label = page.querySelector('[data-sync-button-label]');
            const alertBox = page.querySelector('[data-sync-alert]');
            const alertTitle = page.querySelector('[data-sync-alert-title]');
            const alertMessage = page.querySelector('[data-sync-alert-message]');

            const resultsContainer = page.querySelector('[data-sync-results]');
            const statusBanner = page.querySelector('[data-sync-status-banner]');
            const statusIcon = page.querySelector('[data-sync-status-icon]');
            const statusTitle = page.querySelector('[data-sync-status-title]');
            const statusMessage = page.querySelector('[data-sync-status-message]');
            const metaContainer = page.querySelector('[data-sync-meta]');
            const statTables = page.querySelector('[data-sync-stat-tables]');
            const statFetched = page.querySelector('[data-sync-stat-fetched]');
            const statSynced = page.querySelector('[data-sync-stat-synced]');
            const statUnchanged = page.querySelector('[data-sync-stat-unchanged]');
            const tablesContainer = page.querySelector('[data-sync-tables]');

            let isSyncing = false;

            const setLoading = (loading) => {
                isSyncing = loading;
                button.disabled = loading;
                icon.classList.toggle('is-spinning', loading);
                label.textContent = loading
                    ? label.dataset.syncing
                    : label.dataset.syncNow;
            };

            const hideResults = () => {
                resultsContainer.classList.remove('is-visible');
            };

            const showResults = () => {
                resultsContainer.classList.add('is-visible');
            };

            const resetResults = () => {
                hideResults();
                statusBanner.className = 'sync-status-banner';
                statusTitle.textContent = '';
                statusMessage.textContent = '';
                metaContainer.innerHTML = '';
                statTables.textContent = '0';
                statFetched.textContent = '0';
                statSynced.textContent = '0';
                statUnchanged.textContent = '0';
                const header = tablesContainer.querySelector('.sync-tables-header');
                tablesContainer.innerHTML = '';
                if (header) {
                    tablesContainer.appendChild(header);
                }
            };

            const escHtml = (str) => {
                if (!str) return '';
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            };

            const renderResults = (result) => {
                const tables = result.data?.tables || [];
                const isSuccess = result.success === true;

                let totalFetched = 0,
                    totalSynced = 0,
                    totalUnchanged = 0;
                tables.forEach(t => {
                    totalFetched += t.fetched || 0;
                    totalSynced += t.synced || 0;
                    totalUnchanged += t.unchanged || 0;
                });

                const hasSyncedData = totalSynced > 0;

                statTables.textContent = tables.length;
                statFetched.textContent = totalFetched;
                statSynced.textContent = totalSynced;
                statUnchanged.textContent = totalUnchanged;

                const statsEl = document.querySelector('.sync-stats');

                if (hasSyncedData) {
                    const mode = result.mode || 'incremental';
                    const isAuto = result.auto_full_resync === true;

                    let modeLabel = mode === 'full_resync'
                        ? window.syncLang.modeFull
                        : window.syncLang.modeIncremental;
                    let modeClass = `sync-mode-badge is-${mode}`;
                    if (isAuto) {
                        modeLabel += ' (' + window.syncLang.modeAuto + ')';
                        modeClass = 'sync-mode-badge is-auto';
                    }
                    metaContainer.innerHTML = `<span class="${modeClass}">${escHtml(modeLabel)}</span>`;
                    metaContainer.style.display = '';
                    if (statsEl) statsEl.style.display = '';
                } else {
                    metaContainer.style.display = 'none';
                    if (statsEl) statsEl.style.display = 'none';
                }

                statusBanner.className = 'sync-status-banner';
                if (isSuccess) {
                    statusBanner.classList.add('is-success');
                    statusIcon.innerHTML =
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />';
                } else {
                    statusBanner.classList.add('is-error');
                    statusIcon.innerHTML =
                        '<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />';
                }

                if (isSuccess && result.message) {
                    statusTitle.textContent = window.syncLang.successTitle;
                    statusMessage.textContent = result.message;
                } else if (!isSuccess && result.message) {
                    statusTitle.textContent = window.syncLang.errorTitle;
                    statusMessage.textContent = result.message;
                } else if (isSuccess) {
                    statusTitle.textContent = window.syncLang.successTitle;
                    statusMessage.textContent = window.syncLang.successMessage;
                } else {
                    statusTitle.textContent = window.syncLang.errorTitle;
                    statusMessage.textContent = window.syncLang.errorMessage;
                }

                if (hasSyncedData && tables.length > 0) {
                    let headerHtml = '<div class="sync-tables-header">' +
                        '<span>' + escHtml(window.syncLang.labelTable) + '</span>' +
                        '<span>' + escHtml(window.syncLang.labelCounts) + '</span>' +
                        '<span>' + escHtml(window.syncLang.labelStatus) + '</span>' +
                        '</div>';

                    let rowsHtml = '';
                    tables.forEach(t => {
                        const hasErrors = t.errors && t.errors.length > 0;
                        let statusClass, statusText;
                        if (hasErrors) {
                            statusClass = 'is-error';
                            statusText = window.syncLang.statusErrors;
                        } else if ((t.synced || 0) > 0) {
                            statusClass = 'is-synced';
                            statusText = window.syncLang.statusSynced;
                        } else {
                            statusClass = 'is-unchanged';
                            statusText = window.syncLang.statusUnchanged;
                        }

                        rowsHtml += '<div class="sync-table-row">' +
                            '<span class="sync-table-name">' + escHtml(t.table) + '</span>' +
                            '<span class="sync-table-counts">' +
                            '<span class="sync-pill sync-pill-fetched">' + (t.fetched || 0) + '</span>' +
                            '<span class="sync-pill sync-pill-synced">' + (t.synced || 0) + '</span>' +
                            '<span class="sync-pill sync-pill-unchanged">' + (t.unchanged || 0) + '</span>' +
                            '</span>' +
                            '<span class="sync-table-status ' + statusClass + '">' + escHtml(statusText) + '</span>' +
                            '</div>';
                    });

                    tablesContainer.innerHTML = headerHtml + rowsHtml;
                } else {
                    tablesContainer.innerHTML = '';
                }

                showResults();
            };

            const showMessage = (type, title, message) => {
                alertBox.className = 'sync-alert is-visible is-' + type;
                alertTitle.textContent = title;
                alertMessage.textContent = message;
            };

            const readResponse = async (response) => {
                try {
                    return await response.json();
                } catch (error) {
                    return {};
                }
            };

            const startSync = async () => {
                if (isSyncing) {
                    return;
                }

                setLoading(true);
                alertBox.className = 'sync-alert';
                alertTitle.textContent = '';
                alertMessage.textContent = '';
                resetResults();

                try {
                    const response = await fetch(page.dataset.syncUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': page.dataset.csrfToken,
                        },
                        body: JSON.stringify({
                            dry_run: false,
                            full_resync: false,
                        }),
                    });

                    const result = await readResponse(response);

                    renderResults(result);
                } catch (error) {
                    showMessage(
                        'error',
                        window.syncLang.errorTitle,
                        window.syncLang.connectionError
                    );
                } finally {
                    setLoading(false);
                }
            };

            button.addEventListener('click', startSync);

            if (page.dataset.autoRun === 'true') {
                startSync();
            }
        })();
    </script>
</x-filament-panels::page>
