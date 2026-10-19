import { fileURLToPath, URL } from 'node:url';
import vue from '@vitejs/plugin-vue';
import i18n from 'laravel-vue-i18n/vite';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [vue(), i18n()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.ts'],
    },
});
