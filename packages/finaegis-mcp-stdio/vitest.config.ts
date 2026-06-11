import { defineConfig } from 'vitest/config';

// Without a local config, vitest walks up the tree and loads the monorepo
// root vite.config.js — which imports the root `vite` package that a
// package-scoped `npm ci` never installs (breaks packages-ci and the
// mcp-release test step).
export default defineConfig({
  // Inline (empty) PostCSS config — without it, vite's CSS pipeline searches
  // upward and loads the monorepo root postcss.config.js, which requires
  // tailwindcss that a package-scoped `npm ci` never installs.
  css: {
    postcss: {
      plugins: [],
    },
  },
  test: {
    include: ['test/**/*.test.ts'],
  },
});
