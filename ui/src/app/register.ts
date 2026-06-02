/**
 * register — registration for this plugin's UI.
 *
 * Registers the 13 standard blocks plus shells, layouts, cell renderers, form
 * fields and icons. The 6 Pro/interactive blocks (chart_panel, kanban_board,
 * flow_editor, form_builder, condition_tree, sentence_builder) ship in
 * @middag-io/react-pro and register via its registerProDefaults().
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
  TabbedPanelBlock,
  ActivityTimelineBlock,
  WorkflowProgressBlock,
  MarkdownPanelBlock,
  CardGridBlock,
  ActionGridBlock,
  LinkListBlock,
  FormPanelBlock,
} from "@middag-io/react";

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
  registerBlock("tabbed_panel", TabbedPanelBlock);
  registerBlock("activity_timeline", ActivityTimelineBlock);
  registerBlock("workflow_progress", WorkflowProgressBlock);
  registerBlock("markdown_panel", MarkdownPanelBlock);
  registerBlock("card_grid", CardGridBlock);
  registerBlock("action_grid", ActionGridBlock);
  registerBlock("link_list", LinkListBlock);
  // form_panel pulls react-hook-form + zod; drop it if this bundle has no forms.
  registerBlock("form_panel", FormPanelBlock);

  // Cell renderers (status, timestamp, link, boolean, etc.)
  registerDefaultCells();

  // Form field components (text, select, switch, entity_picker, etc.)
  registerDefaultFields();

  // Icons (navigation, block, entity type icons)
  registerDefaultIcons();
}
