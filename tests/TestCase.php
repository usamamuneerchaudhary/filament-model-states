<?php

declare(strict_types=1);

namespace Usamamuneerchaudhary\FilamentModelStates\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\ModelStatus\ModelStatusServiceProvider;
use Spatie\ModelStatus\Status;
use Usamamuneerchaudhary\FilamentModelStates\FilamentModelStatesServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ModelStatusServiceProvider::class,
            FilamentModelStatesServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('model-status.status_model', Status::class);
        $app['config']->set('cache.default', 'array');
    }
}
