@php
    $boardColumns = $this->getBoardColumns();
    $transitions = $this->getTransitionMapForView();
    $columnLabels = $this->getColumnLabelsForView();
    $totalCount = $this->getTotalCount();
    $visibleCount = $this->getVisibleCardsCount();
    $recordsPerColumn = $this->getRecordsPerColumnLimit();
@endphp

<x-filament-widgets::widget>
    @filamentStyles(['filament-model-states'])

    <x-filament::section heading="Workflow board">
        <div
            x-data="kanbanBoard({
                transitions: @js($transitions),
                labels: @js($columnLabels),
                confirmBeforeMove: @js($this->shouldConfirmBeforeMove()),
            })"
            x-on:kanban-move-accepted.window="handleMoveAccepted($event.detail)"
            x-on:kanban-move-rejected.window="handleMoveRejected()"
        >
            <div class="fi-model-states-kanban__toolbar">
                <div class="fi-model-states-kanban__search">
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                        <x-filament::input
                            wire:model.live.debounce.500ms="search"
                            placeholder="Search records..."
                        />
                    </x-filament::input.wrapper>
                </div>

                <div class="fi-model-states-kanban__filter">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="columnFilter">
                            <option value="">All states</option>
                            @foreach ($this->getColumns() as $columnKey)
                                <option value="{{ $columnKey }}">{{ $this->getColumnLabel($columnKey) }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <p class="fi-model-states-kanban__stats">
                    Showing <strong>{{ number_format($visibleCount) }}</strong>
                    of <strong>{{ number_format($totalCount) }}</strong> records
                    @if ($recordsPerColumn < $totalCount)
                        <span class="fi-model-states-kanban__stats-note">(up to {{ $recordsPerColumn }} per column)</span>
                    @endif
                </p>
            </div>

            <div
                class="fi-model-states-kanban"
                style="grid-template-columns: repeat({{ count($boardColumns) }}, minmax(15rem, 1fr));"
            >
                @foreach ($boardColumns as $column)
                    @php
                        $columnKey = $column['key'];
                        $records = $column['records'];
                        $color = $this->getColumnColor($columnKey);
                        $outgoing = $this->getOutgoingTransitions($columnKey);
                    @endphp

                    <div class="fi-model-states-kanban__column" data-column="{{ $columnKey }}">
                        <div class="fi-model-states-kanban__column-header">
                            <span class="fi-model-states-kanban__column-title">
                                {{ $this->getColumnLabel($columnKey) }}
                            </span>
                            <x-filament::badge :color="$color">
                                <span data-count-column="{{ $columnKey }}">
                                    {{ $column['shown'] }}@if ($column['has_more']) / {{ $column['total'] }}@endif
                                </span>
                            </x-filament::badge>
                        </div>

                        @if (count($outgoing))
                            <div class="fi-model-states-kanban__transitions" title="Allowed transitions from {{ $this->getColumnLabel($columnKey) }}">
                                <span class="fi-model-states-kanban__transitions-label">Can move to</span>
                                @foreach ($outgoing as $target)
                                    <span class="fi-model-states-kanban__transition-chip">
                                        {{ $this->getColumnLabel($target) }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="fi-model-states-kanban__terminal-note">Terminal state</p>
                        @endif

                        <div
                            class="fi-model-states-kanban__column-body fi-model-states-column"
                            data-column="{{ $columnKey }}"
                        >
                            @forelse ($records as $record)
                                <div
                                    data-record-id="{{ $record->getKey() }}"
                                    class="fi-model-states-kanban__card fi-model-states-card"
                                >
                                    <p class="fi-model-states-kanban__card-title">
                                        {{ $this->getCardTitle($record) }}
                                    </p>
                                    @if ($subtitle = $this->getCardSubtitle($record))
                                        <p class="fi-model-states-kanban__card-subtitle">
                                            {{ $subtitle }}
                                        </p>
                                    @endif
                                </div>
                            @empty
                                <p class="fi-model-states-kanban__empty-column">No matching records</p>
                            @endforelse
                        </div>

                        @if ($column['has_more'])
                            <p class="fi-model-states-kanban__more-note">
                                +{{ number_format($column['total'] - $column['shown']) }} more — refine search to find them
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>

            <template x-teleport="body">
                <div
                    x-show="showConfirm"
                    x-cloak
                    x-transition.opacity
                    class="fi-model-states-kanban__confirm-overlay"
                    role="dialog"
                    aria-modal="true"
                    x-on:keydown.escape.window="cancelMove()"
                >
                    <div
                        class="fi-model-states-kanban__confirm-dialog"
                        x-on:click.outside="cancelMove()"
                    >
                        <h3 class="fi-model-states-kanban__confirm-title">Confirm state change</h3>
                        <p class="fi-model-states-kanban__confirm-body" x-show="pendingMove">
                            Move
                            <strong x-text="pendingMove ? pendingMove.cardTitle : ''"></strong>
                            from
                            <strong x-text="pendingMove ? pendingMove.sourceLabel : ''"></strong>
                            to
                            <strong x-text="pendingMove ? pendingMove.targetLabel : ''"></strong>?
                        </p>
                        <div class="fi-model-states-kanban__confirm-actions">
                            <x-filament::button color="gray" tag="button" type="button" x-on:click="cancelMove()">
                                Cancel
                            </x-filament::button>
                            <x-filament::button tag="button" type="button" x-on:click="confirmMove()">
                                Confirm move
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </x-filament::section>

    @filamentScripts(['filament-model-states'])
</x-filament-widgets::widget>
