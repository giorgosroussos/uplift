<?php

use Tests\Support\TranslationKeys;

it('finds keys in every laravel-vue-i18n helper', function () {
    $source = <<<'TS'
        $t('ui.a.one');
        trans("ui.a.two");
        wTrans('ui.a.three');
        $tChoice('ui.a.four', 2);
        transChoice('ui.a.five', 1);
        wTransChoice('ui.a.six', 3);
        const item = { title: 'ui.nav.dashboard' };
        TS;

    expect(TranslationKeys::usedIn($source))->toBe([
        'ui.a.five', 'ui.a.four', 'ui.a.one', 'ui.a.six', 'ui.a.three', 'ui.a.two', 'ui.nav.dashboard',
    ]);
});

it('treats a missing or empty value as unresolved', function () {
    $catalogue = ['ui.ok' => 'Fine', 'ui.empty' => ' ', 'ui.group' => ['nested' => 'x']];

    expect(TranslationKeys::missing(['ui.ok', 'ui.empty', 'ui.group', 'ui.absent'], $catalogue))
        ->toBe(['ui.empty', 'ui.group', 'ui.absent']);
});
