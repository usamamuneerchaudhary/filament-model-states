<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Support;

use BackedEnum;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StateTransitionRunner
{
    /**
     * @var array<string, StateConfiguration>
     */
    private array $configurations = [];

    public function __construct(
        protected StateConfigurationResolver $configurationsResolver,
        protected TransitionFormFactory $forms,
    ) {}

    public function evaluate(StateTransitionContext $context): TransitionResult
    {
        $record = $context->record;
        $currentState = $record->getAttribute($context->stateField);
        $sourceColumn = $this->columnKey($currentState);

        if ($sourceColumn === $context->targetColumn) {
            return new TransitionResult(
                status: TransitionResultStatus::Unchanged,
                sourceColumn: $sourceColumn,
                targetColumn: $context->targetColumn,
            );
        }

        if ($context->authorizeUpdate) {
            Gate::authorize('update', $record);
        }

        $this->authorizeAbility($record, $sourceColumn, $context->targetColumn, $context->abilities);

        if (! $this->isAllowed($currentState, $sourceColumn, $context)) {
            return new TransitionResult(
                status: TransitionResultStatus::Rejected,
                sourceColumn: $sourceColumn,
                targetColumn: $context->targetColumn,
            );
        }

        $plan = $this->formPlan($currentState, $context);

        if ($plan->needsInput) {
            return new TransitionResult(
                status: TransitionResultStatus::NeedsInput,
                sourceColumn: $sourceColumn,
                targetColumn: $context->targetColumn,
                schema: $plan->schema,
            );
        }

        return new TransitionResult(
            status: TransitionResultStatus::Ready,
            sourceColumn: $sourceColumn,
            targetColumn: $context->targetColumn,
            positionalArguments: $plan->positionalArguments,
        );
    }

    public function attempt(StateTransitionContext $context): TransitionResult
    {
        $result = $this->evaluate($context);

        if ($result->status !== TransitionResultStatus::Ready) {
            return $result;
        }

        $this->persist($context, $result);

        return new TransitionResult(
            status: TransitionResultStatus::Applied,
            sourceColumn: $result->sourceColumn,
            targetColumn: $result->targetColumn,
        );
    }

    /**
     * @param  iterable<mixed>  $records
     * @param  callable(Model): StateTransitionContext  $contextFor
     * @return array{applied: int, skipped: int}
     */
    public function attemptMany(iterable $records, callable $contextFor): array
    {
        $applied = 0;
        $skipped = 0;

        foreach ($records as $record) {
            if (! $record instanceof Model) {
                $skipped++;

                continue;
            }

            try {
                $result = $this->attempt($contextFor($record));
            } catch (AuthorizationException) {
                $skipped++;

                continue;
            }

            if ($result->status === TransitionResultStatus::Applied) {
                $applied++;

                continue;
            }

            $skipped++;
        }

        return [
            'applied' => $applied,
            'skipped' => $skipped,
        ];
    }

    public function persist(StateTransitionContext $context, TransitionResult $result): void
    {
        $record = $context->record;
        $field = $context->stateField;
        $currentState = $record->getAttribute($field);
        $targetClass = $context->targetStateClass ?? $context->targetColumn;

        if (is_object($currentState) && method_exists($currentState, 'transitionTo')) {
            $currentState->transitionTo($targetClass, ...$result->positionalArguments);
        } else {
            $record->setAttribute($field, $this->valueForColumn($record, $field, $context->targetColumn));
            $record->save();
        }

        $this->recordStatus($record, $context->targetColumn, $context->statusReason ?? 'State updated');
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, string>  $abilities
     */
    public function contextFor(
        Model $record,
        string $targetColumn,
        array $arguments = [],
        ?string $stateField = null,
        array $abilities = [],
        ?string $statusReason = null,
    ): StateTransitionContext {
        $field = $stateField ?? $this->configurationsResolver->detectStateField($record::class);
        $configuration = $this->configuration($record::class, $field);
        $mapped = $configuration->stateClassMap[$targetColumn] ?? null;

        return new StateTransitionContext(
            record: $record,
            stateField: $field,
            targetColumn: $targetColumn,
            arguments: $arguments,
            allowedTransitions: $configuration->transitions,
            targetStateClass: is_string($mapped) ? $mapped : null,
            abilities: $abilities,
            statusReason: $statusReason ?? 'State updated',
        );
    }

    /**
     * @return list<string>
     */
    public function candidateTargets(Model $record, string $stateField): array
    {
        $configuration = $this->configuration($record::class, $stateField);
        $current = $this->columnKey($record->getAttribute($stateField));

        if ($configuration->transitions === null) {
            return array_values(array_filter(
                $configuration->columns,
                fn (string $column): bool => $column !== $current,
            ));
        }

        return $configuration->transitions[$current] ?? [];
    }

    /**
     * @param  list<string>  $targets
     * @param  array<string, string>  $abilities
     * @return list<string>
     */
    public function filterAbilities(Model $record, string $sourceColumn, array $targets, array $abilities): array
    {
        return array_values(array_filter(
            $targets,
            fn (string $target): bool => $this->abilityAllows($record, $sourceColumn, $target, $abilities),
        ));
    }

    /**
     * @param  list<string>  $targets
     * @return array<string, string>
     */
    public function labelsFor(Model $record, string $stateField, array $targets, bool $includeCurrent = true): array
    {
        $configuration = $this->configuration($record::class, $stateField);
        $keys = $targets;

        if ($includeCurrent) {
            array_unshift($keys, $this->columnKey($record->getAttribute($stateField)));
        }

        $options = [];

        foreach (array_values(array_unique($keys)) as $key) {
            $options[$key] = $configuration->labelFor($key);
        }

        return $options;
    }

    public function columnKey(mixed $state): string
    {
        if ($state instanceof BackedEnum) {
            return (string) $state->value;
        }

        if (is_object($state)) {
            $stateClass = $state::class;

            if (method_exists($stateClass, 'getMorphClass')) {
                return (string) $stateClass::getMorphClass();
            }

            if (method_exists($state, 'getValue')) {
                return (string) $state->getValue();
            }

            if (property_exists($state, 'name')) {
                return (string) $state->name;
            }

            return Str::snake(class_basename($state));
        }

        return (string) $state;
    }

    protected function isAllowed(mixed $currentState, string $sourceColumn, StateTransitionContext $context): bool
    {
        $config = $this->stateConfig($currentState);

        if (is_object($config) && method_exists($config, 'isTransitionAllowed')) {
            $from = $this->morphOf($currentState, $sourceColumn);

            return (bool) $config->isTransitionAllowed($from, $this->targetMorph($context));
        }

        if ($context->allowedTransitions === null) {
            return true;
        }

        return in_array($context->targetColumn, $context->allowedTransitions[$sourceColumn] ?? [], true);
    }

    protected function formPlan(mixed $currentState, StateTransitionContext $context): TransitionFormPlan
    {
        $transitionClass = $this->resolveTransitionClass($currentState, $context);

        if ($transitionClass === null) {
            return new TransitionFormPlan(needsInput: false);
        }

        return $this->forms->plan($transitionClass, $context->record, $context->arguments);
    }

    /**
     * @return class-string|null
     */
    protected function resolveTransitionClass(mixed $currentState, StateTransitionContext $context): ?string
    {
        $config = $this->stateConfig($currentState);

        if (! is_object($config) || ! method_exists($config, 'resolveTransitionClass')) {
            return null;
        }

        $transitionClass = $config->resolveTransitionClass(
            $this->morphOf($currentState, $this->columnKey($currentState)),
            $this->targetMorph($context),
        );

        if (! is_string($transitionClass) || ! class_exists($transitionClass)) {
            return null;
        }

        return $transitionClass;
    }

    protected function stateConfig(mixed $currentState): ?object
    {
        if (! is_object($currentState) || ! method_exists($currentState, 'config')) {
            return null;
        }

        $config = $currentState::config();

        return is_object($config) ? $config : null;
    }

    protected function morphOf(mixed $state, string $fallback): string
    {
        if (is_object($state) && method_exists($state, 'getMorphClass')) {
            return (string) $state::getMorphClass();
        }

        return $fallback;
    }

    protected function targetMorph(StateTransitionContext $context): string
    {
        $targetClass = $context->targetStateClass;

        if (is_string($targetClass) && class_exists($targetClass) && method_exists($targetClass, 'getMorphClass')) {
            return (string) $targetClass::getMorphClass();
        }

        return $context->targetColumn;
    }

    /**
     * @param  array<string, string>  $abilities
     */
    protected function authorizeAbility(Model $record, string $sourceColumn, string $targetColumn, array $abilities): void
    {
        if ($this->abilityAllows($record, $sourceColumn, $targetColumn, $abilities)) {
            return;
        }

        throw new AuthorizationException('This transition is unauthorized.');
    }

    /**
     * @param  array<string, string>  $abilities
     */
    protected function abilityAllows(Model $record, string $sourceColumn, string $targetColumn, array $abilities): bool
    {
        $ability = $abilities[$sourceColumn.'->'.$targetColumn] ?? null;

        if (! is_string($ability) || $ability === '') {
            return true;
        }

        return Gate::allows($ability, $record);
    }

    protected function valueForColumn(Model $record, string $field, string $column): mixed
    {
        $cast = $record->getCasts()[$field] ?? null;

        if (is_string($cast) && enum_exists($cast) && is_subclass_of($cast, BackedEnum::class)) {
            return $cast::from($column);
        }

        return $column;
    }

    protected function recordStatus(Model $record, string $targetColumn, string $reason): void
    {
        if (! method_exists($record, 'setStatus') || ! method_exists($record, 'status')) {
            return;
        }

        $status = $record->status();
        $name = is_object($status) && isset($status->name) ? $status->name : null;

        if ($name === $targetColumn) {
            return;
        }

        $record->setStatus($targetColumn, $reason);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function configuration(string $modelClass, string $field): StateConfiguration
    {
        return $this->configurations[$modelClass.'|'.$field] ??= $this->configurationsResolver->resolve($modelClass, $field);
    }
}
