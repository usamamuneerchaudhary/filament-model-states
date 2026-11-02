<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Usamamuneerchaudhary\FilamentModelStates\Exceptions\UnresolvableTransitionParameter;
use Usamamuneerchaudhary\FilamentModelStates\Support\StateTransitionContext;
use Usamamuneerchaudhary\FilamentModelStates\Support\StateTransitionRunner;
use Usamamuneerchaudhary\FilamentModelStates\Support\TransitionFormFactory;
use Usamamuneerchaudhary\FilamentModelStates\Tables\Columns\StateSelectColumn;
use Usamamuneerchaudhary\FilamentModelStates\Tables\Filters\StateSelectFilter;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\FormTransition;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\Order;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\ReasonTransition;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\WeirdTransition;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\WorkflowState;
use Usamamuneerchaudhary\FilamentModelStates\Widgets\ModelStateKanbanBoard;

beforeEach(function (): void {
    Schema::create('orders', function (Blueprint $table): void {
        $table->id();
        $table->string('reference')->unique();
        $table->string('customer_name');
        $table->string('workflow_state')->default('draft');
        $table->timestamps();
    });

    Schema::create('statuses', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
        $table->text('reason')->nullable();
        $table->morphs('model');
        $table->timestamps();
    });
});

function allowOrderUpdatesForComponents(): void
{
    Gate::before(function (mixed $user, string $ability, array $arguments): ?bool {
        if ($ability === 'update' && ($arguments[0] ?? null) instanceof Order) {
            return true;
        }

        return null;
    });
}

it('builds a text field for a required transition argument', function () {
    $plan = app(TransitionFormFactory::class)->plan(ReasonTransition::class, new Order, []);

    expect($plan->needsInput)->toBeTrue()
        ->and($plan->schema)->toHaveCount(2)
        ->and($plan->schema[0]->getName())->toBe('reason')
        ->and($plan->schema[1]->getName())->toBe('code');
});

it('turns submitted transition arguments into constructor arguments', function () {
    $plan = app(TransitionFormFactory::class)->plan(ReasonTransition::class, new Order, [
        'reason' => 'customer asked',
    ]);

    expect($plan->needsInput)->toBeFalse()
        ->and($plan->positionalArguments)->toBe(['customer asked']);
});

it('uses transitionForm when the transition class defines one', function () {
    $plan = app(TransitionFormFactory::class)->plan(FormTransition::class, new Order, []);

    expect($plan->needsInput)->toBeTrue()
        ->and($plan->schema[0]->getLabel())->toBe('Reason');
});

it('rejects transition parameters that cannot become form fields', function () {
    expect(fn () => app(TransitionFormFactory::class)->plan(WeirdTransition::class, new Order, []))
        ->toThrow(UnresolvableTransitionParameter::class);
});

it('limits select column options to allowed transitions', function () {
    $order = Order::factory()->inState(WorkflowState::Draft)->create();

    $options = StateSelectColumn::make('workflow_state')->optionsForRecord($order);

    expect($options)->toBe([
        'draft' => 'Draft',
        'pending' => 'Pending',
        'cancelled' => 'Cancelled',
    ]);
});

it('restores the previous state when an inline transition is rejected', function () {
    allowOrderUpdatesForComponents();

    $order = Order::factory()->inState(WorkflowState::Draft)->create();
    $column = StateSelectColumn::make('workflow_state')->record($order);

    expect($column->updateState('completed'))->toBe('draft');

    $order->refresh();

    expect($order->workflow_state)->toBe(WorkflowState::Draft);
});

it('persists an allowed inline transition', function () {
    allowOrderUpdatesForComponents();

    $order = Order::factory()->inState(WorkflowState::Draft)->create();
    $column = StateSelectColumn::make('workflow_state')->record($order);

    expect($column->updateState('pending'))->toBe('pending');

    $order->refresh();

    expect($order->workflow_state)->toBe(WorkflowState::Pending);
});

it('hides a select option when the transition ability is denied', function () {
    Gate::before(function (mixed $user, string $ability): ?bool {
        if ($ability === 'approve-orders') {
            return false;
        }

        return null;
    });

    $order = Order::factory()->inState(WorkflowState::Draft)->create();
    $options = StateSelectColumn::make('workflow_state')
        ->abilities(['draft->pending' => 'approve-orders'])
        ->optionsForRecord($order);

    expect(array_keys($options))->toBe(['draft', 'cancelled']);
});

it('builds filter options from the model state configuration', function () {
    expect(StateSelectFilter::optionsFor(Order::class, 'workflow_state'))
        ->toHaveKey('draft', 'Draft')
        ->toHaveKey('failed', 'Failed');
});

it('applies a bulk transition only where the state machine allows it', function () {
    allowOrderUpdatesForComponents();

    $draft = Order::factory()->inState(WorkflowState::Draft)->create();
    $completed = Order::factory()->inState(WorkflowState::Completed)->create();
    $runner = app(StateTransitionRunner::class);

    $counts = $runner->attemptMany(
        [$draft, $completed],
        fn (Order $record): StateTransitionContext => $runner->contextFor($record, 'pending'),
    );

    expect($counts)->toBe(['applied' => 1, 'skipped' => 1]);

    $draft->refresh();
    $completed->refresh();

    expect($draft->workflow_state)->toBe(WorkflowState::Pending)
        ->and($completed->workflow_state)->toBe(WorkflowState::Completed);
});

it('omits a kanban target and rejects the move when the ability is denied', function () {
    Gate::before(function (mixed $user, string $ability, array $arguments): ?bool {
        if ($ability === 'update' && ($arguments[0] ?? null) instanceof Order) {
            return true;
        }

        if ($ability === 'approve-orders') {
            return false;
        }

        return null;
    });

    $board = new class extends ModelStateKanbanBoard
    {
        protected function getTransitionAbilities(): array
        {
            return [
                'draft->pending' => 'approve-orders',
            ];
        }
    };
    $board->modelClass = Order::class;

    $order = Order::factory()->inState(WorkflowState::Draft)->create();

    expect($board->allowedTargetsForRecord($order))->not->toContain('pending');

    expect(fn () => $board->moveRecord($order->id, 'pending'))
        ->toThrow(AuthorizationException::class);

    $order->refresh();

    expect($order->workflow_state)->toBe(WorkflowState::Draft);
});
