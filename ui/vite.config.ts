/**
 * Vite config — used by `npm run dev` and `npm run build`.
 *
 * Inertia aliases redirect @inertiajs/* to mock adapters so the
 * dev server works standalone. In production, the real @inertiajs
 * packages handle routing and page resolution.
 */
import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import { resolve } from "path";

export default defineConfig({
  plugins: [react()],
  server: { port: 5176 },
  optimizeDeps: {
    exclude: ["@middag-io/react"],
  },
  resolve: {
    alias: {
      "@/": resolve(__dirname, "src") + "/",
      "@mock/": resolve(__dirname, "mock") + "/",
      // FREE: Inertia mocks from local self-contained adapters
      "@inertiajs/react": resolve(__dirname, "mock/adapters/inertia-react.ts"),
      "@inertiajs/core": resolve(__dirname, "mock/adapters/inertia-core.ts"),
    },
  },
});
