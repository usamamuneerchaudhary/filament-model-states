<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Actions;

use Filament\Actions\BulkAction;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Usamamuneerchaudhary\FilamentModelStates\Actions\Concerns\ConfiguresStateTransition;
use Usamamuneerchaudhary\FilamentModelStates\Support\StateTransitionContext;
use Usamamuneerchaudhary\FilamentModelStates\Support\StateTransitionRunner;

class StateBulkAction extends BulkAction
{
    use ConfiguresStateTransition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema(fn (): array => $this->bulkSchema());

        $this->modal(fn (): bool => $this->bulkSchema() !== []);

        $this->action(function (array $data): void {
            $counts = app(StateTransitionRunner::class)->attemptMany(
                $this->getSelectedRecords(),
                fn (Model $record): StateTransitionContext => $this->makeTransitionContext($record, $data),
            );

            Notification::make()
                ->title('State update finished')
                ->body("{$counts['applied']} updated, {$counts['skipped']} skipped.")
                ->success()
                ->send();
        });
    }

    /**
     * @return list<Component>
     */
    protected function bulkSchema(): array
    {
        try {
            $records = $this->getSelectedRecords();
        } catch (LogicException) {
            return [];
        }

        $runner = app(StateTransitionRunner::class);
        $schema = [];

        foreach ($records as $record) {
            if (! $record instanceof Model) {
                continue;
            }

            foreach ($runner->evaluate($this->makeTransitionContext($record))->schema as $component) {
                if ($component instanceof Field) {
                    $schema[$component->getName()] ??= $component;

                    continue;
                }

                $schema[] = $component;
            }
        }

        return array_values($schema);
    }
}
