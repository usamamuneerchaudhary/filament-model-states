<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StateConfigurationResolver
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function resolve(string $modelClass, ?string $stateField = null): StateConfiguration
    {
        if (! is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException("Class [{$modelClass}] must extend Illuminate\Database\Eloquent\Model.");
        }

        /** @var Model $model */
        $model = new $modelClass;

        $field = $stateField ?? $this->detectStateField($modelClass);
        $cast = $model->getCasts()[$field] ?? null;

        if (is_string($cast) && enum_exists($cast) && is_subclass_of($cast, BackedEnum::class)) {
            return $this->fromEnum($modelClass, $field, $cast);
        }

        $spatie = $this->tryFromSpatie($modelClass, $field);

        if ($spatie !== null) {
            return $spatie;
        }

        $cast = $model->getCasts()[$field] ?? null;

        if ($this->isSpatieStateCast($cast)) {
            throw new InvalidArgumentException(
                "Model [{$modelClass}] casts [{$field}] to {$cast} but no concrete state classes were discovered. Place state classes in the same directory as {$cast} (Spatie auto-discovery), or register them via registerState() in config().",
            );
        }

        return $this->fromDatabase($modelClass, $field);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function detectStateField(string $modelClass): string
    {
        /** @var Model $model */
        $model = new $modelClass;
        $casts = $model->getCasts();

        foreach (['workflow_state', 'state', 'status'] as $candidate) {
            if (array_key_exists($candidate, $casts)) {
                return $candidate;
            }
        }

        foreach ($casts as $field => $cast) {
            if ($this->isSpatieStateCast($cast) && (str_contains($field, 'state') || str_contains($field, 'status'))) {
                return $field;
            }
        }

        foreach ($casts as $field => $cast) {
            if (! is_string($cast) || ! enum_exists($cast) || ! is_subclass_of($cast, BackedEnum::class)) {
                continue;
            }

            if (str_contains($field, 'state') || str_contains($field, 'status')) {
                return $field;
            }
        }

        foreach ($casts as $field => $cast) {
            if (is_string($cast) && enum_exists($cast) && is_subclass_of($cast, BackedEnum::class)) {
                return $field;
            }
        }

        foreach ($casts as $field => $cast) {
            if ($this->isSpatieStateCast($cast)) {
                return $field;
            }
        }

        throw new InvalidArgumentException(
            "Could not detect a state field on [{$modelClass}]. Pass --state-field= or cast an enum attribute.",
        );
    }

    /**
     * @param  class-string<BackedEnum>  $enumClass
     */
    protected function fromEnum(string $modelClass, string $field, string $enumClass): StateConfiguration
    {
        /** @var list<string> $columns */
        $columns = array_column($enumClass::cases(), 'value');

        return new StateConfiguration(
            modelClass: $modelClass,
            stateField: $field,
            columns: $columns,
            transitions: $this->resolveTransitions($modelClass, $enumClass),
            detectionSource: 'enum',
            enumClass: $enumClass,
            hasStatusHistory: $this->usesStatusHistory($modelClass),
        );
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function fromDatabase(string $modelClass, string $field): StateConfiguration
    {
        /** @var list<string> $columns */
        $columns = $modelClass::query()
            ->select($field)
            ->distinct()
            ->orderBy($field)
            ->pluck($field)
            ->filter()
            ->map(fn ($value): string => (string) $value)
            ->values()
            ->all();

        return new StateConfiguration(
            modelClass: $modelClass,
            stateField: $field,
            columns: $columns,
            transitions: $this->resolveTransitions($modelClass),
            detectionSource: 'database',
            hasStatusHistory: $this->usesStatusHistory($modelClass),
        );
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function tryFromSpatie(string $modelClass, string $field): ?StateConfiguration
    {
        if (! class_exists('Spatie\ModelStates\State')) {
            return null;
        }

        $baseStateClass = $this->resolveSpatieBaseStateClass($modelClass, $field);

        if ($baseStateClass === null) {
            return null;
        }

        /** @var list<class-string> $stateClasses */
        $stateClasses = collect($baseStateClass::getStateMapping())
            ->values()
            ->filter(fn (string $stateClass): bool => class_exists($stateClass))
            ->values()
            ->all();

        if ($stateClasses === []) {
            return null;
        }

        $map = SpatieStateTransitionMap::build($baseStateClass, $stateClasses);

        return new StateConfiguration(
            modelClass: $modelClass,
            stateField: $field,
            columns: $map['columns'],
            transitions: $map['transitions'],
            detectionSource: 'spatie-model-states',
            hasStatusHistory: $this->usesStatusHistory($modelClass),
            stateClassMap: $map['state_class_map'],
        );
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return class-string|null
     */
    protected function resolveSpatieBaseStateClass(string $modelClass, string $field): ?string
    {
        /** @var Model $model */
        $model = new $modelClass;
        $cast = $model->getCasts()[$field] ?? null;

        if (! $this->isSpatieStateCast($cast)) {
            return null;
        }

        return $cast;
    }

    protected function isSpatieStateCast(mixed $cast): bool
    {
        return is_string($cast)
            && class_exists('Spatie\ModelStates\State')
            && is_subclass_of($cast, 'Spatie\ModelStates\State');
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  class-string<BackedEnum>|null  $enumClass
     * @return array<string, list<string>>|null
     */
    protected function resolveTransitions(string $modelClass, ?string $enumClass = null): ?array
    {
        if ($enumClass !== null && method_exists($enumClass, 'transitions')) {
            /** @var array<string, list<string>> $transitions */
            $transitions = $enumClass::transitions();

            return $transitions;
        }

        if (method_exists($modelClass, 'getStateTransitions')) {
            /** @var array<string, list<string>> $transitions */
            $transitions = $modelClass::getStateTransitions();

            return $transitions;
        }

        return null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function usesStatusHistory(string $modelClass): bool
    {
        return in_array('Spatie\ModelStatus\HasStatuses', class_uses_recursive($modelClass), true);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, mixed>
     */
    public function inspect(string $modelClass, ?string $stateField = null): array
    {
        $configuration = $this->resolve($modelClass, $stateField);

        return $configuration->toReport();
    }
}
