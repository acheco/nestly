import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { fileURLToPath } from 'node:url';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    // Vitest only runs pure-function tests (spec 001, R13), so it skips the app's plugins.
    // laravel-vite-plugin also refuses to start a Vite server when CI is set.
    plugins: process.env.VITEST
        ? []
        : lazyPlugins(() => [
              laravel({
                  input: ['resources/css/app.css', 'resources/js/app.ts'],
                  refresh: true,
                  fonts: [
                      bunny('Instrument Sans', {
                          weights: [400, 500, 600],
                      }),
                  ],
              }),
              inertia(),
              tailwindcss(),
              vue({
                  template: {
                      transformAssetUrls: {
                          base: null,
                          includeAbsolute: false,
                      },
                  },
              }),
              wayfinder({
                  formVariants: true,
              }),
          ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    test: {
        include: ['tests/js/**/*.test.ts'],
        environment: 'node',
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            // Rewritten by `boost:update` after every `composer update`.
            'boost.json',
            'CLAUDE.md',
            'composer.json',
            'docs/**',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
            'specs/foundation/decisions.md',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            entryPoint: 'resources/css/app.css',
        },
    },
});
