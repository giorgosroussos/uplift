<?php

use Symfony\Component\Process\Process;
use Tests\Support\TranslationKeys;

/*
| Phase 1: every UI string resolves from lang/el and lang/en (SCOPE-020).
*/

it('ships exactly the Greek and English locales', function () {
    $locales = array_map('basename', glob(lang_path('*'), GLOB_ONLYDIR) ?: []);
    sort($locales);

    expect($locales)->toBe(['el', 'en']);
});

it('keeps the same translation keys in lang/el and lang/en', function () {
    $en = TranslationKeys::catalogue(lang_path(), 'en');
    $el = TranslationKeys::catalogue(lang_path(), 'el');

    expect($en)->not->toBeEmpty()
        ->and(array_keys($el))->toBe(array_keys($en));

    foreach ($en as $key => $value) {
        if (is_string($value)) {
            expect($el[$key])->toBeString()->not->toBe('', "lang/el is empty for {$key}");
        }
    }
});

it('resolves every key used by the frontend in both locales', function () {
    $used = TranslationKeys::usedInDirectory(resource_path('js'));

    expect($used)->not->toBeEmpty();

    foreach (['en', 'el'] as $locale) {
        $catalogue = TranslationKeys::catalogue(lang_path(), $locale);

        foreach ($used as $file => $keys) {
            expect(TranslationKeys::missing($keys, $catalogue))
                ->toBe([], "{$file} uses keys missing from lang/{$locale}");
        }
    }
});

it('reports a key that is missing from a catalogue', function () {
    $keys = TranslationKeys::usedIn(<<<'VUE'
        <template>
            <h1>{{ $t('ui.nav.dashboard') }}</h1>
            <p>{{ $t('ui.does_not.exist') }}</p>
        </template>
        VUE);

    expect($keys)->toBe(['ui.does_not.exist', 'ui.nav.dashboard'])
        ->and(TranslationKeys::missing($keys, TranslationKeys::catalogue(lang_path(), 'en')))
        ->toBe(['ui.does_not.exist'])
        ->and(TranslationKeys::missing($keys, TranslationKeys::catalogue(lang_path(), 'el')))
        ->toBe(['ui.does_not.exist']);
});

it('rejects hardcoded strings in Vue templates through ESLint', function () {
    $lint = fn (string $template): Process => tap(
        new Process(
            ['npx', 'eslint', '--max-warnings=0', '--stdin', '--stdin-filename', 'resources/js/pages/LintProbe.vue'],
            base_path(),
            timeout: 120,
        ),
        fn (Process $process) => $process->setInput("<template>\n    {$template}\n</template>\n")->run(),
    );

    $hardcoded = $lint('<p>Hello world</p>');
    expect($hardcoded->getExitCode())->not->toBe(0)
        ->and($hardcoded->getOutput())->toContain('vue/no-bare-strings-in-template');

    $translated = $lint("<p>{{ \$t('ui.nav.dashboard') }}</p>");
    expect($translated->getExitCode())->toBe(0, $translated->getOutput());
});
