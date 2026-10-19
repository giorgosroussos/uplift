import { flushPromises, mount } from '@vue/test-utils';
import { i18nVue, loadLanguageAsync } from 'laravel-vue-i18n';
import { describe, expect, it } from 'vitest';
import { defineComponent, h } from 'vue';
import { resolveLanguage, SUPPORTED_LOCALES } from '@/lib/i18n';

const Probe = defineComponent({
    render() {
        return h('span', this.$t('ui.nav.log_out'));
    },
});

async function render(lang: string): Promise<string> {
    const wrapper = mount(Probe, {
        global: {
            plugins: [
                [
                    i18nVue,
                    { lang, fallbackLang: 'en', resolve: resolveLanguage },
                ],
            ],
        },
    });

    await loadLanguageAsync(lang);
    await flushPromises();

    return wrapper.text();
}

describe('i18n', () => {
    it('supports exactly Greek and English (SCOPE-020)', () => {
        expect([...SUPPORTED_LOCALES].sort()).toEqual(['el', 'en']);
    });

    it('resolves UI strings from lang/en', async () => {
        expect(await render('en')).toBe('Log out');
    });

    it('resolves UI strings from lang/el', async () => {
        expect(await render('el')).toBe('Αποσύνδεση');
    });

    it('returns an empty catalogue for an unknown locale', async () => {
        expect(await resolveLanguage('xx')).toEqual({ default: {} });
    });
});
