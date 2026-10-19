import prettier from 'eslint-config-prettier';
import vue from 'eslint-plugin-vue';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    {
        ignores: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'storage/**',
            'resources/js/actions/**',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
            'design/**',
            'build/**',
            'tools/**',
        ],
    },
    ...tseslint.configs.recommended,
    ...vue.configs['flat/recommended'],
    {
        files: ['**/*.vue'],
        languageOptions: {
            parserOptions: {
                parser: tseslint.parser,
                extraFileExtensions: ['.vue'],
                sourceType: 'module',
            },
        },
    },
    {
        files: ['resources/js/**/*.{ts,vue}'],
        rules: {
            'vue/multi-word-component-names': 'off',
            'vue/require-default-prop': 'off',
            '@typescript-eslint/no-explicit-any': 'off',
            '@typescript-eslint/consistent-type-imports': [
                'error',
                { prefer: 'type-imports', fixStyle: 'separate-type-imports' },
            ],
            // SCOPE-020: every UI string comes from a translation key.
            'vue/no-bare-strings-in-template': [
                'error',
                {
                    allowlist: [
                        '(',
                        ')',
                        ',',
                        '.',
                        '&',
                        '+',
                        '-',
                        '=',
                        '*',
                        '/',
                        '#',
                        '%',
                        '!',
                        '?',
                        ':',
                        '[',
                        ']',
                        '{',
                        '}',
                        '<',
                        '>',
                        '·',
                        '•',
                        '‐',
                        '–',
                        '—',
                        '−',
                        '|',
                    ],
                    attributes: {
                        '/.+/': [
                            'title',
                            'aria-label',
                            'aria-placeholder',
                            'aria-roledescription',
                            'aria-valuetext',
                        ],
                        input: ['placeholder'],
                        img: ['alt'],
                    },
                    directives: ['v-text'],
                },
            ],
        },
    },
    prettier,
);
