/**
 * AuthShell — a custom demo shell for the chromeless login screen.
 *
 * The free BasicShell always renders the app sidebar + nav; ImmersiveShell adds
 * a close (X) button. Neither fits a login entry point, so the demo registers
 * its own shell via the lib's `registerShell` seam (the shell-level twin of the
 * custom `chart` block and `sparkline` cell). No @middag-io/react edits — the
 * page contract just emits `shell('auth')` and the resolver renders this.
 *
 * It reads the page title/subtitle from the Inertia contract (like the lib
 * shells do) and centers the rendered layout (the login form_panel) in a card.
 */

import type { ReactElement, ReactNode } from "react";
import { usePage } from "@inertiajs/react";

interface AuthPageMeta {
  title?: string;
  subtitle?: string;
}

export function AuthShell({ children }: { children: ReactNode }): ReactElement {
  const { props } = usePage<{ contract?: { page?: AuthPageMeta } }>();
  const page = props.contract?.page ?? {};

  return (
    <div className="bg-muted/40 text-foreground flex min-h-screen flex-col items-center justify-center gap-6 p-4">
      <div className="w-full max-w-sm">
        <div className="mb-6 text-center">
          <div className="text-lg font-semibold tracking-tight">MIDDAG</div>
          <div className="text-muted-foreground text-sm">Help-desk reference demo</div>
        </div>

        <div className="bg-card border-border rounded-xl border p-6 shadow-sm">
          {page.title && <h1 className="text-foreground text-base font-semibold">{page.title}</h1>}
          {page.subtitle && (
            <p className="text-muted-foreground mb-2 mt-1 text-xs">{page.subtitle}</p>
          )}
          {children}
        </div>

        <p className="text-muted-foreground mt-4 text-center text-xs">
          Demo credentials: <code className="font-mono">demo@middag.io</code> /{" "}
          <code className="font-mono">middag</code>
        </p>
      </div>
    </div>
  );
}
