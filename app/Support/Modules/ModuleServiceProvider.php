<?php

namespace App\Support\Modules;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Base service provider of a module in app/Modules/<Name>/.
 *
 * A module registers its own migrations (Database/Migrations), web routes
 * (routes.php), broadcast channels (channels.php) and event listeners
 * ($listen) here, so modules never edit shared files (ARCH-003).
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Domain event => listeners of this module.
     *
     * @var array<class-string, list<class-string>>
     */
    protected array $listen = [];

    public function boot(): void
    {
        $path = $this->modulePath();

        if (is_dir($path.'/Database/Migrations')) {
            $this->loadMigrationsFrom($path.'/Database/Migrations');
        }

        if (is_file($path.'/routes.php') && ! $this->app->routesAreCached()) {
            Route::middleware('web')->group($path.'/routes.php');
        }

        if (is_file($path.'/channels.php')) {
            require $path.'/channels.php';
        }

        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    /**
     * Absolute path of the module directory (where its provider lives).
     */
    public function modulePath(): string
    {
        return dirname((string) (new ReflectionClass($this))->getFileName());
    }

    /**
     * @return array<class-string, list<class-string>>
     */
    public function listeners(): array
    {
        return $this->listen;
    }
}
