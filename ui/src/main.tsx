/**
 * Dev-server entry (`npm run dev`). Mounts the standalone dev app on #root.
 *
 * Registration is the SAME module production uses (./app/register), so the dev
 * preview and the built host bundle (entry-custom.tsx → build:host) render
 * identically — register a block once, see it in both. The @inertiajs/* imports
 * the lib makes are aliased to local mock adapters by vite.config.ts.
 */
import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "@middag-io/react/style.css";
import "./theme.css";
import "@fontsource-variable/figtree";
import { registerDefaults } from "./app/register";
import { App } from "./app";

registerDefaults();

const el = document.getElementById("root");
if (!el) throw new Error("#root not found — check index.html");

createRoot(el).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
