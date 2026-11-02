<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support;

use Illuminate\Database\Eloquent\Factories\Factory;
use Usamamuneerchaudhary\FilamentModelStates\Tests\Support\Booking\Initiated;

/**
 * @extends Factory<BookingRecord>
 */
class BookingRecordFactory extends Factory
{
    protected $model = BookingRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'BK-'.fake()->unique()->numerify('#####'),
            'state' => Initiated::class,
            'cancellation_reason' => null,
        ];
    }
}
