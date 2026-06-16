<x-filament-widgets::widget>
    <style>
        .student-forms-grid {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 1rem;
        }

        .student-form-card {
            position: relative;
            display: flex;
            min-height: 220px;
            flex-direction: column;
            overflow: hidden;
            padding: 1.25rem;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            background: #ffffff;
            color: #111827;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                border-color 0.2s ease;
        }

        .student-form-card:hover {
            transform: translateY(-4px);
            border-color: #3b82f6;
            box-shadow: 0 18px 35px rgba(15, 23, 42, 0.12);
        }

        .student-form-card__decoration {
            position: absolute;
            top: -45px;
            right: -45px;
            width: 140px;
            height: 140px;
            border-radius: 9999px;
            filter: blur(42px);
            pointer-events: none;
        }

        .student-form-card__decoration.is-open {
            background: rgba(59, 130, 246, 0.20);
        }

        .student-form-card__decoration.is-completed {
            background: rgba(16, 185, 129, 0.20);
        }

        .student-form-card__decoration.is-expired {
            background: rgba(239, 68, 68, 0.20);
        }

        .student-form-card__header {
            position: relative;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .student-form-card__icon {
            display: inline-flex;
            width: 56px;
            height: 56px;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            border: 1px solid;
            border-radius: 16px;
        }

        .student-form-card__icon.is-open {
            border-color: #bfdbfe;
            background: #eff6ff;
            color: #2563eb;
        }

        .student-form-card__icon.is-completed {
            border-color: #a7f3d0;
            background: #ecfdf5;
            color: #059669;
        }

        .student-form-card__icon.is-expired {
            border-color: #fecaca;
            background: #fef2f2;
            color: #dc2626;
        }

        .student-form-card__badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 11px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.4;
            white-space: nowrap;
        }

        .student-form-card__badge.is-open {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .student-form-card__badge.is-completed {
            background: #ecfdf5;
            color: #047857;
        }

        .student-form-card__badge.is-expired {
            background: #fef2f2;
            color: #b91c1c;
        }

        .student-form-card__badge-dot {
            width: 8px;
            height: 8px;
            border-radius: 9999px;
        }

        .student-form-card__badge-dot.is-open {
            background: #3b82f6;
        }

        .student-form-card__badge-dot.is-completed {
            background: #10b981;
        }

        .student-form-card__badge-dot.is-expired {
            background: #ef4444;
        }

        .student-form-card__content {
            position: relative;
            flex: 1;
            margin-top: 1.25rem;
        }

        .student-form-card__title {
            margin: 0;
            color: #111827;
            font-size: 18px;
            font-weight: 800;
            line-height: 1.5;
        }

        .student-form-card__description {
            margin: 8px 0 0;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.75;
        }

        .student-form-card__footer {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 1.25rem;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
        }

        .student-form-card__footer.is-open {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .student-form-card__footer.is-completed {
            background: #ecfdf5;
            color: #047857;
        }

        .student-form-card__footer.is-expired {
            background: #fef2f2;
            color: #b91c1c;
        }

        .student-form-card__arrow {
            transition: transform 0.2s ease;
        }

        .student-form-card:hover .student-form-card__arrow {
            transform: translateX(5px);
        }

        .student-forms-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 1.5rem;
            border: 1px dashed #d1d5db;
            border-radius: 18px;
            background: #f9fafb;
            color: #6b7280;
            text-align: center;
        }

        .student-forms-empty__icon {
            display: flex;
            width: 64px;
            height: 64px;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            background: #f3f4f6;
            color: #9ca3af;
        }

        .student-forms-empty__title {
            margin: 14px 0 0;
            color: #111827;
            font-size: 16px;
            font-weight: 800;
        }

        .student-forms-empty__description {
            max-width: 480px;
            margin: 7px 0 0;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
        }

        @media (min-width: 768px) {
            .student-forms-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1280px) {
            .student-forms-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        html.dark .student-form-card,
        .dark .student-form-card {
            border-color: rgba(255, 255, 255, 0.11);
            background: #18181b;
            color: #f4f4f5;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.28);
        }

        html.dark .student-form-card:hover,
        .dark .student-form-card:hover {
            border-color: #3b82f6;
            background: #202023;
            box-shadow: 0 20px 44px rgba(0, 0, 0, 0.38);
        }

        html.dark .student-form-card__title,
        .dark .student-form-card__title {
            color: #f4f4f5;
        }

        html.dark .student-form-card__description,
        .dark .student-form-card__description {
            color: #a1a1aa;
        }

        html.dark .student-form-card__icon.is-open,
        .dark .student-form-card__icon.is-open {
            border-color: rgba(96, 165, 250, 0.25);
            background: rgba(37, 99, 235, 0.14);
            color: #60a5fa;
        }

        html.dark .student-form-card__icon.is-completed,
        .dark .student-form-card__icon.is-completed {
            border-color: rgba(52, 211, 153, 0.25);
            background: rgba(16, 185, 129, 0.14);
            color: #34d399;
        }

        html.dark .student-form-card__icon.is-expired,
        .dark .student-form-card__icon.is-expired {
            border-color: rgba(248, 113, 113, 0.25);
            background: rgba(239, 68, 68, 0.14);
            color: #f87171;
        }

        html.dark .student-form-card__badge.is-open,
        html.dark .student-form-card__footer.is-open,
        .dark .student-form-card__badge.is-open,
        .dark .student-form-card__footer.is-open {
            background: rgba(37, 99, 235, 0.14);
            color: #60a5fa;
        }

        html.dark .student-form-card__badge.is-completed,
        html.dark .student-form-card__footer.is-completed,
        .dark .student-form-card__badge.is-completed,
        .dark .student-form-card__footer.is-completed {
            background: rgba(16, 185, 129, 0.14);
            color: #34d399;
        }

        html.dark .student-form-card__badge.is-expired,
        html.dark .student-form-card__footer.is-expired,
        .dark .student-form-card__badge.is-expired,
        .dark .student-form-card__footer.is-expired {
            background: rgba(239, 68, 68, 0.14);
            color: #f87171;
        }

        html.dark .student-forms-empty,
        .dark .student-forms-empty {
            border-color: rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.025);
            color: #a1a1aa;
        }

        html.dark .student-forms-empty__icon,
        .dark .student-forms-empty__icon {
            background: rgba(255, 255, 255, 0.05);
            color: #71717a;
        }

        html.dark .student-forms-empty__title,
        .dark .student-forms-empty__title {
            color: #f4f4f5;
        }

        html.dark .student-forms-empty__description,
        .dark .student-forms-empty__description {
            color: #a1a1aa;
        }
    </style>

    <x-filament::section>
        <x-slot name="heading">
            {{ __('dashboard.quick_actions') }}
        </x-slot>

        <x-slot name="description">
            {{ __('dashboard.quick_actions_description') }}
        </x-slot>

        @if (count($actions) > 0)
            <div class="student-forms-grid">
                @foreach ($actions as $action)
                    @php
                        $isCompleted = (bool) ($action['completed'] ?? false);
                        $isExpired = (bool) ($action['expired'] ?? false);

                        $stateClass = $isCompleted
                            ? 'is-completed'
                            : ($isExpired ? 'is-expired' : 'is-open');

                        $statusLabel = $isCompleted
                            ? __('dashboard.completed')
                            : ($isExpired
                                ? __('dashboard.expired_contact')
                                : __('dashboard.open_form'));

                        $description = $isCompleted
                            ? __('dashboard.form_completed_description')
                            : ($isExpired
                                ? __('dashboard.form_expired_description')
                                : __('dashboard.form_open_description'));
                    @endphp

                    <a
                        href="{{ $action['url'] }}"
                        aria-label="{{ $action['name'] }}"
                        class="student-form-card"
                    >
                        <div class="student-form-card__decoration {{ $stateClass }}"></div>

                        <div class="student-form-card__header">
                            <div class="student-form-card__icon {{ $stateClass }}">
                                <x-filament::icon
                                    :icon="$action['icon']"
                                    style="width: 28px; height: 28px;"
                                />
                            </div>

                            <span class="student-form-card__badge {{ $stateClass }}">
                                <span
                                    class="student-form-card__badge-dot {{ $stateClass }}"
                                ></span>

                                {{ $statusLabel }}
                            </span>
                        </div>

                        <div class="student-form-card__content">
                            <h3 class="student-form-card__title">
                                {{ $action['name'] }}
                            </h3>

                            <p class="student-form-card__description">
                                {{ $description }}
                            </p>
                        </div>

                        <div class="student-form-card__footer {{ $stateClass }}">
                            <span>{{ $statusLabel }}</span>

                            <x-filament::icon
                                icon="heroicon-m-arrow-right"
                                class="student-form-card__arrow"
                                style="width: 20px; height: 20px;"
                            />
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="student-forms-empty">
                <div class="student-forms-empty__icon">
                    <x-filament::icon
                        icon="heroicon-o-document-text"
                        style="width: 32px; height: 32px;"
                    />
                </div>

                <h3 class="student-forms-empty__title">
                    {{ __('dashboard.no_available_forms') }}
                </h3>

                <p class="student-forms-empty__description">
                    {{ __('dashboard.no_available_forms_description') }}
                </p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
