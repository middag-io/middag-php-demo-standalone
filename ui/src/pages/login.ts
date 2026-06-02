/**
 * Login page contract.
 *
 * Uses the "basic" shell and a form_panel block. form_panel ships in the free
 * @middag-io/react engine; its heavy deps (react-hook-form + zod) load on demand.
 */
import type { PageContract } from "@middag-io/react";

export const loginContract: PageContract = {
  version: "1",
  shell: "basic",
  page: { key: "demo.login", title: "Sign in", subtitle: "Use any credentials, this is a mock." },
  layout: {
    template: "stack",
    regions: {
      content: [
        {
          key: "login",
          type: "form_panel",
          data: {
            action: "/login",
            method: "post",
            schema: [
              { kind: "field", key: "email", component: "email", props: { label: "Email", required: true, placeholder: "you@example.com" } },
              { kind: "field", key: "password", component: "password", props: { label: "Password", required: true } },
            ],
            values: {},
            errors: {},
            meta: { submitLabel: "Sign in" },
          },
        },
      ],
    },
  },
};
