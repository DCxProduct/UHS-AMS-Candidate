<x-filament-panels::page>
    <style>
        .sync-page {
            min-height: 70vh;
            padding: 24px;
            background: var(--sync-page-bg);
            color: var(--sync-text);
            color-scheme: light;
            --sync-page-bg: #f8fafc;
            --sync-surface: #ffffff;
            --sync-surface-soft: #f8fafc;
            --sync-border: #e5e7eb;
            --sync-text: #111827;
            --sync-muted: #6b7280;
            --sync-heading: #111827;
            --sync-icon-bg: #ecfeff;
            --sync-icon-color: #0891b2;
            --sync-button-bg: #0f766e;
            --sync-button-hover: #115e59;
            --sync-button-shadow: rgba(15, 118, 110, 0.25);
            --sync-pill-bg: #f1f5f9;
            --sync-pill-color: #334155;
            --sync-pill-auto-bg: #fff7ed;
            --sync-pill-auto-color: #9a3412;
            --sync-ok-bg: #dcfce7;
            --sync-ok-color: #166534;
            --sync-warn-bg: #fef3c7;
            --sync-warn-color: #92400e;
            --sync-error-bg: #fef2f2;
            --sync-error-border: #fecaca;
            --sync-error-color: #991b1b;
            --sync-success-bg: #f0fdf4;
            --sync-success-border: #bbf7d0;
            --sync-success-color: #166534;
            color-scheme: light dark;
        }

        .sync-shell {
            width: 100%;
            max-width: 1040px;
            margin: 0 auto;
        }

        .sync-card {
            width: 100%;
            background: var(--sync-surface);
            border: 1px solid var(--sync-border);
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .sync-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 28px 32px;
            border-bottom: 1px solid var(--sync-border);
        }

        .sync-icon {
            width: 64px;
            height: 64px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: var(--sync-icon-bg);
            color: var(--sync-icon-color);
            flex: 0 0 64px;
        }

        .sync-heading {
            display: flex;
            align-items: center;
            gap: 18px;
            min-width: 0;
        }

        .sync-actions {
            flex: 0 0 auto;
        }

        .sync-title {
            margin: 0 0 10px;
            font-size: 24px;
            font-weight: 700;
            color: var(--sync-heading);
        }

        .sync-text {
            margin: 0;
            font-size: 15px;
            color: var(--sync-muted);
            line-height: 1.6;
        }

        .sync-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 132px;
            min-height: 46px;
            padding: 12px 22px;
            border: none;
            border-radius: 8px;
            background: var(--sync-button-bg);
            color: #ffffff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 8px 20px var(--sync-button-shadow);
            transition: background-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        .sync-button:hover:not(:disabled) {
            background: var(--sync-button-hover);
            transform: translateY(-1px);
            box-shadow: 0 10px 24px var(--sync-button-shadow);
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
            margin: 24px 32px 0;
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
            color: var(--sync-success-color);
            background: var(--sync-success-bg);
            border: 1px solid var(--sync-success-border);
        }

        .sync-alert.is-error {
            color: var(--sync-error-color);
            background: var(--sync-error-bg);
            border: 1px solid var(--sync-error-border);
        }

        .sync-alert-title {
            display: block;
            margin-bottom: 2px;
            font-weight: 700;
        }

        .sync-results {
            display: none;
            padding: 24px 32px 32px;
        }

        .sync-results.is-visible {
            display: block;
        }

        .sync-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 18px;
        }

        .sync-pill {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 5px 10px;
            border-radius: 999px;
            background: var(--sync-pill-bg);
            color: var(--sync-pill-color);
            font-size: 13px;
            font-weight: 600;
        }

        .sync-pill.is-auto {
            background: var(--sync-pill-auto-bg);
            color: var(--sync-pill-auto-color);
        }

        .sync-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .sync-stat {
            border: 1px solid var(--sync-border);
            border-radius: 8px;
            padding: 16px;
            background: var(--sync-surface);
        }

        .sync-stat-label {
            display: block;
            margin-bottom: 6px;
            color: var(--sync-muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .sync-stat-value {
            color: var(--sync-text);
            font-size: 26px;
            font-weight: 750;
            line-height: 1;
        }

        .sync-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--sync-border);
            border-radius: 8px;
            background: var(--sync-surface);
        }

        .sync-table {
            width: 100%;
            min-width: 620px;
            border-collapse: collapse;
            text-align: left;
            font-size: 14px;
        }

        .sync-table th,
        .sync-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--sync-border);
            white-space: nowrap;
        }

        .sync-table th {
            background: var(--sync-surface-soft);
            color: var(--sync-muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .sync-table tr:last-child td {
            border-bottom: none;
        }

        .sync-table-name {
            color: var(--sync-text);
            font-weight: 700;
        }

        .sync-number {
            font-variant-numeric: tabular-nums;
        }

        .sync-status {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .sync-status.is-ok {
            background: var(--sync-ok-bg);
            color: var(--sync-ok-color);
        }

        .sync-status.is-warning {
            background: var(--sync-warn-bg);
            color: var(--sync-warn-color);
        }

        .sync-errors {
            margin-top: 16px;
            padding: 14px 16px;
            border: 1px solid var(--sync-error-border);
            border-radius: 8px;
            background: var(--sync-error-bg);
            color: var(--sync-error-color);
            font-size: 14px;
        }

        .sync-errors-title {
            margin: 0 0 8px;
            font-weight: 700;
        }

        .sync-errors-list {
            margin: 0;
            padding-left: 18px;
        }

        @media (max-width: 720px) {
            .sync-page {
                padding: 16px;
            }

            .sync-header {
                align-items: stretch;
                flex-direction: column;
                padding: 24px;
            }

            .sync-heading {
                align-items: flex-start;
            }

            .sync-icon {
                width: 52px;
                height: 52px;
                flex-basis: 52px;
            }

            .sync-button {
                width: 100%;
            }

            .sync-alert {
                margin: 20px 24px 0;
            }

            .sync-results {
                padding: 20px 24px 24px;
            }

            .sync-stats {
                grid-template-columns: 1fr;
            }
        }

        @keyframes sync-spin {
            to {
                transform: rotate(360deg);
            }
        }

        html.dark .sync-page,
        html[data-theme='dark'] .sync-page,
        .dark .sync-page,
        [data-theme='dark'] .sync-page {
            --sync-page-bg: #020617;
            --sync-surface: #0f172a;
            --sync-surface-soft: #111827;
            --sync-border: #1e293b;
            --sync-text: #e2e8f0;
            --sync-muted: #94a3b8;
            --sync-heading: #f8fafc;
            --sync-icon-bg: rgba(34, 211, 238, 0.14);
            --sync-icon-color: #67e8f9;
            --sync-button-bg: #0f766e;
            --sync-button-hover: #115e59;
            --sync-button-shadow: rgba(15, 118, 110, 0.32);
            --sync-pill-bg: rgba(148, 163, 184, 0.12);
            --sync-pill-color: #e2e8f0;
            --sync-pill-auto-bg: rgba(249, 115, 22, 0.16);
            --sync-pill-auto-color: #fdba74;
            --sync-ok-bg: rgba(34, 197, 94, 0.18);
            --sync-ok-color: #86efac;
            --sync-warn-bg: rgba(245, 158, 11, 0.18);
            --sync-warn-color: #fcd34d;
            --sync-error-bg: rgba(127, 29, 29, 0.45);
            --sync-error-border: rgba(248, 113, 113, 0.35);
            --sync-error-color: #fecaca;
            --sync-success-bg: rgba(22, 101, 52, 0.35);
            --sync-success-border: rgba(34, 197, 94, 0.28);
            --sync-success-color: #bbf7d0;
        }
    </style>

    <div
        class="sync-page"
        data-sync-page
        data-auto-run="{{ request()->boolean('run') ? 'true' : 'false' }}"
        data-sync-url="{{ route('sync.run') }}"
        data-csrf-token="{{ csrf_token() }}"
    >
        <div class="sync-shell">
            <div class="sync-card">
                <div class="sync-header">
                    <div class="sync-heading">
                        <div class="sync-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="32" height="32">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992M2.985 19.644v-4.992m0 0h4.992m-4.992 0 3.181-3.183a8.25 8.25 0 0 1 13.803 3.183m0 0a8.25 8.25 0 0 1-13.803 3.181M7.5 15.5l2.25 2.25M7.5 15.5l-2.25 2.25" stroke-linecap="round" />
                                <circle cx="12" cy="12" r="10.5" stroke-width="1.5" stroke-dasharray="6 4" stroke-linecap="round" opacity="0.25" />
                            </svg>
                        </div>

                        <div>
                            <h2 class="sync-title">ធ្វើសមកាលកម្មទិន្នន័យ</h2>
                            <p class="sync-text">ធ្វើបច្ចុប្បន្នភាពមូលដ្ឋានទិន្នន័យក្នុងស្រុកជាមួយទិន្នន័យម៉ាស៊ីនមេចុងក្រោយ និងពិនិត្យតារាងនីមួយៗដែលបានធ្វើសមកាលកម្ម។</p>
                        </div>
                    </div>

                    <div class="sync-actions">
                        <button type="button" class="sync-button" data-sync-button>
                            <svg class="sync-button-icon" data-sync-button-icon xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992M2.985 19.644v-4.992m0 0h4.992m-4.992 0 3.181-3.183a8.25 8.25 0 0 1 13.803 3.183m0 0a8.25 8.25 0 0 1-13.803 3.181" />
                            </svg>
                            <span data-sync-button-label>ធ្វើសមកាលកម្មឥឡូវនេះ</span>
                        </button>
                    </div>
                </div>

                <div class="sync-alert" data-sync-alert role="status" aria-live="polite">
                    <strong class="sync-alert-title" data-sync-alert-title></strong>
                    <span data-sync-alert-message></span>
                </div>

                <div class="sync-results" data-sync-results>
                    <div class="sync-meta" data-sync-meta></div>

                    <div class="sync-stats">
                        <div class="sync-stat">
                            <span class="sync-stat-label">តារាង</span>
                            <span class="sync-stat-value" data-sync-total-tables>0</span>
                        </div>
                        <div class="sync-stat">
                            <span class="sync-stat-label">ទាញយក</span>
                            <span class="sync-stat-value" data-sync-total-fetched>0</span>
                        </div>
                        <div class="sync-stat">
                            <span class="sync-stat-label">សមកាលកម្ម</span>
                            <span class="sync-stat-value" data-sync-total-synced>0</span>
                        </div>
                    </div>

                    <div class="sync-table-wrap">
                        <table class="sync-table">
                            <thead>
                                <tr>
                                    <th>តារាង</th>
                                    <th>ទាញយក</th>
                                    <th>សមកាលកម្ម</th>
                                    <th>កំហុស</th>
                                    <th>ស្ថានភាព</th>
                                </tr>
                            </thead>
                            <tbody data-sync-table-body></tbody>
                        </table>
                    </div>

                    <div class="sync-errors" data-sync-errors hidden>
                        <p class="sync-errors-title">កំហុសសមកាលកម្ម</p>
                        <ul class="sync-errors-list" data-sync-errors-list></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
            const results = page.querySelector('[data-sync-results]');
            const meta = page.querySelector('[data-sync-meta]');
            const totalTables = page.querySelector('[data-sync-total-tables]');
            const totalFetched = page.querySelector('[data-sync-total-fetched]');
            const totalSynced = page.querySelector('[data-sync-total-synced]');
            const tableBody = page.querySelector('[data-sync-table-body]');
            const errorsBox = page.querySelector('[data-sync-errors]');
            const errorsList = page.querySelector('[data-sync-errors-list]');

            let isSyncing = false;

            const setLoading = (loading) => {
                isSyncing = loading;
                button.disabled = loading;
                icon.classList.toggle('is-spinning', loading);
                label.textContent = loading ? 'កំពុងធ្វើសមកាលកម្ម...' : 'ធ្វើសមកាលកម្មឥឡូវនេះ';
            };

            const showMessage = (type, title, message) => {
                alertBox.className = `sync-alert is-visible is-${type}`;
                alertTitle.textContent = title;
                alertMessage.textContent = message;
            };

            const formatNumber = (value) => new Intl.NumberFormat().format(Number(value) || 0);

            const formatLabel = (value) => String(value || '')
                .replace(/_/g, ' ')
                .replace(/\b\w/g, (letter) => letter.toUpperCase());

            const tableRowsFrom = (result) => {
                if (Array.isArray(result?.data?.summary)) {
                    return result.data.summary;
                }

                if (Array.isArray(result?.data?.tables)) {
                    return result.data.tables;
                }

                if (Array.isArray(result?.summary)) {
                    return result.summary;
                }

                if (Array.isArray(result?.data?.data?.tables)) {
                    return result.data.data.tables;
                }

                return [];
            };

            const normalizeErrors = (errors) => Array.isArray(errors) ? errors.filter(Boolean) : [];

            const errorMessage = (error) => {
                if (typeof error === 'string') {
                    return error;
                }

                if (error && typeof error === 'object') {
                    return error.message || error.detail || JSON.stringify(error);
                }

                return 'កំហុសសមកាលកម្មមិនស្គាល់។';
            };

            const resetResults = () => {
                results.classList.remove('is-visible');
                meta.replaceChildren();
                tableBody.replaceChildren();
                errorsList.replaceChildren();
                errorsBox.hidden = true;
                totalTables.textContent = '0';
                totalFetched.textContent = '0';
                totalSynced.textContent = '0';
            };

            const addPill = (text, extraClass = '') => {
                if (!text) {
                    return;
                }

                const pill = document.createElement('span');
                pill.className = `sync-pill ${extraClass}`.trim();
                pill.textContent = text;
                meta.appendChild(pill);
            };

            const renderErrors = (tableErrors, responseErrors) => {
                const allErrors = [...tableErrors, ...responseErrors];

                errorsList.replaceChildren();
                errorsBox.hidden = allErrors.length === 0;

                allErrors.forEach((error) => {
                    const item = document.createElement('li');
                    item.textContent = error;
                    errorsList.appendChild(item);
                });
            };

            const renderResults = (result) => {
                const rows = tableRowsFrom(result);
                const data = result?.data || {};
                const tableErrors = [];
                const responseErrors = normalizeErrors(result?.errors).map(errorMessage);

                tableBody.replaceChildren();
                meta.replaceChildren();

                addPill(data.mode ? `របៀប៖ ${formatLabel(data.mode)}` : null);
                addPill(data.full_resync ? 'ធ្វើសមកាលកម្មឡើងវិញពេញលេញ' : 'បន្ថែម');
                addPill(data.auto_full_resync ? 'ធ្វើសមកាលកម្មឡើងវិញដោយស្វ័យប្រវត្តិ' : null, 'is-auto');

                const totals = rows.reduce((carry, row) => {
                    carry.fetched += Number(row?.fetched) || 0;
                    carry.synced += Number(row?.synced) || 0;
                    carry.errors += normalizeErrors(row?.errors).length;

                    return carry;
                }, { fetched: 0, synced: 0, errors: 0 });

                rows.forEach((row) => {
                    const errors = normalizeErrors(row?.errors);
                    const tr = document.createElement('tr');
                    const statusClass = errors.length > 0 ? 'is-warning' : 'is-ok';
                    const statusLabel = errors.length > 0 ? 'ត្រូវការពិនិត្យ' : 'បានបញ្ចប់';

                    tr.innerHTML = `
                        <td class="sync-table-name"></td>
                        <td class="sync-number">${formatNumber(row?.fetched)}</td>
                        <td class="sync-number">${formatNumber(row?.synced)}</td>
                        <td class="sync-number">${formatNumber(errors.length)}</td>
                        <td><span class="sync-status ${statusClass}">${statusLabel}</span></td>
                    `;

                    tr.querySelector('.sync-table-name').textContent = row?.table || 'តារាងមិនស្គាល់';
                    tableBody.appendChild(tr);

                    errors.forEach((error) => {
                        tableErrors.push(`${row?.table || 'តារាងមិនស្គាល់'}: ${errorMessage(error)}`);
                    });
                });

                totalTables.textContent = formatNumber(rows.length);
                totalFetched.textContent = formatNumber(totals.fetched);
                totalSynced.textContent = formatNumber(totals.synced);

                renderErrors(tableErrors, responseErrors);
                results.classList.add('is-visible');
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

                    if (response.ok && result.success) {
                        showMessage('success', 'សមកាលកម្មបានបញ្ចប់', result.message || 'ទិន្នន័យរបស់អ្នកគឺទាន់សម័យហើយ។');
                        renderResults(result);
                        return;
                    }

                    showMessage('error', 'សមកាលកម្មមិនទាន់បានបញ្ចប់', result.message || 'យើងមិនអាចធ្វើសមកាលកម្មទិន្នន័យបានទេ។ សូមព្យាយាមម្តងទៀត។');
                    renderResults(result);
                } catch (error) {
                    showMessage('error', 'សមកាលកម្មមិនទាន់បានបញ្ចប់', 'យើងមិនអាចភ្ជាប់ទៅសេវាសមកាលកម្មបានទេ។ សូមពិនិត្យការតភ្ជាប់អ៊ីនធឺណិតរបស់អ្នក ហើយព្យាយាមម្តងទៀត។');
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
