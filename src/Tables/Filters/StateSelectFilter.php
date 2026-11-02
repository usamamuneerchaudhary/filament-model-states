<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tables\Filters;

use Closure;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Model;
use Usamamuneerchaudhary\FilamentModelStates\Support\StateConfigurationResolver;

class StateSelectFilter extends SelectFilter
{
    protected mixed $stateFieldName = null;

    public function stateField(string|Closure $field): static
    {
        $this->stateFieldName = $field;
        $this->attribute($field);

        return $this;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, string>
     */
    public static function optionsFor(string $modelClass, ?string $stateField = null): array
    {
        $configuration = app(StateConfigurationResolver::class)->resolve($modelClass, $stateField);
        $options = [];

        foreach ($configuration->columns as $column) {
            $options[$column] = $configuration->labelFor($column);
        }

        return $options;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->options(function (): array {
            $model = $this->getTable()->getModel();

            if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
                return [];
            }

            return self::optionsFor($model, $this->resolvedStateField());
        });
    }

    protected function resolvedStateField(): string
    {
        $field = $this->evaluate($this->stateFieldName);

        if (is_string($field) && $field !== '') {
            return $field;
        }

        return $this->getName();
    }
}
