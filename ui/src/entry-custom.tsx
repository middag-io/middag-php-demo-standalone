/**
 * Production entry point for custom host.
 *
 * Uses real createInertiaApp — the host platform (your backend)
 * serves the HTML and Inertia page props. This file is the build target
 * for `npm run build:host`.
 *
 * NOT used by `npm run dev` — that uses src/main.tsx with mock adapters.
 */
import { createRoot } from "react-dom/client";
import { createInertiaApp } from "@inertiajs/react";
import { I18nProvider, ProgressProvider } from "@middag-io/react";
import "@middag-io/react/style.css";
import "./theme.css";
import { registerDefaults } from "./app/register";
import { resolvePageComponent } from "./app/page-resolver";

registerDefaults();

createInertiaApp({
  id: "middag-app",
  resolve: (name) => resolvePageComponent(name),
  setup({ el, App, props }) {
    el.classList.add("middag-root");

    createRoot(el).render(
      <ProgressProvider>
        <App {...props}>
          {({ Component, props: pageProps, key }) => (
            <I18nProvider>
              <Component key={key} {...pageProps} />
            </I18nProvider>
          )}
        </App>
      </ProgressProvider>,
    );
  },
});
