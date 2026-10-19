/**
 * Translation loading for laravel-vue-i18n (SCOPE-020).
 *
 * UI strings live in lang/en/*.php and lang/el/*.php. The laravel-vue-i18n
 * Vite plugin compiles them into lang/php_<locale>.json at build time; the
 * plugin asks for both "<locale>" and "php_<locale>".
 */
export const SUPPORTED_LOCALES = ['el', 'en'] as const;

export type Locale = (typeof SUPPORTED_LOCALES)[number];

type Messages = Record<string, string>;

const files = import.meta.glob<{ default: Messages }>('../../../lang/*.json');

export async function resolveLanguage(
    lang: string,
): Promise<{ default: Messages }> {
    const loader = files[`../../../lang/${lang}.json`];

    return loader ? await loader() : { default: {} };
}
