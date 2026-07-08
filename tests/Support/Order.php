<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStatus\HasStatuses;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use HasStatuses;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'customer_name',
        'workflow_state',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'workflow_state' => WorkflowState::class,
        ];
    }

    public function isValidStatus(string $name, ?string $reason = null): bool
    {
        return WorkflowState::tryFrom($name) !== null;
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeFilamentKanbanSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $query) use ($search): void {
            $query->where('reference', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%");
        });
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }
}
