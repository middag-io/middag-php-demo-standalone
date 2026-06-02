/**
 * Dev-server page fixtures — REAL contracts captured from the live PHP backend.
 *
 * Each .json is the exact Inertia page object the backend emits for one route
 * (component + SharedProps + props.contract), captured from the bootstrap
 * `<script type="application/json">` payload. The standalone dev app
 * (`npm run dev`) replays them so you can build/preview the UI with NO PHP
 * running — and what you see is what production renders.
 *
 * Refresh after a backend change: see ./README.md.
 */
import type { PageContract } from "@middag-io/react";

import login from "./login.json";
import dashboard from "./dashboard.json";
import tickets from "./tickets.json";
import ticketDetail from "./ticket-detail.json";
import ticketNew from "./ticket-new.json";
import agents from "./agents.json";
import agentDetail from "./agent-detail.json";
import customers from "./customers.json";
import parity from "./parity.json";
import help from "./help.json";
import coverage from "./coverage.json";

/**
 * One captured Inertia page object. `props` carries the full SharedProps the
 * shell reads (auth, navigation, theme, flash...) plus the page contract.
 */
export interface Fixture {
  component: string;
  url: string;
  props: { contract: PageContract; [key: string]: unknown };
}

// `as unknown as` bridges the structurally-inferred JSON types to Fixture —
// the JSON is validated at capture time by the backend's own contract tests.
export const fixtures = {
  login,
  dashboard,
  tickets,
  "ticket-detail": ticketDetail,
  "ticket-new": ticketNew,
  agents,
  "agent-detail": agentDetail,
  customers,
  parity,
  help,
  coverage,
} as unknown as Record<string, Fixture>;
