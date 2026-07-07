<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Concerns;

use Filament\Widgets\WidgetConfiguration;
use Usamamuneerchaudhary\FilamentModelStates\Widgets\ModelStateKanbanBoard;

/**
 * Add to a Filament Resource for Kanban registration.
 */
trait HasStateKanbanBoard
{
    public static function getStateKanbanBoard(): WidgetConfiguration
    {
        return ModelStateKanbanBoard::for(static::getModel());
    }
}
