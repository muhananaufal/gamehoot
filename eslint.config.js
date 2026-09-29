import js from '@eslint/js';
import globals from 'globals';

export default [
    {
        ignores: ['vendor/**', 'node_modules/**', '.npm-cache/**', 'public/**', 'storage/**', 'bootstrap/cache/**'],
    },
    js.configs.recommended,
    {
        files: ['resources/js/**/*.js'],
        languageOptions: {
            globals: globals.browser,
        },
    },
    {
        files: ['*.config.js', 'tests/js/**/*.js'],
        languageOptions: {
            globals: globals.node,
        },
    },
    {
        rules: {
            eqeqeq: 'error',
            'no-console': ['error', { allow: ['warn', 'error'] }],
        },
    },
];
