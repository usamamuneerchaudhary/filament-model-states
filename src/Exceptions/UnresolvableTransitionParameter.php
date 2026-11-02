<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Exceptions;

use RuntimeException;

class UnresolvableTransitionParameter extends RuntimeException
{
    public static function forParameter(string $transitionClass, string $parameter): self
    {
        return new self(
            "Transition [{$transitionClass}] parameter \${$parameter} cannot be turned into a form field. Add a static transitionForm() method that returns Filament components.",
        );
    }
}
