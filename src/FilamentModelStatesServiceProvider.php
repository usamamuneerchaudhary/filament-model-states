<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Usamamuneerchaudhary\FilamentModelStates\Commands\MakeKanbanCommand;

class FilamentModelStatesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-model-states')
            ->hasConfigFile()
            ->hasViews('filament-model-states')
            ->hasCommand(MakeKanbanCommand::class);
    }

    public function packageBooted(): void
    {
        if (! class_exists(FilamentAsset::class)) {
            return;
        }

        FilamentAsset::register([
            Css::make('filament-model-states', __DIR__ . '/../resources/css/filament-model-states.css'),
            Js::make('sortable', __DIR__ . '/../resources/js/sortable.min.js'),
            Js::make('kanban-board', __DIR__ . '/../resources/js/kanban-board.js'),
        ], package: 'filament-model-states');
    }
}
