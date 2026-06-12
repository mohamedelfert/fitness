<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the modular monolith (SYSTEM_ARCHITECTURE.md §5 / BLUEPRINT.md §2).
 * Each bounded context lives under modules/<Name>/ with its own
 * Database/Migrations and Routes/api.php. This provider discovers and
 * registers them so modules stay self-contained.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Resolve factories for module models:
        // Modules\Training\Models\Exercise => Modules\Training\Database\Factories\ExerciseFactory
        Factory::guessFactoryNamesUsing(function (string $modelName) {
            if (str_starts_with($modelName, 'Modules\\')) {
                return str_replace('\\Models\\', '\\Database\\Factories\\', $modelName).'Factory';
            }

            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });

        // Auto-register each module's own service provider (gates, policies, bindings):
        // modules/<X>/Providers/<X>ServiceProvider.php => Modules\<X>\Providers\<X>ServiceProvider
        $base = base_path('modules/');
        foreach (glob($base.'*/Providers/*ServiceProvider.php') ?: [] as $file) {
            $class = 'Modules\\'.str_replace(['/', '.php'], ['\\', ''], substr($file, strlen($base)));
            if (class_exists($class)) {
                $this->app->register($class);
            }
        }
    }

    public function boot(): void
    {
        // Migrations: modules/*/Database/Migrations
        foreach (glob(base_path('modules/*/Database/Migrations'), GLOB_ONLYDIR) ?: [] as $path) {
            $this->loadMigrationsFrom($path);
        }

        // API routes: modules/*/Routes/api.php, all under /v1 with the `api` middleware group.
        foreach (glob(base_path('modules/*/Routes/api.php')) ?: [] as $routeFile) {
            Route::middleware('api')->prefix('v1')->group($routeFile);
        }
    }
}
