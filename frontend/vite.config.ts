import { sveltekit } from '@sveltejs/kit/vite';
import { defineConfig } from 'vitest/config';

export default defineConfig({
  plugins: [sveltekit()],
  resolve: process.env.VITEST ? { conditions: ['browser'] } : undefined,
  server: {
    host: '0.0.0.0',
    port: 5173,
    proxy: {
      '/api': process.env.API_PROXY_TARGET || 'http://localhost:8080',
    },
  },
  test: {
    include: ['src/**/*.{test,spec}.{js,ts}'],
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/vitest-setup.ts'],
    coverage: {
      provider: 'v8',
      include: ['src/lib/**'],
      exclude: ['src/lib/**/*.test.ts', 'src/lib/**/*.spec.ts'],
      reporter: ['text', 'json', 'html'],
      thresholds: {
        lines: 95,
      },
    },
  },
});
