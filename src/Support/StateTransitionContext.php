<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Support;

use Illuminate\Database\Eloquent\Model;

readonly class StateTransitionContext
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, list<string>>|null  $allowedTransitions
     * @param  class-string|null  $targetStateClass
     * @param  array<string, string>  $abilities
     */
    public function __construct(
        public Model $record,
        public string $stateField,
        public string $targetColumn,
        public array $arguments = [],
        public ?array $allowedTransitions = null,
        public ?string $targetStateClass = null,
        public array $abilities = [],
        public bool $authorizeUpdate = true,
        public ?string $statusReason = 'State updated',
    ) {}
}
