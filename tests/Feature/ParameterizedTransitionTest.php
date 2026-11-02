<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\Booking\Cancelled;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\Booking\Initiated;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\BookingRecord;
use Usamamuneerchaudhary\FilamentModelStates\Widgets\ModelStateKanbanBoard;

beforeEach(function (): void {
    Schema::create('bookings', function (Blueprint $table): void {
        $table->id();
        $table->string('reference');
        $table->string('state');
        $table->string('cancellation_reason')->nullable();
        $table->timestamps();
    });

    Schema::create('statuses', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
        $table->text('reason')->nullable();
        $table->morphs('model');
        $table->timestamps();
    });

    Gate::before(function (mixed $user, string $ability, array $arguments): ?bool {
        if ($ability === 'update' && ($arguments[0] ?? null) instanceof BookingRecord) {
            return true;
        }

        return null;
    });
});

function bookingBoard(): ModelStateKanbanBoard
{
    $board = new ModelStateKanbanBoard;
    $board->modelClass = BookingRecord::class;

    return $board;
}

it('holds a parameterized kanban move until a reason is provided', function () {
    $booking = BookingRecord::factory()->create();

    bookingBoard()->moveRecord($booking->id, 'cancelled');

    $booking->refresh();

    expect($booking->state)->toBeInstanceOf(Initiated::class)
        ->and($booking->cancellation_reason)->toBeNull();
});

it('stores the pending transition on the board that received the drop', function () {
    $booking = BookingRecord::factory()->create();
    $board = bookingBoard();

    $board->moveRecord($booking->id, 'cancelled');

    expect($board->pendingTransition)->toBe([
        'recordId' => $booking->id,
        'targetColumn' => 'cancelled',
    ]);
});

it('completes a parameterized transition with the submitted reason', function () {
    $booking = BookingRecord::factory()->create();

    bookingBoard()->moveRecord($booking->id, 'cancelled', [
        'reason' => 'Customer cancelled',
    ]);

    $booking->refresh();

    expect($booking->state)->toBeInstanceOf(Cancelled::class)
        ->and($booking->cancellation_reason)->toBe('Customer cancelled')
        ->and($booking->latestStatus('cancelled'))->not->toBeNull();
});
