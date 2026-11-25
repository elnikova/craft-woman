import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

// In Docker (compose.yaml sets VITE_DOCKER=1) the server must listen on all interfaces,
// and file changes from the Windows host only arrive by polling.
const inDocker = process.env.VITE_DOCKER === '1';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    // /images/... stay as public/ paths; Vite leaves them alone
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
        // Explicit IPv4: on Windows `localhost` may resolve to ::1, and then
        // the HMR websocket fails to connect and the Vite client reloads the page in a loop.
        host: inDocker ? '0.0.0.0' : '127.0.0.1',
        port: 5173,
        strictPort: true,
        hmr: { host: '127.0.0.1' },
        watch: {
            ignored: ['**/storage/framework/views/**', '**/database/*.sqlite*', '**/vendor/**'],
            usePolling: inDocker,
            interval: 300,
        },
    },
});
