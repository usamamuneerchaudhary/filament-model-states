<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests\Phpstan;

use Illuminate\Support\ServiceProvider;

class ViewsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(
            dirname(__DIR__, 2).'/resources/views',
            'filament-model-states',
        );
    }
}
