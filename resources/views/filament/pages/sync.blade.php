<x-filament-panels::page>
    <style>
        .sync-page {
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #f8fafc;
        }

        .sync-card {
            width: 100%;
            max-width: 520px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            padding: 32px;
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
    </style>

    <div
        class="sync-page"
        data-sync-page
        data-auto-run="{{ request()->boolean('run') ? 'true' : 'false' }}"
        data-sync-url="{{ route('sync.run') }}"
        data-csrf-token="{{ csrf_token() }}"
    >
        <div class="sync-card">
            <div class="sync-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="32" height="32">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992M2.985 19.644v-4.992m0 0h4.992m-4.992 0 3.181-3.183a8.25 8.25 0 0 1 13.803 3.183m0 0a8.25 8.25 0 0 1-13.803 3.183" />
                </svg>
            </div>

            <h2 class="sync-title">Synchronize data</h2>
            <p class="sync-text">Update the local database with the latest server data.</p>

            <button type="button" class="sync-button" data-sync-button>
                <svg class="sync-button-icon" data-sync-button-icon xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.9" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992M2.985 19.644v-4.992m0 0h4.992m-4.992 0 3.181-3.183a8.25 8.25 0 0 1 13.803 3.183m0 0a8.25 8.25 0 0 1-13.803 3.183" />
                </svg>
                <span data-sync-button-label>Sync Now</span>
            </button>

            <div class="sync-alert" data-sync-alert role="status" aria-live="polite">
                <strong class="sync-alert-title" data-sync-alert-title></strong>
                <span data-sync-alert-message></span>
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

            let isSyncing = false;

            const setLoading = (loading) => {
                isSyncing = loading;
                button.disabled = loading;
                icon.classList.toggle('is-spinning', loading);
                label.textContent = loading ? 'Syncing...' : 'Sync Now';
            };

            const showMessage = (type, title, message) => {
                alertBox.className = `sync-alert is-visible is-${type}`;
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
                        showMessage('success', 'Sync complete', result.message || 'Your data is now up to date.');
                        return;
                    }

                    showMessage('error', 'Sync did not finish', result.message || 'We could not sync the data. Please try again.');
                } catch (error) {
                    showMessage('error', 'Sync did not finish', 'We could not connect to the sync service. Please check your internet connection and try again.');
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
