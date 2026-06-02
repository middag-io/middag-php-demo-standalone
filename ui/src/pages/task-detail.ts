/**
 * Task detail page contract, a read-only field/value view.
 *
 * Reuses dense_table as a two-column key/value display (the demo backend does
 * the same), plus an Edit action in the page header.
 */
import type { PageContract } from "@middag-io/react";

export const taskDetailContract: PageContract = {
  version: "1",
  shell: "product",
  page: {
    key: "demo.tasks.show",
    title: "Task detail",
    breadcrumbs: [{ label: "Tasks", href: "/" }, { label: "Task detail" }],
    actions: [
      { id: "edit", label: "Edit", intent: "secondary", icon: "pencil", target: { kind: "link", href: "/tasks/1/edit" } },
    ],
  },
  layout: {
    template: "stack",
    regions: {
      content: [
        {
          key: "task",
          type: "dense_table",
          title: "Write onboarding docs",
          data: {
            columns: [
              { key: "field", label: "Field" },
              { key: "value", label: "Value" },
            ],
            rows: [
              { id: "title", field: "Title", value: "Write onboarding docs" },
              { id: "status", field: "Status", value: "open" },
              { id: "priority", field: "Priority", value: "high" },
              { id: "due_on", field: "Due date", value: "2024-06-10" },
            ],
            pagination: { page: 1, perPage: 10, total: 4, lastPage: 1 },
            sort: { column: null, direction: null },
            filters: { available: [], applied: {} },
          },
        },
      ],
    },
  },
};
