<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Support;

enum TransitionResultStatus
{
    case Unchanged;
    case Rejected;
    case NeedsInput;
    case Ready;
    case Applied;
}
