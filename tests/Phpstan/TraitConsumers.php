<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Phpstan;

use Illuminate\Database\Eloquent\Model;
use Usamamuneerchaudhary\FilamentModelStates\Concerns\HasStateKanbanBoard;
use Usamamuneerchaudhary\FilamentModelStates\Concerns\HasStatusTimelineInfolist;

final class KanbanResourceConsumer
{
    use HasStateKanbanBoard;

    public static function getModel(): string
    {
        return Model::class;
    }
}

final class TimelineConsumer
{
    use HasStatusTimelineInfolist;
}
