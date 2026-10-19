<?php

namespace Tests\Support;

use Illuminate\Support\Arr;
use Symfony\Component\Finder\Finder;

/**
 * Finds translation keys used by the frontend and compares the Greek and
 * English catalogues (SCOPE-020).
 */
final class TranslationKeys
{
    /**
     * Matches $t('key'), trans('key'), wTrans('key'), $tChoice('key', n),
     * transChoice('key', n) and wTransChoice('key', n), and literal keys such
     * as title: 'ui.nav.dashboard' given to layouts and navigation items.
     */
    private const CALL = '/(?:\$t|\btrans|\bwTrans|\$tChoice|\btransChoice|\bwTransChoice)\(\s*([\'"])([^\'"]+)\1/';

    private const LITERAL_KEY = '/([\'"])((?:ui)\.[a-z0-9_]+(?:\.[a-z0-9_]+)+)\1/';

    /**
     * @return list<string>
     */
    public static function usedIn(string $source): array
    {
        preg_match_all(self::CALL, $source, $calls);
        preg_match_all(self::LITERAL_KEY, $source, $literals);

        $keys = array_unique([...$calls[2], ...$literals[2]]);
        sort($keys);

        return array_values($keys);
    }

    /**
     * @return array<string, list<string>> relative file => keys used in it
     */
    public static function usedInDirectory(string $directory): array
    {
        $finder = Finder::create()->files()->in($directory)
            ->name(['*.vue', '*.ts'])
            ->notName('*.test.ts')
            ->exclude(['actions', 'routes', 'wayfinder']);

        $used = [];
        foreach ($finder as $file) {
            $keys = self::usedIn($file->getContents());
            if ($keys !== []) {
                $used[$file->getRelativePathname()] = $keys;
            }
        }
        ksort($used);

        return $used;
    }

    /**
     * Flattened "file.key" => value pairs of lang/<locale>/*.php.
     *
     * @return array<string, mixed>
     */
    public static function catalogue(string $langPath, string $locale): array
    {
        $entries = [];
        foreach (glob($langPath.'/'.$locale.'/*.php') ?: [] as $file) {
            $group = basename($file, '.php');
            $lines = require $file;
            foreach (Arr::dot(is_array($lines) ? $lines : []) as $key => $value) {
                $entries[$group.'.'.$key] = $value;
            }
        }
        ksort($entries);

        return $entries;
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, mixed>  $catalogue
     * @return list<string> keys that do not resolve to a non-empty string
     */
    public static function missing(array $keys, array $catalogue): array
    {
        return array_values(array_filter(
            $keys,
            static fn (string $key): bool => ! is_string($catalogue[$key] ?? null) || trim($catalogue[$key]) === '',
        ));
    }
}
