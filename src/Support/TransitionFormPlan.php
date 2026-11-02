<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Support;

use Filament\Schemas\Components\Component;

readonly class TransitionFormPlan
{
    /**
     * @param  list<Component>  $schema
     * @param  list<mixed>  $positionalArguments
     */
    public function __construct(
        public bool $needsInput,
        public array $schema = [],
        public array $positionalArguments = [],
    ) {}
}
