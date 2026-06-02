/**
 * Tasks list page contract, the dashboard after login.
 *
 * Demonstrates two free blocks plus row-level actions:
 *   - metric_card (task count)
 *   - dense_table (rows, rowHref for navigation, rowActions for edit/delete)
 */
import type { PageContract } from "@middag-io/react";

export const tasksContract: PageContract = {
  version: "1",
  shell: "product",
  page: {
    key: "demo.tasks",
    title: "Tasks",
    subtitle: "A contract-driven CRUD, mirroring the middag-php-demo-standalone backend.",
    actions: [
      { id: "new", label: "New task", intent: "primary", icon: "plus", target: { kind: "link", href: "/tasks/new" } },
    ],
  },
  layout: {
    template: "stack",
    regions: {
      content: [
        {
          key: "task_count",
          type: "metric_card",
          data: { label: "Tasks", value: 3, icon: "list-check" },
        },
        {
          key: "tasks",
          type: "dense_table",
          title: "All tasks",
          data: {
            columns: [
              { key: "title", label: "Title" },
              { key: "status", label: "Status", variant: "badge" },
              { key: "priority", label: "Priority" },
              { key: "created", label: "Created", variant: "timestamp" },
            ],
            rows: [
              { id: 1, title: "Write onboarding docs", status: "open", priority: "high", created: "2024-05-30" },
              { id: 2, title: "Review pull request", status: "open", priority: "normal", created: "2024-05-29" },
              { id: 3, title: "Ship the release", status: "done", priority: "low", created: "2024-05-28" },
            ],
            pagination: { page: 1, perPage: 10, total: 3, lastPage: 1 },
            sort: { column: "created", direction: "desc" },
            filters: { available: [], applied: {} },
            rowHref: "/tasks/{id}",
            rowActions: [
              { id: "edit", label: "Edit", intent: "secondary", icon: "pencil", target: { kind: "link", href: "/tasks/{id}/edit" } },
              {
                id: "delete",
                label: "Delete",
                intent: "danger",
                icon: "trash",
                target: { kind: "request", endpoint: "/tasks/{id}", method: "delete" },
                confirmation: {
                  title: "Delete task",
                  message: "Remove this task? This cannot be undone.",
                  variant: "danger",
                  confirmLabel: "Delete",
                  cancelLabel: "Cancel",
                },
              },
            ],
          },
        },
      ],
    },
  },
};
