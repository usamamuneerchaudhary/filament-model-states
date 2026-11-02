<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\HasStates;
use Spatie\ModelStatus\HasStatuses;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\Booking\BookingState;

class BookingRecord extends Model
{
    /** @use HasFactory<BookingRecordFactory> */
    use HasFactory;

    use HasStates;
    use HasStatuses;

    protected $table = 'bookings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'state',
        'cancellation_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => BookingState::class,
        ];
    }

    public function isValidStatus(string $name, ?string $reason = null): bool
    {
        return true;
    }

    protected static function newFactory(): BookingRecordFactory
    {
        return BookingRecordFactory::new();
    }
}
