import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'vendor/resma/filament-awin-theme/resources/css/theme.css',
                'vendor/andreia/filament-nord-theme/resources/css/theme.css',
                'resources/css/custom.css',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
