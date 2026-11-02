<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Support;

use Filament\Schemas\Components\Component;

readonly class TransitionResult
{
    /**
     * @param  list<Component>  $schema
     * @param  list<mixed>  $positionalArguments
     */
    public function __construct(
        public TransitionResultStatus $status,
        public ?string $message = null,
        public ?string $sourceColumn = null,
        public ?string $targetColumn = null,
        public array $schema = [],
        public array $positionalArguments = [],
    ) {}
}
