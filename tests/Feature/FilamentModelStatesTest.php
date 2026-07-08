<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Usamamuneerchaudhary\FilamentModelStates\Infolists\Components\StatusTimelineEntry;
use Usamamuneerchaudhary\FilamentModelStates\Support\SpatieStateOrdering;
use Usamamuneerchaudhary\FilamentModelStates\Support\StateConfiguration;
use Usamamuneerchaudhary\FilamentModelStates\Support\StateConfigurationResolver;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\AwaitingPaymentStateStub;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\BaseStateWithoutOrderStub;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\BookingStateOrderingStub;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\CompletedStateOrderingStub;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\InitiatedStateOrderingStub;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\InitiatedStateStub;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\MorphKeyedStateStub;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\Order;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\PendingStateOrderingStub;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\ScopedOrderKanbanBoard;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\SortOrderFirstStateStub;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\SortOrderSecondStateStub;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\WorkflowState;
use Usamamuneerchaudhary\FilamentModelStates\Widgets\ModelStateKanbanBoard;
use Usamamuneerchaudhary\FilamentModelStates\Widgets\StateKanbanBoard;

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

function kanbanBoard(): ModelStateKanbanBoard
{
    $board = new ModelStateKanbanBoard;
    $board->modelClass = Order::class;

    return $board;
}

function allowOrderUpdates(): void
{
    Gate::before(function (mixed $user, string $ability, array $arguments): ?bool {
        if ($ability === 'update' && ($arguments[0] ?? null) instanceof Order) {
            return true;
        }

        return null;
    });
}

it('auto-detects workflow states from the order model', function () {
    $report = app(StateConfigurationResolver::class)->inspect(Order::class);

    expect($report['state_field'])->toBe('workflow_state')
        ->and($report['source'])->toBe('enum')
        ->and($report['columns'])->toBe([
            'draft', 'pending', 'processing', 'completed', 'cancelled', 'failed',
        ])
        ->and($report['status_history'])->toBeTrue();
});

it('builds status timeline data from model status history', function () {
    $order = Order::factory()->inState(WorkflowState::Draft)->create([
        'reference' => 'ORD-TEST-1',
        'customer_name' => 'Timeline Tester',
    ]);

    $order->setStatus('draft', 'Order created');
    $order->setStatus('pending', 'Payment link sent');

    $entry = StatusTimelineEntry::make('status_history');
    $entry->model($order);

    $timeline = $entry->getTimelineData();

    expect($timeline)->toHaveCount(2)
        ->and($timeline->first()['name'])->toBe('pending')
        ->and($timeline->first()['is_current'])->toBeTrue()
        ->and($timeline->last()['name'])->toBe('draft');
});

it('moves an order to an allowed workflow state via the kanban board', function () {
    allowOrderUpdates();

    $order = Order::factory()->inState(WorkflowState::Draft)->create();
    $order->setStatus('draft', 'Order created');

    kanbanBoard()->moveRecord($order->id, 'pending');

    $order->refresh();

    expect($order->workflow_state)->toBe(WorkflowState::Pending)
        ->and($order->status())->not->toBeNull()
        ->and($order->latestStatus('pending'))->not->toBeNull();
});

it('rejects invalid kanban transitions', function () {
    allowOrderUpdates();

    $order = Order::factory()->inState(WorkflowState::Draft)->create();
    $order->setStatus('draft', 'Order created');

    kanbanBoard()->moveRecord($order->id, 'completed');

    $order->refresh();

    expect($order->workflow_state)->toBe(WorkflowState::Draft);
});

it('groups orders by workflow state column', function () {
    Order::factory()->inState(WorkflowState::Draft)->create();
    Order::factory()->inState(WorkflowState::Pending)->create();
    Order::factory()->inState(WorkflowState::Pending)->create();

    $columns = kanbanBoard()->getRecordsByColumn();

    expect($columns['draft'])->toHaveCount(1)
        ->and($columns['pending'])->toHaveCount(2);
});

it('uses morph keys for spatie state labels instead of descriptions', function () {
    $configuration = new StateConfiguration(
        modelClass: Order::class,
        stateField: 'status',
        columns: ['initiated', 'awaiting_payment'],
        transitions: null,
        detectionSource: 'spatie-model-states',
        stateClassMap: [
            'initiated' => InitiatedStateStub::class,
            'awaiting_payment' => AwaitingPaymentStateStub::class,
        ],
    );

    expect($configuration->labelFor('initiated'))->toBe('Initiated')
        ->and($configuration->labelFor('awaiting_payment'))->toBe('Awaiting Payment');
});

it('orders spatie columns using columnOrder on the base state class', function () {
    $ordered = SpatieStateOrdering::order(BookingStateOrderingStub::class, [
        CompletedStateOrderingStub::class,
        InitiatedStateOrderingStub::class,
        PendingStateOrderingStub::class,
    ]);

    expect($ordered)->toBe([
        PendingStateOrderingStub::class,
        InitiatedStateOrderingStub::class,
        CompletedStateOrderingStub::class,
    ]);
});

it('orders spatie columns using per-state sortOrder when provided', function () {
    $ordered = SpatieStateOrdering::order(BaseStateWithoutOrderStub::class, [
        SortOrderSecondStateStub::class,
        SortOrderFirstStateStub::class,
    ]);

    expect($ordered)->toBe([
        SortOrderFirstStateStub::class,
        SortOrderSecondStateStub::class,
    ]);
});

it('exposes allowed transition targets per column', function () {
    $board = kanbanBoard();

    expect($board->getOutgoingTransitions('draft'))
        ->toBe(['pending', 'cancelled'])
        ->and($board->getOutgoingTransitions('completed'))
        ->toBe([]);
});

it('limits visible cards per column while reporting sql totals', function () {
    config(['filament-model-states.kanban.records_per_column' => 2]);

    Order::factory()->count(5)->inState(WorkflowState::Draft)->create();

    $board = kanbanBoard();

    $draftColumn = collect($board->getBoardColumns())->firstWhere('key', 'draft');

    expect($draftColumn['shown'])->toBe(2)
        ->and($draftColumn['total'])->toBe(5)
        ->and($draftColumn['has_more'])->toBeTrue()
        ->and($board->getTotalCount())->toBe(5);
});

it('filters the kanban board via search', function () {
    Order::factory()->create([
        'reference' => 'ORD-UNIQUE-999',
        'customer_name' => 'Unique Customer',
        'workflow_state' => WorkflowState::Draft,
    ]);

    Order::factory()->count(3)->inState(WorkflowState::Pending)->create();

    $board = kanbanBoard();
    $board->search = 'UNIQUE-999';

    expect($board->getTotalCount())->toBe(1)
        ->and($board->getRecordsByColumn()['draft'])->toHaveCount(1)
        ->and($board->getRecordsByColumn()['pending'])->toHaveCount(0);
});

it('denies kanban moves when update is unauthorized', function () {
    Gate::before(function (mixed $user, string $ability, array $arguments): ?bool {
        if ($ability === 'update' && ($arguments[0] ?? null) instanceof Order) {
            return false;
        }

        return null;
    });

    $order = Order::factory()->inState(WorkflowState::Draft)->create();

    expect(fn () => kanbanBoard()->moveRecord($order->id, 'pending'))
        ->toThrow(AuthorizationException::class);

    $order->refresh();

    expect($order->workflow_state)->toBe(WorkflowState::Draft);
});

it('rejects moves for records outside getQuery scope', function () {
    allowOrderUpdates();

    $visibleOrder = Order::factory()->inState(WorkflowState::Draft)->create([
        'customer_name' => 'scoped-visible',
    ]);

    $hiddenOrder = Order::factory()->inState(WorkflowState::Draft)->create([
        'customer_name' => 'scoped-hidden',
    ]);

    $scopedBoard = new ScopedOrderKanbanBoard;
    $scopedBoard->moveRecord($hiddenOrder->id, 'pending');

    $hiddenOrder->refresh();
    $visibleOrder->refresh();

    expect($hiddenOrder->workflow_state)->toBe(WorkflowState::Draft);

    $scopedBoard->moveRecord($visibleOrder->id, 'pending');

    $visibleOrder->refresh();

    expect($visibleOrder->workflow_state)->toBe(WorkflowState::Pending);
});

it('normalizes spatie state objects using morph class keys', function () {
    $board = kanbanBoard();
    $method = new ReflectionMethod(StateKanbanBoard::class, 'normalizeStateToColumnKey');
    $method->setAccessible(true);

    expect($method->invoke($board, new MorphKeyedStateStub))->toBe('awaiting_payment');
});

it('fills missing transition map keys for client and server parity', function () {
    $board = new class extends StateKanbanBoard
    {
        protected function getModel(): string
        {
            return Order::class;
        }

        protected function getStateField(): string
        {
            return 'workflow_state';
        }

        protected function getColumns(): array
        {
            return ['draft', 'pending', 'completed'];
        }

        protected function getAllowedTransitions(): ?array
        {
            return [
                'draft' => ['pending'],
            ];
        }
    };

    expect($board->getTransitionMapForView())->toBe([
        'draft' => ['pending'],
        'pending' => [],
        'completed' => [],
    ]);
});

it('uses workflow state labels in the status timeline', function () {
    $order = Order::factory()->inState(WorkflowState::Pending)->create();

    $entry = StatusTimelineEntry::make('status_history');
    $entry->model($order);

    expect($entry->getStatusLabel($order, 'pending'))->toBe('Pending');
});

it('memoizes board columns within a single request', function () {
    Order::factory()->count(2)->inState(WorkflowState::Draft)->create();

    $board = kanbanBoard();
    $first = $board->getBoardColumns();
    $second = $board->getBoardColumns();

    expect($first)->toBe($second);
});
