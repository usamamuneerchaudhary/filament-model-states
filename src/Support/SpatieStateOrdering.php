<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Support;

use ReflectionClass;

class SpatieStateOrdering
{
    /**
     * @param  class-string  $baseStateClass
     * @param  list<class-string>  $stateClasses
     * @return list<class-string>
     */
    public static function order(string $baseStateClass, array $stateClasses): array
    {
        if ($stateClasses === []) {
            return [];
        }

        if (method_exists($baseStateClass, 'columnOrder')) {
            /** @var list<string> $explicitOrder */
            $explicitOrder = $baseStateClass::columnOrder();

            return self::applyExplicitOrder($explicitOrder, $stateClasses);
        }

        if (self::stateClassesHaveSortOrder($stateClasses)) {
            return collect($stateClasses)
                ->sortBy(fn (string $stateClass): int => self::sortOrderFor($stateClass))
                ->values()
                ->all();
        }

        if (method_exists($baseStateClass, 'config')) {
            return self::orderFromStateConfig($baseStateClass, $stateClasses);
        }

        return $stateClasses;
    }

    /**
     * @param  list<string>  $explicitOrder  Morph keys or state class names.
     * @param  list<class-string>  $stateClasses
     * @return list<class-string>
     */
    protected static function applyExplicitOrder(array $explicitOrder, array $stateClasses): array
    {
        /** @var array<string, class-string> $byMorph */
        $byMorph = [];
        /** @var array<class-string, class-string> $byClass */
        $byClass = [];

        foreach ($stateClasses as $stateClass) {
            $byMorph[$stateClass::getMorphClass()] = $stateClass;
            $byClass[$stateClass] = $stateClass;
        }

        $ordered = [];

        foreach ($explicitOrder as $entry) {
            $stateClass = $byClass[$entry] ?? $byMorph[$entry] ?? null;

            if ($stateClass !== null && ! in_array($stateClass, $ordered, true)) {
                $ordered[] = $stateClass;
            }
        }

        foreach ($stateClasses as $stateClass) {
            if (! in_array($stateClass, $ordered, true)) {
                $ordered[] = $stateClass;
            }
        }

        return $ordered;
    }

    /**
     * @param  list<class-string>  $stateClasses
     */
    protected static function stateClassesHaveSortOrder(array $stateClasses): bool
    {
        foreach ($stateClasses as $stateClass) {
            if (self::hasSortOrder($stateClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  class-string  $stateClass
     */
    protected static function hasSortOrder(string $stateClass): bool
    {
        return (new ReflectionClass($stateClass))->hasProperty('sortOrder')
            || method_exists($stateClass, 'sortOrder');
    }

    /**
     * @param  class-string  $stateClass
     */
    protected static function sortOrderFor(string $stateClass): int
    {
        if (method_exists($stateClass, 'sortOrder')) {
            return (int) $stateClass::sortOrder();
        }

        if (property_exists($stateClass, 'sortOrder')) {
            return (int) $stateClass::$sortOrder;
        }

        return PHP_INT_MAX;
    }

    /**
     * Order states by how they appear in the abstract state's config():
     * default state first, then the order transitions were registered.
     *
     * @param  class-string  $baseStateClass
     * @param  list<class-string>  $stateClasses
     * @return list<class-string>
     */
    protected static function orderFromStateConfig(string $baseStateClass, array $stateClasses): array
    {
        $config = $baseStateClass::config();

        /** @var array<string, class-string> $classByMorph */
        $classByMorph = [];

        foreach ($stateClasses as $stateClass) {
            $classByMorph[$stateClass::getMorphClass()] = $stateClass;
        }

        /** @var list<string> $orderedMorphs */
        $orderedMorphs = [];

        if (is_string($config->defaultStateClass) && $config->defaultStateClass !== '') {
            $orderedMorphs[] = $config->defaultStateClass::getMorphClass();
        }

        foreach (array_keys($config->allowedTransitions) as $transitionKey) {
            [$fromMorph, $toMorph] = explode('->', (string) $transitionKey, 2);

            foreach ([$fromMorph, $toMorph] as $morph) {
                if (! in_array($morph, $orderedMorphs, true)) {
                    $orderedMorphs[] = $morph;
                }
            }
        }

        foreach ($config->registeredStates as $registeredStateClass) {
            $morph = $registeredStateClass::getMorphClass();

            if (! in_array($morph, $orderedMorphs, true)) {
                $orderedMorphs[] = $morph;
            }
        }

        foreach (self::discoverStateClassesFromDirectory($baseStateClass) as $discoveredStateClass) {
            $morph = $discoveredStateClass::getMorphClass();

            if (! in_array($morph, $orderedMorphs, true)) {
                $orderedMorphs[] = $morph;
            }
        }

        /** @var list<class-string> $ordered */
        $ordered = [];

        foreach ($orderedMorphs as $morph) {
            $stateClass = $classByMorph[$morph] ?? null;

            if ($stateClass !== null) {
                $ordered[] = $stateClass;
            }
        }

        foreach ($stateClasses as $stateClass) {
            if (! in_array($stateClass, $ordered, true)) {
                $ordered[] = $stateClass;
            }
        }

        return $ordered;
    }

    /**
     * Mirror Spatie's directory discovery order (filesystem order, not alphabetical).
     *
     * @param  class-string  $baseStateClass
     * @return list<class-string>
     */
    protected static function discoverStateClassesFromDirectory(string $baseStateClass): array
    {
        $reflection = new ReflectionClass($baseStateClass);
        $directory = dirname((string) $reflection->getFileName());
        $namespace = $reflection->getNamespaceName();
        $files = scandir($directory, SCANDIR_SORT_NONE);

        if ($files === false) {
            return [];
        }

        /** @var list<class-string> $stateClasses */
        $stateClasses = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $className = pathinfo($file, PATHINFO_FILENAME);
            $stateClass = $namespace.'\\'.$className;

            if (! class_exists($stateClass) || ! is_subclass_of($stateClass, $baseStateClass)) {
                continue;
            }

            $stateClasses[] = $stateClass;
        }

        return $stateClasses;
    }
}
