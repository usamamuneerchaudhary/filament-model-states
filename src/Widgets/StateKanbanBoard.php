<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Widgets;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

abstract class StateKanbanBoard extends Widget
{
    protected string $view = 'filament-model-states::widgets.state-kanban-board';

    protected int | string | array $columnSpan = 'full';

    public string $search = '';

    public ?string $columnFilter = null;

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
        $allowedTransitions = $this->getAllowedTransitions();

        if ($allowedTransitions !== null) {
            return $allowedTransitions;
        }

        $columns = $this->getColumns();

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
     */
    protected function getQuery(): Builder
    {
        return $this->getModel()::query();
    }

    /**
     * Override to apply free-text search to the board query.
     */
    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query;
    }

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
     * @return list<array{key: string, records: Collection<int, Model>, total: int, shown: int, has_more: bool}>
     */
    public function getBoardColumns(): array
    {
        $columns = $this->getColumns();
        $totals = $this->getColumnTotals();
        $limit = $this->getRecordsPerColumnLimit();
        $field = $this->getStateField();

        return collect($columns)
            ->map(function (string $column) use ($totals, $limit, $field): array {
                $total = (int) ($totals[$column] ?? 0);

                $records = $this->getFilteredQuery()
                    ->where($field, $column)
                    ->latest('updated_at')
                    ->limit($limit)
                    ->get();

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
     * Called from the frontend when a card is dropped on a new column.
     */
    public function moveRecord(int | string $recordId, string $targetColumn): void
    {
        if (! in_array($targetColumn, $this->getColumns(), true)) {
            $this->rejectMove(null, $targetColumn, 'Unknown target column.');

            return;
        }

        $sourceColumn = null;
        $moved = false;

        try {
            DB::transaction(function () use ($recordId, $targetColumn, &$sourceColumn, &$moved): void {
                /** @var Model $record */
                $record = $this->getModel()::query()->lockForUpdate()->findOrFail($recordId);

                $this->authorizeMoveRecord($record);

                $currentState = $record->{$this->getStateField()};
                $sourceColumn = $this->resolveColumnKey($record);

                if ($sourceColumn === $targetColumn) {
                    return;
                }

                if (! $this->canTransitionTo($currentState, $sourceColumn, $targetColumn)) {
                    $this->rejectMove($currentState, $targetColumn);

                    return;
                }

                $this->persistStateChange($record, $currentState, $targetColumn);
                $moved = true;
            });
        } catch (Throwable $e) {
            if ($this->shouldRejectMoveException($e)) {
                $this->rejectMove(null, $targetColumn, $e->getMessage());

                return;
            }

            throw $e;
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

    protected function authorizeMoveRecord(Model $record): void
    {
        Gate::authorize('update', $record);
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

    protected function persistStateChange(Model $record, mixed $currentState, string $targetColumn): void
    {
        $field = $this->getStateField();
        $targetState = $this->resolveStateClass($targetColumn);

        if (is_object($currentState) && method_exists($currentState, 'transitionTo')) {
            $currentState->transitionTo($targetState);
        } else {
            $record->{$field} = $this->resolveStateValue($targetColumn);
            $record->save();
        }

        if (method_exists($record, 'setStatus')) {
            $record->setStatus($targetColumn, 'Moved via Kanban board');
        }
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
        if ($state instanceof BackedEnum) {
            return (string) $state->value;
        }

        if (is_object($state)) {
            if (method_exists($state, 'getValue')) {
                return (string) $state->getValue();
            }

            if (property_exists($state, 'name')) {
                return (string) $state->name;
            }

            $classBaseName = class_basename($state);

            return Str::snake($classBaseName);
        }

        return (string) $state;
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
        return ! ($exception instanceof \Illuminate\Auth\Access\AuthorizationException);
    }
}
