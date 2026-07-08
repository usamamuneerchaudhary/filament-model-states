<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support;

use Illuminate\Database\Eloquent\Builder;
use Usamamuneerchaudhary\FilamentModelStates\Widgets\ModelStateKanbanBoard;

class ScopedOrderKanbanBoard extends ModelStateKanbanBoard
{
    public string $modelClass = Order::class;

    protected function getQuery(): Builder
    {
        return Order::query()->where('customer_name', 'scoped-visible');
    }
}
