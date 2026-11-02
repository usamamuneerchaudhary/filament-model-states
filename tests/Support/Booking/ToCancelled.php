<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support\Booking;

use Filament\Forms\Components\TextInput;
use Spatie\ModelStates\Transition;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\BookingRecord;

class ToCancelled extends Transition
{
    public function __construct(
        protected BookingRecord $booking,
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

    public function handle(): BookingRecord
    {
        $this->booking->state = new Cancelled($this->booking);
        $this->booking->cancellation_reason = $this->reason;
        $this->booking->save();

        return $this->booking;
    }
}
