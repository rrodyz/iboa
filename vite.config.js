import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ command }) => ({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        // Minification agressive en production
        minify: 'esbuild',
        // Pas de sourcemaps en production (réduit la taille ~30%)
        sourcemap: false,
        // Seuil d'avertissement chunk (1 Mo)
        chunkSizeWarningLimit: 1024,
        rollupOptions: {
            output: {
                // Code splitting : sépare les vendors des scripts page
                manualChunks(id) {
                    if (id.includes('node_modules/apexcharts')) return 'apexcharts';
                    if (id.includes('node_modules/@hotwired')) return 'turbo';
                    if (id.includes('node_modules/alpinejs')) return 'alpine';
                },
            },
        },
    },
    // Compression CSS (supprime commentaires, espaces)
    css: {
        devSourcemap: command === 'serve',
    },
}));
