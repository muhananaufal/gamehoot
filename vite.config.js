import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import { fontsource } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const appUrl = env.APP_URL ?? 'http://localhost:8000';

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
                // Self-hosted from the locked npm packages: the venue network may be poor.
                fonts: [
                    fontsource('Figtree', {
                        alias: 'figtree',
                        weights: [400, 500, 600, 700],
                        preload: [{ weight: 400 }, { weight: 700 }],
                        fallbacks: ['system-ui', 'sans-serif'],
                    }),
                    fontsource('Fredoka', {
                        alias: 'fredoka',
                        weights: [500, 600, 700],
                        preload: [{ weight: 700 }],
                        fallbacks: ['system-ui', 'sans-serif'],
                    }),
                ],
            }),
            tailwindcss(),
        ],
        server: {
            // The dev server runs in the node container (compose.yaml); the browser reaches it on localhost.
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            origin: 'http://localhost:5173',
            cors: { origin: appUrl },
            hmr: { host: 'localhost' },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
