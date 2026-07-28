<x-filament-panels::page>
    @php
        $adminStats = $this->isAdmin() ? $this->getAdminStats() : [];
        $studentStats = $this->isStudent() ? $this->getStudentStats() : [];
    @endphp

    @if ($this->isAdmin())
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Pending</div>
                <div class="mt-2 text-3xl font-black text-yellow-600">{{ $adminStats['pending'] }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Accepted</div>
                <div class="mt-2 text-3xl font-black text-green-600">{{ $adminStats['accepted'] }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Rejected</div>
                <div class="mt-2 text-3xl font-black text-red-600">{{ $adminStats['rejected'] }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">Total</div>
                <div class="mt-2 text-3xl font-black text-blue-600">{{ $adminStats['total'] }}</div>
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h2 class="text-xl font-black text-gray-950 dark:text-white">
                {{ __('review_applications.navigation_label') }}
            </h2>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Review student enrollment applications and accept or reject documents.
            </p>

            <a
                href="{{ url('/review-applications') }}"
                class="mt-5 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700"
            >
                {{ __('review_applications.navigation_label') }}
            </a>
        </div>
    @endif

    @if ($this->isStudent())
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <h2 class="text-xl font-black text-gray-950 dark:text-white">
                    {{ __('app.forms_nav.profile') }}
                </h2>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Complete your profile before continuing to enrollment.
                </p>

                <div class="mt-4">
                    @if ($studentStats['profile_completed'])
                        <span class="rounded-full bg-green-100 px-3 py-1 text-sm font-bold text-green-700">
                            Completed
                        </span>
                    @else
                        <span class="rounded-full bg-yellow-100 px-3 py-1 text-sm font-bold text-yellow-700">
                            Pending
                        </span>
                    @endif
                </div>

                <a
                    href="{{ $this->getProfileUrl() }}"
                    class="mt-5 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700"
                >
                    Open Profile
                </a>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <h2 class="text-xl font-black text-gray-950 dark:text-white">
                    {{ __('app.forms_nav.enrollment') }}
                </h2>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Submit enrollment after your profile is completed.
                </p>

                <div class="mt-4">
                    <span class="rounded-full bg-blue-100 px-3 py-1 text-sm font-bold text-blue-700">
                        {{ __('review_applications.statuses.' . $studentStats['enrollment_status']) }}
                    </span>
                </div>

                @if ($studentStats['profile_completed'])
                    <a
                        href="{{ $this->getEnrollmentUrl() }}"
                        class="mt-5 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700"
                    >
                        Open Enrollment
                    </a>
                @else
                    <div class="mt-5 text-sm font-bold text-yellow-600">
                        Please complete profile first.
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if (! $this->hasDashboardWidgets())
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                {{ __('dashboard.no_tracking_title') }}
            </h2>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('dashboard.no_tracking_description') }}
            </p>
        </div>
    @endif
</x-filament-panels::page>
