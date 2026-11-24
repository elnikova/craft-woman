import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    // /images/... лишаються шляхами з public/, Vite їх не чіпає
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        // IPv4 явно: на Windows `localhost` може резолвитися в ::1, і тоді
        // вебсокет HMR не під'єднується, а клієнт Vite перезавантажує сторінку по колу.
        host: '127.0.0.1',
        hmr: { host: '127.0.0.1' },
        watch: {
            ignored: ['**/storage/framework/views/**', '**/database/*.sqlite*'],
        },
    },
});
