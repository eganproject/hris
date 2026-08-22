import { defineConfig } from 'vite';
import frameworkPlugin from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        frameworkPlugin({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/attendance-map.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
