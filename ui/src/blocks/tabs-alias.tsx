/**
 * `tabs` → `tabbed_panel` alias — bridges the PHP/React block-type seam.
 *
 * middag-io/ui's BlockBuilder::tabs() emits wire type "tabs" with each tab shaped
 * `{ id, label, blocks }` (the page-level Tab). The free React block is registered
 * only as "tabbed_panel" and reads `{ key, label, blocks }`. This adapter renames
 * `id`→`key` and delegates to the lib's TabbedPanelBlock, so a PHP `tabs()` block
 * renders without a backend change — the host-side half of P5b.
 */
import type { ComponentType } from "react";
import { TabbedPanelBlock } from "@middag-io/react";

interface PhpTab {
  id: string;
  label: unknown;
  blocks: unknown[];
}

interface TabsAliasProps {
  block: {
    type: string;
    key: string;
    data: { tabs?: PhpTab[]; defaultTab?: string };
  };
}

export const TabsAliasBlock: ComponentType<TabsAliasProps> = ({ block }) => {
  const tabs = (block.data?.tabs ?? []).map((tab) => ({
    key: tab.id,
    label: tab.label,
    blocks: tab.blocks,
  }));

  const mapped = {
    ...block,
    type: "tabbed_panel",
    data: { ...block.data, tabs },
  };

  return <TabbedPanelBlock block={mapped as never} />;
};
