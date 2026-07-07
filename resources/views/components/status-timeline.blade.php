@php
    $items = $getTimelineData();
    $showReason = $getShowReason();
@endphp

@filamentStyles(['filament-model-states'])

<div class="fi-model-states-timeline">
    @if ($items->isEmpty())
        <div class="fi-in-placeholder text-sm text-gray-500 dark:text-gray-400">
            {{ $getEmptyStateLabel() }}
        </div>
    @else
        <ol class="fi-model-states-timeline__list">
            @foreach ($items as $item)
                <li class="fi-model-states-timeline__item">
                    <span @class([
                        'fi-model-states-timeline__dot',
                        'fi-model-states-timeline__dot--current' => $item['is_current'],
                    ])></span>

                    <div class="fi-model-states-timeline__meta">
                        <x-filament::badge :color="$item['is_current'] ? 'primary' : 'gray'">
                            {{ ucfirst(str_replace(['_', '-'], ' ', $item['name'])) }}
                        </x-filament::badge>

                        @if ($item['is_current'])
                            <span class="fi-model-states-timeline__current-label">current</span>
                        @endif

                        <time class="fi-model-states-timeline__time">
                            {{ $item['started_at']->format(config('filament-model-states.timeline.date_format')) }}
                        </time>
                    </div>

                    @if ($showReason && $item['reason'])
                        <p class="fi-model-states-timeline__reason">
                            {{ $item['reason'] }}
                        </p>
                    @endif

                    <p class="fi-model-states-timeline__duration">
                        {{ $item['is_current'] ? 'In this state for' : 'Stayed in this state for' }}
                        {{ $item['duration'] }}
                    </p>
                </li>
            @endforeach
        </ol>
    @endif
</div>
