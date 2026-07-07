<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Concerns;

use Usamamuneerchaudhary\FilamentModelStates\Infolists\Components\StatusTimelineEntry;

/**
 * Add to a resource infolist schema for automatic status history.
 */
trait HasStatusTimelineInfolist
{
    protected static function statusTimelineEntry(string $name = 'status_history'): StatusTimelineEntry
    {
        return StatusTimelineEntry::make($name)
            ->label('Status history')
            ->limit(20)
            ->columnSpanFull();
    }
}
