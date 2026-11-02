import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';

// @alpinejs/csp reads every property of window when it loads, to block
// expressions from reaching globals. Reading the window geometry getters
// (innerWidth, scrollY...) forces a synchronous layout, about 85 ms on a
// throttled phone, and reading window.fence logs a deprecation warning in
// Chrome 156+, which fails Lighthouse's Best Practices. All of them hold
// numbers or null, which Alpine's check ignores, so skipping them changes
// nothing it blocks.
const alpineCspSkippedGlobals = [
    'styleMedia', 'sharedStorage', 'fence',
    'innerWidth', 'innerHeight', 'outerWidth', 'outerHeight',
    'scrollX', 'scrollY', 'pageXOffset', 'pageYOffset',
    'screenX', 'screenY', 'screenLeft', 'screenTop', 'devicePixelRatio',
];

function alpineCspGlobalScan() {
    const original = 'if (key === "styleMedia" || key === "sharedStorage")';

    return {
        name: 'spu-alpine-csp-global-scan',
        transform(code, id) {
            if (!id.replace(/\\/g, '/').includes('/@alpinejs/csp/dist/')) return null;

            if (!code.includes(original)) {
                this.warn('@alpinejs/csp no longer has the global scan this plugin patches; check vite.config.js.');

                return null;
            }

            return { code: code.replace(original, `if (${JSON.stringify(alpineCspSkippedGlobals)}.includes(key))`), map: null };
        },
    };
}

export default defineConfig({
    plugins: [
        alpineCspGlobalScan(),
        laravel({
            input: ['resources/css/app.css', 'resources/css/filament/admin.css', 'resources/js/app.js', 'resources/js/filament/admin.js', 'resources/js/staffIntake.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        cssTarget: ['chrome99', 'firefox102', 'ios15.4', 'safari15.4'],
        rollupOptions: {
            output: {
                assetFileNames: 'assets/[name].[hash][extname]',
                chunkFileNames: 'js/[name].[hash].js',
                entryFileNames: 'js/[name].[hash].js',
            },
        },
        chunkSizeWarningLimit: 600,
    },
    resolve: {
        alias: {
            '@fonts': path.resolve(__dirname, 'public/fonts'),
            '@images': path.resolve(__dirname, 'public/images'),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
