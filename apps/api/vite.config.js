import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // Allow importing packages/design-tokens across the monorepo (dev server only;
        // `vite build` resolves the relative @import without this).
        fs: {
            allow: ['.', '../../..'],
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
