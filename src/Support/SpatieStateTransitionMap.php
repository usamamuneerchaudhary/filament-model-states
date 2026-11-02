<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Support;

use Spatie\ModelStates\StateConfig;
use UnexpectedValueException;

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

        $columns = array_map(
            fn (string $stateClass): string => self::columnKeyForStateClass($stateClass),
            $stateClasses,
        );

        $config = $baseStateClass::config();

        if (! $config instanceof StateConfig) {
            throw new UnexpectedValueException("[{$baseStateClass}] did not return a Spatie state config.");
        }

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
     * @param  list<class-string>  $stateClasses
     * @param  array<string, string>  $columnKeyByMorph
     * @param  list<string>  $columnOrder
     * @return array<string, list<string>>
     */
    protected static function transitionsFromConfig(
        StateConfig $config,
        array $stateClasses,
        array $columnKeyByMorph,
        array $columnOrder,
    ): array {
        $transitions = [];
        $columnPositions = array_flip($columnOrder);

        foreach ($stateClasses as $stateClass) {
            $fromKey = self::columnKeyForStateClass($stateClass);
            $fromMorph = $stateClass::getMorphClass();
            $targets = [];

            foreach ($config->transitionableStates($fromMorph) as $toMorph) {
                if (! is_string($toMorph)) {
                    continue;
                }

                $targets[] = $columnKeyByMorph[$toMorph] ?? $toMorph;
            }

            usort(
                $targets,
                fn (string $left, string $right): int => ($columnPositions[$left] ?? PHP_INT_MAX) <=> ($columnPositions[$right] ?? PHP_INT_MAX),
            );

            $transitions[$fromKey] = $targets;
        }

        return $transitions;
    }
}
