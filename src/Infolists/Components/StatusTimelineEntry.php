<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Infolists\Components;

use Filament\Infolists\Components\Entry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class StatusTimelineEntry extends Entry
{
    protected string $view = 'filament-model-states::components.status-timeline';

    protected int $limit;

    protected bool $showReason;

    protected ?string $emptyStateLabel = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->limit = (int) config('filament-model-states.timeline.per_page', 25);
        $this->showReason = (bool) config('filament-model-states.timeline.show_reason', true);
        $this->emptyStateLabel = 'No status changes recorded yet.';
    }

    /**
     * Limit how many history entries are shown before "load more".
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function hideReason(): static
    {
        $this->showReason = false;

        return $this;
    }

    public function emptyStateLabel(string $label): static
    {
        $this->emptyStateLabel = $label;

        return $this;
    }

    public function getShowReason(): bool
    {
        return $this->showReason;
    }

    public function getEmptyStateLabel(): string
    {
        return $this->emptyStateLabel ?? 'No status changes recorded yet.';
    }

    /**
     * Pulls the history from spatie/laravel-model-status's `statuses`
     * relation (requires the model to use the HasStatuses trait) and
     * shapes it into a simple, view-friendly collection with computed
     * "duration in this state" for each step.
     */
    public function getTimelineData(): Collection
    {
        /** @var Model|null $record */
        $record = $this->getRecord();

        if (! $record || ! method_exists($record, 'statuses')) {
            return collect();
        }

        $statuses = $record->statuses()
            ->orderByDesc('created_at')
            ->limit($this->limit)
            ->get();

        return $statuses->values()->map(function ($status, int $index) use ($statuses) {
            $endedAt = $index > 0 ? $statuses[$index - 1]->created_at : null;

            return [
                'name' => $status->name,
                'reason' => $status->reason,
                'started_at' => $status->created_at,
                'ended_at' => $endedAt,
                'duration' => $endedAt
                    ? $status->created_at->diffForHumans($endedAt, true)
                    : $status->created_at->diffForHumans(null, true) . ' so far',
                'is_current' => $index === 0,
            ];
        });
    }
}
