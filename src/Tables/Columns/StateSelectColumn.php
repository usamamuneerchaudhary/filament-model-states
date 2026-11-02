<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tables\Columns;

use BackedEnum;
use Closure;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\SelectColumn;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Usamamuneerchaudhary\FilamentModelStates\Support\StateTransitionRunner;
use Usamamuneerchaudhary\FilamentModelStates\Support\TransitionResultStatus;

class StateSelectColumn extends SelectColumn
{
    /**
     * @var array<string, string>|Closure
     */
    protected array|Closure $transitionAbilities = [];

    /**
     * @param  array<string, string>|Closure  $abilities
     */
    public function abilities(array|Closure $abilities): static
    {
        $this->transitionAbilities = $abilities;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->options(function (): array {
            $record = $this->getRecord();

            if (! $record instanceof Model) {
                return [];
            }

            return $this->optionsForRecord($record);
        });

        $this->updateStateUsing(function (mixed $state): mixed {
            $record = $this->getRecord();

            if (! $record instanceof Model) {
                return $state;
            }

            $previous = $this->storedValue($record->getAttribute($this->getName()));
            $target = $this->storedValue($state);

            if (! is_string($target) || $target === '') {
                return $previous;
            }

            try {
                $runner = app(StateTransitionRunner::class);
                $result = $runner->attempt(
                    $runner->contextFor(
                        $record,
                        $target,
                        [],
                        $this->getName(),
                        $this->resolvedAbilities(),
                        'Updated from table',
                    ),
                );
            } catch (AuthorizationException $exception) {
                Notification::make()
                    ->title('Move not allowed')
                    ->body($exception->getMessage())
                    ->danger()
                    ->send();

                return $previous;
            }

            if ($result->status === TransitionResultStatus::Applied) {
                return $target;
            }

            if ($result->status === TransitionResultStatus::Unchanged) {
                return $previous;
            }

            $notification = Notification::make()->title(
                $result->status === TransitionResultStatus::NeedsInput ? 'More detail required' : 'Move not allowed',
            );

            if ($result->status === TransitionResultStatus::NeedsInput) {
                $notification
                    ->body('This transition needs extra input. Use a state action to complete it.')
                    ->warning();
            } else {
                $notification
                    ->body($result->message ?? 'This transition is not allowed.')
                    ->danger();
            }

            $notification->send();

            return $previous;
        });
    }

    /**
     * @return array<string, string>
     */
    public function optionsForRecord(Model $record): array
    {
        $runner = app(StateTransitionRunner::class);
        $field = $this->getName();
        $current = $runner->columnKey($record->getAttribute($field));
        $targets = $runner->filterAbilities(
            $record,
            $current,
            $runner->candidateTargets($record, $field),
            $this->resolvedAbilities(),
        );

        return $runner->labelsFor($record, $field, $targets);
    }

    /**
     * @return array<string, string>
     */
    protected function resolvedAbilities(): array
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

    protected function storedValue(mixed $state): mixed
    {
        if ($state instanceof BackedEnum) {
            return $state->value;
        }

        if (is_object($state) && method_exists($state, 'getValue')) {
            return $state->getValue();
        }

        return $state;
    }
}
