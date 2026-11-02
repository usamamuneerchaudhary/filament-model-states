<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Actions\Concerns;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Usamamuneerchaudhary\FilamentModelStates\Support\StateConfigurationResolver;
use Usamamuneerchaudhary\FilamentModelStates\Support\StateTransitionContext;
use Usamamuneerchaudhary\FilamentModelStates\Support\StateTransitionRunner;

trait ConfiguresStateTransition
{
    protected mixed $targetColumn = null;

    protected mixed $stateFieldName = null;

    /**
     * @var array<string, string>|Closure
     */
    protected array|Closure $transitionAbilities = [];

    public function target(mixed $target): static
    {
        $this->targetColumn = $target;

        return $this;
    }

    public function stateField(string|Closure $field): static
    {
        $this->stateFieldName = $field;

        return $this;
    }

    /**
     * @param  array<string, string>|Closure  $abilities
     */
    public function abilities(array|Closure $abilities): static
    {
        $this->transitionAbilities = $abilities;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function makeTransitionContext(Model $record, array $arguments = []): StateTransitionContext
    {
        return app(StateTransitionRunner::class)->contextFor(
            $record,
            $this->resolveTargetColumn(),
            $arguments,
            $this->resolveStateField($record),
            $this->resolveAbilities(),
            'State updated',
        );
    }

    protected function resolveTargetColumn(): string
    {
        $target = $this->evaluate($this->targetColumn);

        if ($target instanceof BackedEnum) {
            return (string) $target->value;
        }

        if (is_object($target) && method_exists($target, 'getMorphClass')) {
            return (string) $target::getMorphClass();
        }

        if (is_string($target) && class_exists($target) && method_exists($target, 'getMorphClass')) {
            return (string) $target::getMorphClass();
        }

        if (! is_string($target) || $target === '') {
            throw new InvalidArgumentException('A target state is required.');
        }

        return $target;
    }

    protected function resolveStateField(Model $record): string
    {
        $field = $this->evaluate($this->stateFieldName);

        if (is_string($field) && $field !== '') {
            return $field;
        }

        return app(StateConfigurationResolver::class)->detectStateField($record::class);
    }

    /**
     * @return array<string, string>
     */
    protected function resolveAbilities(): array
    {
        $abilities = $this->evaluate($this->transitionAbilities);

        if (! is_array($abilities)) {
            return [];
        }

        $resolved = [];

        foreach ($abilities as $transition => $ability) {
            if (is_string($transition) && is_string($ability)) {
                $resolved[$transition] = $ability;
            }
        }

        return $resolved;
    }
}
