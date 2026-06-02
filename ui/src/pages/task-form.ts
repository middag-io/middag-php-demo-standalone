/**
 * Task create/edit page contracts, schema-driven form_panel.
 *
 * One field schema drives both create (empty values, POST) and edit (prefilled
 * values, PUT). Shows text, textarea, select, radio, date, int, switch,
 * entity_picker and a conditional field (done_reason appears when status = done).
 */
import type { PageContract, FormSchemaNode } from "@middag-io/react";

const taskFormSchema: FormSchemaNode[] = [
  { kind: "field", key: "title", component: "text", props: { label: "Title", required: true, placeholder: "What needs doing?" } },
  { kind: "field", key: "notes", component: "textarea", props: { label: "Notes", rows: 4 } },
  {
    kind: "field",
    key: "priority",
    component: "select",
    props: {
      label: "Priority",
      required: true,
      options: [
        { value: "low", label: "Low" },
        { value: "normal", label: "Normal" },
        { value: "high", label: "High" },
      ],
    },
  },
  {
    kind: "field",
    key: "status",
    component: "radio",
    props: {
      label: "Status",
      required: true,
      options: [
        { value: "open", label: "Open" },
        { value: "done", label: "Done" },
      ],
    },
  },
  {
    kind: "field",
    key: "done_reason",
    component: "text",
    props: {
      label: "Done reason",
      helpText: "Shown only when status is Done.",
      visible_when: { field: "status", operator: "equals", value: "done" },
      required_when: { field: "status", operator: "equals", value: "done" },
    },
  },
  { kind: "field", key: "due_on", component: "date", props: { label: "Due date" } },
  { kind: "field", key: "estimate_minutes", component: "int", props: { label: "Estimate (minutes)", min: 0, max: 100000 } },
  {
    kind: "field",
    key: "parent_task",
    component: "entity_picker",
    props: {
      label: "Parent task",
      options: [
        { value: "1", label: "Write onboarding docs" },
        { value: "2", label: "Review pull request" },
      ],
    },
  },
  { kind: "field", key: "notify", component: "switch", props: { label: "Notify on change" } },
];

export const newTaskContract: PageContract = {
  version: "1",
  shell: "product",
  page: {
    key: "demo.tasks.create",
    title: "New task",
    breadcrumbs: [{ label: "Tasks", href: "/" }, { label: "New task" }],
  },
  layout: {
    template: "stack",
    regions: {
      content: [
        {
          key: "task_form",
          type: "form_panel",
          data: {
            action: "/tasks",
            method: "post",
            schema: taskFormSchema,
            values: { priority: "normal", status: "open", notify: true },
            errors: {},
            meta: { submitLabel: "Create task", cancelHref: "/" },
          },
        },
      ],
    },
  },
};

export const editTaskContract: PageContract = {
  version: "1",
  shell: "product",
  page: {
    key: "demo.tasks.edit",
    title: "Edit task",
    breadcrumbs: [{ label: "Tasks", href: "/" }, { label: "Edit task" }],
  },
  layout: {
    template: "stack",
    regions: {
      content: [
        {
          key: "task_form",
          type: "form_panel",
          data: {
            action: "/tasks/1",
            method: "put",
            schema: taskFormSchema,
            values: { title: "Write onboarding docs", priority: "high", status: "open", notify: true },
            errors: {},
            meta: { submitLabel: "Save changes", cancelHref: "/" },
          },
        },
      ],
    },
  },
};
