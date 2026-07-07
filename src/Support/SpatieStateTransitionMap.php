<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Support;

class SpatieStateTransitionMap
{
    /**
     * @param  class-string  $baseStateClass
     * @param  list<class-string>  $stateClasses
     * @return array{
     *     columns: list<string>,
     *     state_class_map: array<string, class-string>,
     *     transitions: array<string, list<string>>
     * }
     */
    public static function build(string $baseStateClass, array $stateClasses): array
    {
        $stateClasses = SpatieStateOrdering::order($baseStateClass, $stateClasses);

        /** @var array<string, class-string> $stateClassMap */
        $stateClassMap = [];
        /** @var array<string, string> $columnKeyByMorph */
        $columnKeyByMorph = [];

        foreach ($stateClasses as $stateClass) {
            $columnKey = self::columnKeyForStateClass($stateClass);
            $stateClassMap[$columnKey] = $stateClass;
            $columnKeyByMorph[$stateClass::getMorphClass()] = $columnKey;
        }

        $columns = array_values(array_map(
            fn (string $stateClass): string => self::columnKeyForStateClass($stateClass),
            $stateClasses,
        ));

        $config = $baseStateClass::config();
        $transitions = self::transitionsFromConfig($config, $stateClasses, $columnKeyByMorph, $columns);

        return [
            'columns' => $columns,
            'state_class_map' => $stateClassMap,
            'transitions' => $transitions,
        ];
    }

    /**
     * @param  class-string  $stateClass
     */
    public static function columnKeyForStateClass(string $stateClass): string
    {
        return $stateClass::getMorphClass();
    }

    /**
     * @param  object  $config
     * @param  list<class-string>  $stateClasses
     * @param  array<string, string>  $columnKeyByMorph
     * @param  list<string>  $columnOrder
     * @return array<string, list<string>>
     */
    protected static function transitionsFromConfig(
        object $config,
        array $stateClasses,
        array $columnKeyByMorph,
        array $columnOrder,
    ): array {
        $transitions = [];
        $columnPositions = array_flip($columnOrder);

        foreach ($stateClasses as $stateClass) {
            $fromKey = self::columnKeyForStateClass($stateClass);
            $fromMorph = $stateClass::getMorphClass();

            $transitionableMorphs = $config->transitionableStates($fromMorph);

            $transitions[$fromKey] = collect($transitionableMorphs)
                ->map(fn (string $toMorph): string => $columnKeyByMorph[$toMorph] ?? $toMorph)
                ->sortBy(fn (string $targetKey): int => $columnPositions[$targetKey] ?? PHP_INT_MAX)
                ->values()
                ->all();
        }

        return $transitions;
    }
}
