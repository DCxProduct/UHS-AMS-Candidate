@php($stageCount = count($stages))

<div class="uhs-workflow-summary">
    @if ($stageCount > 0)
        <p class="uhs-workflow-summary__count">
            {{ __('workflow_notifications.view.stage_count', ['count' => $stageCount]) }}
        </p>

        <div class="uhs-workflow-summary__list">
            @foreach ($stages as $index => $stage)
                <article class="uhs-workflow-stage-card">
                    <div class="uhs-workflow-stage-card__number">
                        {{ $index + 1 }}
                    </div>

                    <div class="uhs-workflow-stage-card__content">
                        <h3 class="uhs-workflow-stage-card__title">
                            {{ $stage['name'] ?: '—' }}
                        </h3>

                        <div class="uhs-workflow-stage-card__badges">
                            <span class="uhs-workflow-stage-card__badge uhs-workflow-stage-card__badge--type">
                                {{ $stage['type_label'] ?: '—' }}
                            </span>
                            <span class="uhs-workflow-stage-card__badge uhs-workflow-stage-card__badge--responsible">
                                {{ __('workflow_notifications.view.responsible', ['name' => $stage['responsible_label'] ?: '—']) }}
                            </span>
                        </div>

                        @if (filled($stage['status_message']))
                            <p class="uhs-workflow-stage-card__message">
                                <span class="uhs-workflow-stage-card__message-label">{{ __('workflow_notifications.fields.status_message') }}:</span>
                                {{ $stage['status_message'] }}
                            </p>
                        @endif

                        @if (filled($stage['notification_message']))
                            <p class="uhs-workflow-stage-card__message">
                                <span class="uhs-workflow-stage-card__message-label">{{ __('workflow_notifications.fields.notification_message') }}:</span>
                                {{ $stage['notification_message'] }}
                            </p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <p class="uhs-workflow-summary__empty">
            {{ __('workflow_notifications.view.no_stages') }}
        </p>
    @endif
</div>
