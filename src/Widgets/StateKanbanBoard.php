<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Widgets;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;
use Usamamuneerchaudhary\FilamentModelStates\Support\StateTransitionContext;
use Usamamuneerchaudhary\FilamentModelStates\Support\StateTransitionRunner;
use Usamamuneerchaudhary\FilamentModelStates\Support\TransitionResult;
use Usamamuneerchaudhary\FilamentModelStates\Support\TransitionResultStatus;

abstract class StateKanbanBoard extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament-model-states::widgets.state-kanban-board';

    protected int|string|array $columnSpan = 'full';

    public string $search = '';

    public ?string $columnFilter = null;

    /**
     * @var array{recordId: int|string, targetColumn: string}|null
     */
    public ?array $pendingTransition = null;

    /**
     * @var list<array{key: string, records: Collection<int, Model>, total: int, shown: int, has_more: bool}>|null
     */
    protected ?array $cachedBoardColumns = null;

    /**
     * The Eloquent model class this board manages, e.g. Order::class.
     */
    abstract protected function getModel(): string;

    /**
     * The attribute holding the state, e.g. 'status'.
     */
    abstract protected function getStateField(): string;

    /**
     * Ordered list of column keys, e.g. ['draft', 'pending', 'processing', 'completed'].
     *
     * @return list<string>
     */
    abstract protected function getColumns(): array;

    /**
     * Human label for a column key. Override for custom labels.
     */
    protected function getColumnLabel(string $key): string
    {
        return Str::headline($key);
    }

    /**
     * Color token per column. Falls back to config.
     */
    protected function getColumnColor(string $key): string
    {
        return config("filament-model-states.kanban.column_colors.{$key}", 'gray');
    }

    /**
     * @return array<string, string>
     */
    public function getColumnLabelsForView(): array
    {
        return collect($this->getColumns())
            ->mapWithKeys(fn (string $column): array => [
                $column => $this->getColumnLabel($column),
            ])
            ->all();
    }

    /**
     * Map a column key to a Spatie ModelStates class when using transitionTo().
     * Override when your column keys differ from state class names.
     *
     * @return class-string
     */
    protected function resolveStateClass(string $column): string
    {
        $modelClass = $this->getModel();

        if (method_exists($modelClass, 'getStateClassForColumn')) {
            $resolved = $modelClass::getStateClassForColumn($column);

            if (is_string($resolved) && $resolved !== '') {
                return $resolved;
            }
        }

        return $column;
    }

    /**
     * Optional transition map for models without spatie/laravel-model-states.
     * Keys are current column keys; values are allowed target column keys.
     *
     * @return array<string, list<string>>|null
     */
    protected function getAllowedTransitions(): ?array
    {
        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getTransitionMapForView(): array
    {
        $columns = $this->getColumns();
        $allowedTransitions = $this->getAllowedTransitions();

        if ($allowedTransitions !== null) {
            return collect($columns)
                ->mapWithKeys(fn (string $column): array => [
                    $column => $allowedTransitions[$column] ?? [],
                ])
                ->all();
        }

        return collect($columns)
            ->mapWithKeys(fn (string $column): array => [
                $column => array_values(array_filter(
                    $columns,
                    fn (string $target): bool => $target !== $column,
                )),
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    public function getOutgoingTransitions(string $column): array
    {
        return $this->getTransitionMapForView()[$column] ?? [];
    }

    public function getRecordsPerColumnLimit(): int
    {
        return (int) config('filament-model-states.kanban.records_per_column', 25);
    }

    public function shouldConfirmBeforeMove(): bool
    {
        return (bool) config('filament-model-states.kanban.confirm_before_move', true);
    }

    /**
     * Fields shown on each card. Override to customize.
     */
    protected function getCardTitle(Model $record): string
    {
        return (string) ($record->name ?? $record->title ?? $record->reference ?? "#{$record->getKey()}");
    }

    protected function getCardSubtitle(Model $record): ?string
    {
        return null;
    }

    /**
     * Override to scope/filter/eager-load the query.
     *
     * @return Builder<Model>
     */
    protected function getQuery(): Builder
    {
        $modelClass = $this->getModel();

        if (! is_a($modelClass, Model::class, true)) {
            throw new InvalidArgumentException("[{$modelClass}] is not an Eloquent model.");
        }

        return $modelClass::query();
    }

    /**
     * Override to apply free-text search to the board query.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query;
    }

    /**
     * @return Builder<Model>
     */
    protected function getFilteredQuery(): Builder
    {
        $query = $this->getQuery();

        if (filled($this->search)) {
            $query = $this->applySearch($query, trim($this->search));
        }

        if (filled($this->columnFilter) && in_array($this->columnFilter, $this->getColumns(), true)) {
            $query->where($this->getStateField(), $this->columnFilter);
        }

        return $query;
    }

    public function getTotalCount(): int
    {
        return $this->getFilteredQuery()->count();
    }

    /**
     * @return array<string, int>
     */
    public function getColumnTotals(): array
    {
        $field = $this->getStateField();

        return $this->getFilteredQuery()
            ->select("{$field} as column_key", DB::raw('count(*) as total'))
            ->groupBy($field)
            ->pluck('total', 'column_key')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    public function getVisibleCardsCount(): int
    {
        return collect($this->getBoardColumns())->sum('shown');
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, Collection<int, Model>>
     */
    protected function fetchRecordsGroupedByColumn(array $columns, int $limit): array
    {
        $field = $this->getStateField();
        $totals = $this->getColumnTotals();
        $totalMatchingRecords = array_sum($totals);
        $maxSingleFetch = count($columns) * $limit;

        if ($totalMatchingRecords <= $maxSingleFetch) {
            $grouped = $this->getFilteredQuery()
                ->whereIn($field, $columns)
                ->orderByDesc('updated_at')
                ->get()
                ->groupBy(fn (Model $record): string => $this->resolveColumnKey($record));

            return collect($columns)
                ->mapWithKeys(fn (string $column): array => [
                    $column => ($grouped->get($column) ?? collect())->take($limit)->values(),
                ])
                ->all();
        }

        return collect($columns)
            ->mapWithKeys(function (string $column) use ($field, $limit): array {
                return [
                    $column => $this->getFilteredQuery()
                        ->where($field, $column)
                        ->latest('updated_at')
                        ->limit($limit)
                        ->get(),
                ];
            })
            ->all();
    }

    /**
     * @return list<array{key: string, records: Collection<int, Model>, total: int, shown: int, has_more: bool}>
     */
    public function getBoardColumns(): array
    {
        if ($this->cachedBoardColumns !== null) {
            return $this->cachedBoardColumns;
        }

        $columns = $this->getColumns();
        $totals = $this->getColumnTotals();
        $limit = $this->getRecordsPerColumnLimit();
        $recordsByColumn = $this->fetchRecordsGroupedByColumn($columns, $limit);

        return $this->cachedBoardColumns = collect($columns)
            ->map(function (string $column) use ($totals, $recordsByColumn): array {
                $records = $recordsByColumn[$column];
                $total = (int) ($totals[$column] ?? 0);

                return [
                    'key' => $column,
                    'records' => $records,
                    'total' => $total,
                    'shown' => $records->count(),
                    'has_more' => $total > $records->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<string, Collection<int, Model>>
     */
    public function getRecordsByColumn(): Collection
    {
        return collect($this->getBoardColumns())->mapWithKeys(
            fn (array $column): array => [$column['key'] => $column['records']],
        );
    }

    /**
     * Map a source column and target column to a gate ability.
     * Keys use "from->to", for example "pending->approved".
     *
     * @return array<string, string>
     */
    protected function getTransitionAbilities(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function allowedTargetsForRecord(Model $record): array
    {
        $sourceColumn = $this->resolveColumnKey($record);

        return app(StateTransitionRunner::class)->filterAbilities(
            $record,
            $sourceColumn,
            $this->getOutgoingTransitions($sourceColumn),
            $this->getTransitionAbilities(),
        );
    }

    /**
     * Called from the frontend when a card is dropped on a new column.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function moveRecord(int|string $recordId, string $targetColumn, array $arguments = []): void
    {
        if (! in_array($targetColumn, $this->getColumns(), true)) {
            $this->rejectMove(null, $targetColumn, 'Unknown target column.');

            return;
        }

        $sourceColumn = null;
        $moved = false;
        $awaitingInput = false;

        try {
            DB::transaction(function () use ($recordId, $targetColumn, $arguments, &$sourceColumn, &$moved, &$awaitingInput): void {
                /** @var Model $record */
                $record = $this->getQuery()->lockForUpdate()->findOrFail($recordId);

                $this->authorizeMoveRecord($record);

                $currentState = $record->{$this->getStateField()};
                $result = app(StateTransitionRunner::class)->evaluate(
                    $this->makeTransitionContext($record, $targetColumn, $arguments),
                );

                $sourceColumn = $result->sourceColumn ?? $this->resolveColumnKey($record);

                if ($result->status === TransitionResultStatus::Unchanged) {
                    return;
                }

                if (! $this->stateUsesConfig($currentState)) {
                    if (! $this->canTransitionTo($currentState, $sourceColumn, $targetColumn)) {
                        $this->rejectMove($currentState, $targetColumn);

                        return;
                    }

                    if ($result->status === TransitionResultStatus::Rejected) {
                        $result = new TransitionResult(
                            status: TransitionResultStatus::Ready,
                            sourceColumn: $sourceColumn,
                            targetColumn: $targetColumn,
                        );
                    }
                } elseif ($result->status === TransitionResultStatus::Rejected) {
                    $this->rejectMove($currentState, $targetColumn, $result->message);

                    return;
                }

                if ($result->status === TransitionResultStatus::NeedsInput) {
                    if ($arguments !== [] || ! config('filament-model-states.kanban.prompt_for_transition_arguments', true)) {
                        $this->rejectMove($currentState, $targetColumn, 'This transition needs additional input.');

                        return;
                    }

                    $key = $record->getKey();
                    $this->pendingTransition = [
                        'recordId' => is_int($key) || is_string($key) ? $key : (string) $key,
                        'targetColumn' => $targetColumn,
                    ];
                    $awaitingInput = true;

                    return;
                }

                if ($result->positionalArguments === []) {
                    $this->persistStateChange($record, $currentState, $targetColumn);
                } else {
                    $this->persistStateChange($record, $currentState, $targetColumn, $result->positionalArguments);
                }

                $moved = true;
            });
        } catch (Throwable $exception) {
            if ($this->shouldRejectMoveException($exception)) {
                $this->rejectMove(null, $targetColumn, $exception->getMessage());

                return;
            }

            throw $exception;
        }

        if ($awaitingInput) {
            $this->openTransitionModal();
            $this->dispatch('kanban-move-needs-input');

            return;
        }

        if (! $moved || $sourceColumn === null) {
            return;
        }

        Notification::make()
            ->title('Status updated')
            ->body("Moved to \"{$this->getColumnLabel($targetColumn)}\".")
            ->success()
            ->send();

        $this->dispatch(
            'kanban-move-accepted',
            sourceColumn: $sourceColumn,
            targetColumn: $targetColumn,
        );
    }

    public function provideTransitionArgumentsAction(): Action
    {
        return Action::make('provideTransitionArguments')
            ->modalHeading('Complete transition')
            ->modalSubmitActionLabel('Apply transition')
            ->schema(function (): array {
                $pending = $this->pendingTransition;

                if ($pending === null) {
                    return [];
                }

                $record = $this->getQuery()->find($pending['recordId']);

                if (! $record instanceof Model) {
                    return [];
                }

                return app(StateTransitionRunner::class)
                    ->evaluate($this->makeTransitionContext($record, $pending['targetColumn']))
                    ->schema;
            })
            ->action(function (array $data): void {
                $pending = $this->pendingTransition;

                if ($pending === null) {
                    return;
                }

                $this->pendingTransition = null;
                $this->moveRecord($pending['recordId'], $pending['targetColumn'], $data);
            });
    }

    protected function authorizeMoveRecord(Model $record): void
    {
        Gate::authorize('update', $record);
    }

    protected function stateUsesConfig(mixed $currentState): bool
    {
        return is_object($currentState) && method_exists($currentState, 'config');
    }

    protected function canTransitionTo(mixed $currentState, string $currentColumn, string $targetColumn): bool
    {
        if (is_object($currentState) && method_exists($currentState, 'canTransitionTo')) {
            return $currentState->canTransitionTo($this->resolveStateClass($targetColumn));
        }

        $allowedTransitions = $this->getAllowedTransitions();

        if ($allowedTransitions === null) {
            return true;
        }

        return in_array($targetColumn, $allowedTransitions[$currentColumn] ?? [], true);
    }

    /**
     * @param  list<mixed>  $transitionArguments
     */
    protected function persistStateChange(Model $record, mixed $currentState, string $targetColumn, array $transitionArguments = []): void
    {
        app(StateTransitionRunner::class)->persist(
            $this->makeTransitionContext($record, $targetColumn),
            new TransitionResult(
                status: TransitionResultStatus::Ready,
                positionalArguments: $transitionArguments,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function makeTransitionContext(Model $record, string $targetColumn, array $arguments = []): StateTransitionContext
    {
        $resolved = $this->resolveStateClass($targetColumn);

        return new StateTransitionContext(
            record: $record,
            stateField: $this->getStateField(),
            targetColumn: $targetColumn,
            arguments: $arguments,
            allowedTransitions: $this->getAllowedTransitions(),
            targetStateClass: class_exists($resolved) ? $resolved : null,
            abilities: $this->getTransitionAbilities(),
            authorizeUpdate: false,
            statusReason: 'Moved via Kanban board',
        );
    }

    protected function openTransitionModal(): void
    {
        $id = $this->getId();

        if (! is_string($id) || $id === '') {
            return;
        }

        $this->mountAction('provideTransitionArguments');
    }

    protected function resolveStateValue(string $column): mixed
    {
        $modelClass = $this->getModel();
        $field = $this->getStateField();
        $casts = (new $modelClass)->getCasts();
        $cast = $casts[$field] ?? null;

        if (is_string($cast) && enum_exists($cast) && is_subclass_of($cast, BackedEnum::class)) {
            /** @var class-string<BackedEnum> $cast */
            return $cast::from($column);
        }

        return $column;
    }

    protected function resolveColumnKey(Model $record): string
    {
        return $this->normalizeStateToColumnKey($record->{$this->getStateField()});
    }

    protected function normalizeStateToColumnKey(mixed $state): string
    {
        return app(StateTransitionRunner::class)->columnKey($state);
    }

    protected function formatStateLabel(mixed $state): string
    {
        if ($state === null) {
            return 'unknown';
        }

        return $this->getColumnLabel($this->normalizeStateToColumnKey($state));
    }

    protected function rejectMove(mixed $currentState, string $targetColumn, ?string $reason = null): void
    {
        if (! config('filament-model-states.kanban.notify_on_invalid_transition', true)) {
            $this->dispatch('kanban-move-rejected');

            return;
        }

        Notification::make()
            ->title('Move not allowed')
            ->body($reason ?? "Can't move from \"{$this->formatStateLabel($currentState)}\" to \"{$this->getColumnLabel($targetColumn)}\" directly.")
            ->danger()
            ->send();

        $this->dispatch('kanban-move-rejected');
    }

    protected function shouldRejectMoveException(Throwable $exception): bool
    {
        return ! ($exception instanceof AuthorizationException);
    }
}
