<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Support;

use Illuminate\Database\Eloquent\Builder;
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
        if ($this->enumClass !== null && enum_exists($this->enumClass)) {
            $case = $this->enumClass::tryFrom($key);

            if ($case !== null && method_exists($case, 'label')) {
                return $case->label();
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

    public function applySearch(Builder $query, string $search): Builder
    {
        $modelClass = $this->modelClass;

        if (method_exists($modelClass, 'scopeFilamentKanbanSearch')) {
            return $query->filamentKanbanSearch($search);
        }

        return $query;
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
