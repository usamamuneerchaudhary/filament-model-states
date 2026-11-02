<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Usamamuneerchaudhary\FilamentModelStates\Actions\Concerns\ConfiguresStateTransition;
use Usamamuneerchaudhary\FilamentModelStates\Support\StateTransitionRunner;
use Usamamuneerchaudhary\FilamentModelStates\Support\TransitionResultStatus;

class StateAction extends Action
{
    use ConfiguresStateTransition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema(function (): array {
            $record = $this->getRecord();

            if (! $record instanceof Model) {
                return [];
            }

            return app(StateTransitionRunner::class)
                ->evaluate($this->makeTransitionContext($record))
                ->schema;
        });

        $this->modal(function (): bool {
            $record = $this->getRecord();

            if (! $record instanceof Model) {
                return false;
            }

            return app(StateTransitionRunner::class)
                ->evaluate($this->makeTransitionContext($record))
                ->schema !== [];
        });

        $this->visible(function (): bool {
            $record = $this->getRecord();

            if (! $record instanceof Model) {
                return false;
            }

            try {
                $target = $this->resolveTargetColumn();
            } catch (InvalidArgumentException) {
                return false;
            }

            $runner = app(StateTransitionRunner::class);
            $field = $this->resolveStateField($record);
            $current = $runner->columnKey($record->getAttribute($field));
            $allowed = $runner->filterAbilities(
                $record,
                $current,
                $runner->candidateTargets($record, $field),
                $this->resolveAbilities(),
            );

            return in_array($target, $allowed, true);
        });

        $this->action(function (array $data): void {
            $record = $this->getRecord();

            if (! $record instanceof Model) {
                return;
            }

            try {
                $result = app(StateTransitionRunner::class)->attempt(
                    $this->makeTransitionContext($record, $data),
                );
            } catch (AuthorizationException $exception) {
                Notification::make()
                    ->title('Move not allowed')
                    ->body($exception->getMessage())
                    ->danger()
                    ->send();

                throw new Halt;
            }

            if ($result->status !== TransitionResultStatus::Applied) {
                Notification::make()
                    ->title('Move not allowed')
                    ->body($result->message ?? 'This transition is not allowed.')
                    ->danger()
                    ->send();

                throw new Halt;
            }

            Notification::make()
                ->title('Status updated')
                ->success()
                ->send();
        });
    }
}
