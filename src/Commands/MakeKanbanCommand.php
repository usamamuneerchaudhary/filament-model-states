<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Usamamuneerchaudhary\FilamentModelStates\Support\StateConfigurationResolver;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class MakeKanbanCommand extends Command
{
    protected $signature = 'filament-model-states:make-kanban
        {model? : The Eloquent model, e.g. Order or App\\Models\\Order}
        {--state-field= : Explicit state attribute when auto-detection is ambiguous}
        {--resource= : Filament resource class to wire the board onto}
        {--detect : Only print the auto-detected state configuration}
        {--force : Overwrite an existing generated widget file}';

    protected $description = 'Detect workflow states on a model and scaffold a plug-and-play Filament Kanban board.';

    public function handle(StateConfigurationResolver $resolver): int
    {
        $modelClass = $this->resolveModelClass($this->argument('model'));

        if ($modelClass === null) {
            $this->components->error('Provide a model argument, e.g. `php artisan filament-model-states:make-kanban Order`.');

            return self::FAILURE;
        }

        try {
            $report = $resolver->inspect($modelClass, $this->option('state-field'));
        } catch (\Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->renderReport($report);

        if ($this->option('detect')) {
            $this->printUsageSnippet($modelClass, (string) $report['state_field']);

            return self::SUCCESS;
        }

        $widgetPath = $this->generateWidget($modelClass, (string) $report['state_field']);

        if ($widgetPath !== null) {
            info("Created widget: {$widgetPath}");
        }

        $resourceClass = $this->resolveResourceClass($this->option('resource'), $modelClass);

        if ($resourceClass !== null) {
            $this->wireResource($resourceClass, $modelClass);
        }

        $this->printUsageSnippet($modelClass, (string) $report['state_field']);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function renderReport(array $report): void
    {
        table(
            ['Setting', 'Value'],
            [
                ['Model', (string) $report['model']],
                ['State field', (string) $report['state_field']],
                ['Detection source', (string) $report['source']],
                ['Enum class', $report['enum'] ?? '—'],
                ['Status history', ($report['status_history'] ?? false) ? 'yes (HasStatuses)' : 'no'],
                ['Columns', implode(', ', (array) $report['columns'])],
                ['Transitions', is_array($report['transitions']) ? json_encode($report['transitions']) : (string) $report['transitions']],
            ],
        );
    }

    protected function printUsageSnippet(string $modelClass, string $stateField): void
    {
        $shortModel = class_basename($modelClass);
        $stateArg = $stateField !== $this->defaultStateFieldFor($modelClass)
            ? ", stateField: '{$stateField}'"
            : '';

        note('Plug-and-play (no custom widget file needed):');
        note("    ModelStateKanbanBoard::for({$shortModel}::class{$stateArg})");
        note('');
        note('Resource trait:');
        note('    use HasStateKanbanBoard;');
        note('    static::getStateKanbanBoard()');
    }

    protected function generateWidget(string $modelClass, string $stateField): ?string
    {
        $modelName = class_basename($modelClass);
        $widgetName = "{$modelName}KanbanBoard";
        $path = app_path("Filament/Widgets/{$widgetName}.php");

        if (File::exists($path) && ! $this->option('force')) {
            warning("Widget already exists: {$path} (use --force to overwrite)");

            return null;
        }

        File::ensureDirectoryExists(dirname($path));

        $stateProperty = $stateField !== $this->defaultStateFieldFor($modelClass)
            ? "    public ?string \$stateField = '{$stateField}';\n\n"
            : '';

        $contents = <<<PHP
<?php

namespace App\Filament\Widgets;

use {$modelClass};
use Usamamuneerchaudhary\FilamentModelStates\Widgets\ModelStateKanbanBoard;

/**
 * Auto-generated Kanban board for {$modelName}.
 * Columns and transitions are auto-detected from the model.
 */
class {$widgetName} extends ModelStateKanbanBoard
{
{$stateProperty}    public string \$modelClass = {$modelName}::class;
}

PHP;

        File::put($path, $contents);

        return $path;
    }

    protected function wireResource(string $resourceClass, string $modelClass): void
    {
        $listPageClass = $this->guessListPageClass($resourceClass);

        if ($listPageClass === null) {
            warning('Could not find a ListRecords page. Wire the widget manually.');

            return;
        }

        note("Register on list page [{$listPageClass}]:");
        note('    '.class_basename($modelClass).'Resource::getStateKanbanBoard()');
    }

    protected function resolveModelClass(?string $model): ?string
    {
        if (blank($model)) {
            return null;
        }

        $model = ltrim($model, '\\');

        if (class_exists($model)) {
            return $model;
        }

        $candidate = 'App\\Models\\'.$model;

        return class_exists($candidate) ? $candidate : null;
    }

    protected function resolveResourceClass(?string $resource, string $modelClass): ?string
    {
        if (filled($resource)) {
            $resource = ltrim($resource, '\\');

            if (class_exists($resource)) {
                return $resource;
            }

            $candidate = 'App\\Filament\\Resources\\'.$resource;

            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        $modelName = class_basename($modelClass);
        $candidates = [
            "App\\Filament\\Resources\\{$modelName}s\\{$modelName}Resource",
            "App\\Filament\\Resources\\{$modelName}\\{$modelName}Resource",
            "App\\Filament\\Resources\\Orders\\{$modelName}Resource",
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function guessListPageClass(string $resourceClass): ?string
    {
        if (! method_exists($resourceClass, 'getPages')) {
            return null;
        }

        $pages = $resourceClass::getPages();
        $index = $pages['index'] ?? null;

        if (is_string($index) && class_exists($index)) {
            return $index;
        }

        return null;
    }

    protected function defaultStateFieldFor(string $modelClass): string
    {
        try {
            return app(StateConfigurationResolver::class)->detectStateField($modelClass);
        } catch (\Throwable) {
            return 'state';
        }
    }
}
