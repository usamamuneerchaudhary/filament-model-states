<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

readonly class StateConfiguration
{
    /**
     * @param  list<string>  $columns
     * @param  array<string, list<string>>|null  $transitions
     * @param  array<string, class-string>|null  $stateClassMap
     */
    public function __construct(
        public string $modelClass,
        public string $stateField,
        public array $columns,
        public ?array $transitions,
        public string $detectionSource,
        public ?string $enumClass = null,
        public bool $hasStatusHistory = false,
        public ?array $stateClassMap = null,
    ) {}

    public function labelFor(string $key): string
    {
        $enumClass = $this->enumClass;

        if (is_string($enumClass) && is_subclass_of($enumClass, BackedEnum::class)) {
            $case = $enumClass::tryFrom($key);

            if ($case !== null && method_exists($case, 'label')) {
                return (string) $case->label();
            }
        }

        $stateClass = $this->stateClassMap[$key] ?? null;

        if (is_string($stateClass) && method_exists($stateClass, 'label')) {
            return (string) $stateClass::label();
        }

        if (str_contains($key, '\\')) {
            return Str::headline(class_basename($key));
        }

        return Str::headline($key);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applySearch(Builder $query, string $search): Builder
    {
        if (! is_a($this->modelClass, Model::class, true)) {
            return $query;
        }

        $model = new $this->modelClass;

        if (! method_exists($model, 'scopeFilamentKanbanSearch')) {
            return $query;
        }

        $result = call_user_func([$model, 'scopeFilamentKanbanSearch'], $query, $search);

        if (! $result instanceof Builder) {
            return $query;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function toReport(): array
    {
        return [
            'model' => $this->modelClass,
            'state_field' => $this->stateField,
            'source' => $this->detectionSource,
            'enum' => $this->enumClass,
            'columns' => $this->columns,
            'transitions' => $this->transitions ?? 'all allowed',
            'status_history' => $this->hasStatusHistory,
        ];
    }
}
