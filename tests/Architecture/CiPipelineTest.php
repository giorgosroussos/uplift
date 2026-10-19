<?php

use Symfony\Component\Yaml\Yaml;

/*
| Phase 1: GitHub Actions runs every operations.ci stage on each pull request
| and push to main (OPS-004).
*/

/**
 * @return array<string, mixed>
 */
function appWorkflow(): array
{
    /** @var array<string, mixed> $workflow */
    $workflow = Yaml::parseFile(base_path('.github/workflows/app.yml'));

    return $workflow;
}

/**
 * Commands each operations.ci stage must run, keyed by the stage name in
 * design/model/operations.yaml.
 *
 * @return array<string, list<string>>
 */
function ciStageCommands(): array
{
    return [
        'lint and format (Pint, ESLint, Prettier)' => ['vendor/bin/pint --test', 'npm run lint', 'npm run format:check'],
        'static analysis (Larastan)' => ['vendor/bin/phpstan analyse'],
        'unit and feature tests (Pest, Vitest)' => ['vendor/bin/pest --testsuite=Unit,Feature', 'npm run test'],
        'browser tests (Pest browser)' => ['vendor/bin/pest --testsuite=Browser'],
        'architecture tests (deptrac)' => ['vendor/bin/deptrac analyse', 'php scripts/generate-deptrac.php --check'],
        'design check (tools/design/ci_check.py)' => ['python tools/design/ci_check.py'],
        'dependency audit (composer audit, npm audit)' => ['composer audit', 'npm audit'],
    ];
}

it('triggers on every pull request and on pushes to main', function () {
    // The YAML 1.1 key "on" parses as boolean true.
    $on = appWorkflow()['on'] ?? appWorkflow()[true] ?? null;

    expect($on)->toBeArray()
        ->and($on)->toHaveKey('pull_request')
        ->and($on['pull_request'] ?? null)->toBeNull()
        ->and($on['push']['branches'] ?? [])->toContain('main');
});

/**
 * The stages of design/model/operations.yaml. The design writes them as a
 * YAML flow sequence, so "lint and format (Pint, ESLint, Prettier)" arrives
 * split at its commas; fragments are rejoined until parentheses balance.
 *
 * @return list<string>
 */
function designCiStages(): array
{
    /** @var array{operations: array{ci: array{stages: list<string>}}} $operations */
    $operations = Yaml::parseFile(base_path('design/model/operations.yaml'));

    $stages = [];
    $pending = null;
    foreach ($operations['operations']['ci']['stages'] as $fragment) {
        $pending = $pending === null ? $fragment : $pending.', '.$fragment;
        if (substr_count($pending, '(') === substr_count($pending, ')')) {
            $stages[] = $pending;
            $pending = null;
        }
    }

    return $stages;
}

it('has a job for every operations.ci stage of the design', function () {
    $jobNames = array_column(appWorkflow()['jobs'], 'name');

    expect(designCiStages())->toHaveCount(7)
        ->and(array_keys(ciStageCommands()))->toBe(designCiStages());

    foreach (designCiStages() as $stage) {
        expect($jobNames)->toContain($stage);
    }
});

it('runs the commands of each stage in its job', function () {
    $jobs = collect(appWorkflow()['jobs'])->keyBy('name');

    foreach (ciStageCommands() as $stage => $commands) {
        $script = collect($jobs[$stage]['steps'])->pluck('run')->filter()->implode("\n");

        foreach ($commands as $command) {
            expect($script)->toContain($command);
        }
    }
});

it('never skips a stage conditionally', function () {
    foreach (appWorkflow()['jobs'] as $id => $job) {
        expect($job)->not->toHaveKey('if', "job {$id} must always run")
            ->and($job)->not->toHaveKey('continue-on-error');

        foreach ($job['steps'] as $step) {
            expect($step)->not->toHaveKey('continue-on-error');
        }
    }
});
