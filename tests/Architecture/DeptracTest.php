<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/*
| Phase 1: deptrac fails when a module depends on a module outside its
| may_depend_on (ARCH-004). The rules are generated from the design.
*/

require_once dirname(__DIR__, 2).'/scripts/generate-deptrac.php';

function runDeptrac(string $configFile): Process
{
    $process = new Process(
        [PHP_BINARY, base_path('vendor/bin/deptrac'), 'analyse', '--config-file='.$configFile, '--no-progress', '--no-cache'],
        base_path(),
        timeout: 300,
    );
    $process->run();

    return $process;
}

/**
 * Writes PHP classes into a fresh directory and a deptrac config with the
 * real module rules pointing at it.
 *
 * @param  array<string, string>  $classes  relative path => PHP source
 */
function deptracFixture(array $classes): string
{
    $dir = sys_get_temp_dir().'/uplift-deptrac-'.bin2hex(random_bytes(6));
    foreach ($classes as $path => $source) {
        File::ensureDirectoryExists(dirname($dir.'/src/'.$path));
        File::put($dir.'/src/'.$path, $source);
    }

    $rules = uplift_module_rules(base_path('design/model/architecture.yaml'));
    File::put($dir.'/deptrac.yaml', uplift_deptrac_yaml($rules, [$dir.'/src']));

    return $dir;
}

it('generates deptrac.yaml from may_depend_on and keeps it up to date', function () {
    $check = new Process([PHP_BINARY, base_path('scripts/generate-deptrac.php'), '--check'], base_path());
    $check->run();

    expect($check->getExitCode())->toBe(0, $check->getErrorOutput());
});

it('has one deptrac layer and rule set per design component', function () {
    $rules = uplift_module_rules(base_path('design/model/architecture.yaml'));

    expect($rules)->toHaveCount(11)
        ->and($rules['Tenancy'])->toBe([])
        ->and($rules['Identity'])->toBe(['Tenancy'])
        ->and($rules['Audit'])->toBe(['Tenancy', 'Identity', 'Platform', 'Courses', 'Activities', 'Assessment']);
});

it('passes for the application modules', function () {
    $result = runDeptrac(base_path('deptrac.yaml'));

    expect($result->getExitCode())->toBe(0, $result->getOutput());
});

it('fails when a module depends on a module outside its may_depend_on', function () {
    $dir = deptracFixture([
        'Identity/Models/User.php' => "<?php\nnamespace App\\Modules\\Identity\\Models;\nclass User {}\n",
        // CMP-Tenancy may depend on no other component.
        'Tenancy/Actions/LeakIdentity.php' => "<?php\nnamespace App\\Modules\\Tenancy\\Actions;\nuse App\\Modules\\Identity\\Models\\User;\nclass LeakIdentity { public function __invoke(User \$user): void {} }\n",
    ]);

    try {
        $result = runDeptrac($dir.'/deptrac.yaml');

        expect($result->getExitCode())->not->toBe(0)
            ->and($result->getOutput())->toContain('Tenancy')
            ->and($result->getOutput())->toContain('Identity');
    } finally {
        File::deleteDirectory($dir);
    }
});

it('allows a dependency listed in may_depend_on', function () {
    $dir = deptracFixture([
        'Tenancy/Models/Organization.php' => "<?php\nnamespace App\\Modules\\Tenancy\\Models;\nclass Organization {}\n",
        // CMP-Identity may depend on CMP-Tenancy.
        'Identity/Actions/JoinOrganization.php' => "<?php\nnamespace App\\Modules\\Identity\\Actions;\nuse App\\Modules\\Tenancy\\Models\\Organization;\nclass JoinOrganization { public function __invoke(Organization \$organization): void {} }\n",
    ]);

    try {
        $result = runDeptrac($dir.'/deptrac.yaml');

        expect($result->getExitCode())->toBe(0, $result->getOutput());
    } finally {
        File::deleteDirectory($dir);
    }
});
