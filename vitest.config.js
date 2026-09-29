import { defineConfig } from 'vitest/config';

// W11: Vitest covers pure JS logic only; browser flows use Pest browser tests.
export default defineConfig({
    test: {
        include: ['tests/js/**/*.test.js'],
        environment: 'node',
    },
});
