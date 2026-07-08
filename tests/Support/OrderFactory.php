<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'ORD-'.fake()->unique()->numerify('#####'),
            'customer_name' => fake()->name(),
            'workflow_state' => fake()->randomElement(WorkflowState::cases()),
        ];
    }

    public function inState(WorkflowState $state): static
    {
        return $this->state(fn (): array => [
            'workflow_state' => $state,
        ]);
    }
}
