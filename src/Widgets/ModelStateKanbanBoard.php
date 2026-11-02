<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Widgets;

use Filament\Widgets\WidgetConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Usamamuneerchaudhary\FilamentModelStates\Support\StateConfiguration;
use Usamamuneerchaudhary\FilamentModelStates\Support\StateConfigurationResolver;

/**
 * Use:
 * ModelStateKanbanBoard::for(Order::class)
 */
class ModelStateKanbanBoard extends StateKanbanBoard
{
    public string $modelClass;

    public ?string $stateField = null;

    protected ?StateConfiguration $resolvedConfiguration = null;

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function for(string $modelClass, ?string $stateField = null): WidgetConfiguration
    {
        return static::make([
            'modelClass' => $modelClass,
            'stateField' => $stateField,
        ]);
    }

    protected function configuration(): StateConfiguration
    {
        return $this->resolvedConfiguration ??= app(StateConfigurationResolver::class)
            ->resolve($this->modelClass, $this->stateField);
    }

    protected function getModel(): string
    {
        return $this->configuration()->modelClass;
    }

    protected function getStateField(): string
    {
        return $this->configuration()->stateField;
    }

    /**
     * @return list<string>
     */
    protected function getColumns(): array
    {
        return $this->configuration()->columns;
    }

    protected function getAllowedTransitions(): ?array
    {
        return $this->configuration()->transitions;
    }

    protected function getColumnLabel(string $key): string
    {
        return $this->configuration()->labelFor($key);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applySearch(Builder $query, string $search): Builder
    {
        return $this->configuration()->applySearch($query, $search);
    }

    protected function resolveStateClass(string $column): string
    {
        $stateClassMap = $this->configuration()->stateClassMap;

        if (is_array($stateClassMap) && isset($stateClassMap[$column])) {
            return $stateClassMap[$column];
        }

        return parent::resolveStateClass($column);
    }
}
