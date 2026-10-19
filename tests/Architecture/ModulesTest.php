<?php

use App\Support\Modules\ModuleServiceProvider;
use Symfony\Component\Yaml\Yaml;

/*
| Every component of design/model/architecture.yaml is a module in
| app/Modules/<Name>/ with its own registered service provider.
*/

/**
 * @return list<string>
 */
function designModules(): array
{
    /** @var array{components: list<array{id: string}>} $architecture */
    $architecture = Yaml::parseFile(dirname(__DIR__, 2).'/design/model/architecture.yaml');

    return array_map(
        static fn (array $component): string => substr($component['id'], strlen('CMP-')),
        $architecture['components'],
    );
}

it('has a module for each of the 11 design components', function () {
    $modules = designModules();
    $directories = array_map('basename', glob(app_path('Modules/*'), GLOB_ONLYDIR) ?: []);

    sort($directories);
    $expected = $modules;
    sort($expected);

    expect($modules)->toHaveCount(11)
        ->and($directories)->toBe($expected);
});

it('gives every module the conventional skeleton', function (string $module) {
    foreach (['Actions', 'Models', 'Policies', 'Events', 'Listeners', 'Http', 'Database/Migrations'] as $dir) {
        expect(is_dir(app_path("Modules/{$module}/{$dir}")))->toBeTrue("{$module}/{$dir} is missing");
    }
})->with(designModules(...));

it('registers a module service provider for every module', function (string $module) {
    $provider = "App\\Modules\\{$module}\\{$module}ServiceProvider";

    expect(class_exists($provider))->toBeTrue()
        ->and(is_subclass_of($provider, ModuleServiceProvider::class))->toBeTrue()
        ->and(require base_path('bootstrap/providers.php'))->toContain($provider)
        ->and(app()->getProvider($provider))->not->toBeNull();
})->with(designModules(...));

it('points each module provider at its own directory', function (string $module) {
    $provider = app()->getProvider("App\\Modules\\{$module}\\{$module}ServiceProvider");

    expect($provider)->toBeInstanceOf(ModuleServiceProvider::class)
        ->and($provider->modulePath())->toBe(app_path("Modules/{$module}"));
})->with(designModules(...));
