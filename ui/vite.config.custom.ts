/**
 * Vite build config for the custom host (PHP) — production build target.
 *
 * Usage:
 *   npm run build:host     → single build
 *   npm run watch:host     → rebuild on change
 *
 * Builds src/entry-custom.tsx as an APP entry (side effects: registerDefaults +
 * createInertiaApp), NOT a library — so the bundle self-executes when the PHP
 * shell loads `<script type="module" src="/build/app.js">`. `base: '/build/'`
 * scopes the (lazy) chunk import URLs to where the assets are served. The dev
 * server (`npm run dev`) uses vite.config.ts instead.
 */
import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

import { resolve } from "path";

export default defineConfig({
  plugins: [react()],
  define: { "process.env.NODE_ENV": JSON.stringify("production") },
  resolve: { alias: { "@/": resolve(__dirname, "src") + "/" } },
  // The PHP shell serves assets from `/build/`; chunk imports resolve there.
  base: "/build/",
  build: {
    // The PHP shell loads `/build/app.js` (+ `/build/style.css`), i.e.
    // `public/build/` under the doc root. Build straight there (gitignored).
    outDir: resolve(__dirname, "../public/build"),
    emptyOutDir: true,
    cssCodeSplit: false,
    rollupOptions: {
      input: resolve(__dirname, "src/entry-custom.tsx"),
      output: {
        // Fixed entry filename the shell references; chunks/assets keep hashes.
        entryFileNames: "app.js",
        chunkFileNames: "[name]-[hash].js",
        assetFileNames: (assetInfo) =>
          assetInfo.name?.endsWith(".css") ? "style.css" : "[name]-[hash][extname]",
      },
    },
  },
});
