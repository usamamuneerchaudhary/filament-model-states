<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support;

use Filament\Forms\Components\TextInput;

class FormTransition
{
    public function __construct(
        public Order $order,
        public string $reason,
    ) {}

    /**
     * @return list<TextInput>
     */
    public static function transitionForm(): array
    {
        return [
            TextInput::make('reason')->label('Reason')->required(),
        ];
    }
}
