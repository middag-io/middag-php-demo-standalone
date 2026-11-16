/**
 * register — registration for this plugin's UI.
 *
 * Registers the 14 free blocks plus shells, layouts, cell renderers, form
 * fields and icons. The Pro/interactive blocks (chart_panel, workflow_progress,
 * kanban_board, flow_editor, form_builder, condition_tree, sentence_builder)
 * ship in @middag-io/react-pro and register via its registerProDefaults().
 *
 * For lean IIFE bundles (WordPress/Moodle), trim the blocks you don't use.
 *
 * Full catalog: https://docs.middag.io/blocks
 */

import {
  registerShell,
  registerLayout,
  registerBlock,
  registerDefaultCells,
  registerCellRenderer,
  registerDefaultFields,
  registerDefaultIcons,
  // Shells
  BasicShell,
  ImmersiveShell,
  // Layouts
  StackLayout,
  SidebarLayout,
  DashboardLayout,
  WizardLayout,
  // Blocks
  DenseTableBlock,
  MetricCardBlock,
  EmptyStateBlock,
  DetailPanelBlock,
  StatusStripBlock,
  TabsBlock,
  ActivityTimelineBlock,
  ChartBlock,
  MarkdownPanelBlock,
  CardGridBlock,
  ActionGridBlock,
  LinkListBlock,
  FormPanelBlock,
} from "@middag-io/react";
// Host-registered extensions: a custom `auth` shell that replaces the package
// one, and two custom cells (`sparkline`, `tag_chips`).
import { SparklineCell } from "../cells/sparkline-cell";
import { TagChipsCell } from "../cells/tag-chips-cell";
import { AuthShell } from "../shells/auth-shell";

let registered = false;

export function registerDefaults(): void {
  if (registered) return;
  registered = true;

  // Shells. `basic` (login) → BasicShell; `product` (dashboard) also maps to
  // BasicShell in the free surface (the Pro ProductShell ships in
  // @middag-io/react-pro); `immersive` is the free immersive shell.
  registerShell("basic", BasicShell);
  registerShell("product", BasicShell);
  registerShell("immersive", ImmersiveShell);
  // Custom demo shell: a chromeless centered login (no app sidebar/nav). The
  // package ships its own `auth` shell; registering the same key replaces it,
  // which is how a host swaps a package shell for its own.
  registerShell("auth", AuthShell);

  // Layouts
  registerLayout("stack", StackLayout);
  registerLayout("sidebar", SidebarLayout);
  registerLayout("dashboard", DashboardLayout);
  registerLayout("wizard", WizardLayout);

  // Blocks — add more as your pages need them
  registerBlock("dense_table", DenseTableBlock);
  registerBlock("metric_card", MetricCardBlock);
  registerBlock("empty_state", EmptyStateBlock);
  registerBlock("detail_panel", DetailPanelBlock);
  registerBlock("status_strip", StatusStripBlock);
  registerBlock("tabs", TabsBlock);
  registerBlock("activity_timeline", ActivityTimelineBlock);
  registerBlock("chart", ChartBlock);
  registerBlock("markdown_panel", MarkdownPanelBlock);
  registerBlock("card_grid", CardGridBlock);
  registerBlock("action_grid", ActionGridBlock);
  registerBlock("link_list", LinkListBlock);
  // form_panel pulls react-hook-form + zod; drop it if this bundle has no forms.
  registerBlock("form_panel", FormPanelBlock);

  // Cell renderers (status, timestamp, link, boolean, etc.)
  registerDefaultCells();
  // Custom `sparkline` cell — a dense_table column with variant "sparkline"
  // resolves here.
  // Register AFTER the defaults.
  registerCellRenderer("sparkline", SparklineCell);
  // Custom `tag_chips` cell — readable labelled pills for the ticket tags
  // (the free `link_group` cell renders icon-only links, not text chips).
  registerCellRenderer("tag_chips", TagChipsCell);

  // Form field components (text, select, switch, entity_picker, etc.)
  registerDefaultFields();

  // Icons (navigation, block, entity type icons)
  registerDefaultIcons();
}
