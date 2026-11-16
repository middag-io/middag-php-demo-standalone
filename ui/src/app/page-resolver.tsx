import type { EditablePanelConfig, PageContract } from "@middag-io/react";
import { ContractPage, EditablePanelProvider, InlineEditablePanel } from "@middag-io/react";
import { usePage } from "@inertiajs/react";
import type { ComponentType } from "react";

// Direct pages — eager loaded (custom React pages in pages/)
const directPages = import.meta.glob("../pages/**/*.tsx", { eager: true }) as Record<
  string,
  { default: ComponentType }
>;

/**
 * Fallback component — reads PageContract from Inertia props and renders
 * it via the lib's ContractPage. Used when no direct page matches.
 *
 * No free shell mounts the editable side panel, so the demo does: when the page
 * ships an `editablePanel` config, the free provider wraps the page (a dense
 * table's `panel` row action opens it) and InlineEditablePanel draws the drawer.
 */
// eslint-disable-next-line react-refresh/only-export-components
function InertiaContractPage() {
  const { props } = usePage<{ contract: PageContract; editablePanel?: EditablePanelConfig }>();
  const page = <ContractPage contract={props.contract} />;
  if (!props.editablePanel) {
    return page;
  }

  return (
    <EditablePanelProvider config={props.editablePanel}>
      {page}
      <InlineEditablePanel />
    </EditablePanelProvider>
  );
}

const contractPageModule: { default: ComponentType } = { default: InertiaContractPage };

/**
 * Resolve an Inertia page name to a React component module.
 *
 * Resolution order:
 *   1. Direct page — if pages/{name}.tsx exists, use it
 *   2. ContractPage — fallback, reads PageContract JSON from Inertia props
 *
 * This means:
 *   - "Dashboard" with pages/Dashboard.tsx → direct React page
 *   - "Dashboard" without matching .tsx   → ContractPage from props.contract
 *   - "Entitlements/Show" with pages/Entitlements/Show.tsx → direct page
 *
 * Direct pages can also use ContractPage internally (hybrid pattern).
 */
export function resolvePageComponent(name: string): { default: ComponentType } {
  // Direct page: matching .tsx file in pages/
  const path = `../pages/${name}.tsx`;
  const page = directPages[path];
  if (page) {
    return page;
  }

  // Default: ContractPage renders from Inertia props.contract
  return contractPageModule;
}
