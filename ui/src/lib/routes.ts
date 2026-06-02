/**
 * Route helper — custom host URL builder.
 *
 * Abstracts away the difference between:
 *   - Dev server (mock navigate via react-router)
 *   - Production (your host platform's URL scheme)
 *
 * Adjust the production URL pattern below to match your backend routing.
 */

declare global {
  interface Window {
    __MIDDAG_MOCK_NAVIGATE__?: (to: string) => void;
  }
}

/**
 * Build a URL for a given path.
 *
 * @param path - Route path (e.g. "/connectors", "/settings/general")
 * @param params - Optional query parameters
 * @returns Full URL string
 *
 * @example
 *   route("/connectors")
 *   // dev  → navigates via react-router
 *   // prod → "/app/connectors"
 *
 *   route("/settings", { tab: "general" })
 *   // prod → "/app/settings?tab=general"
 */
export function route(path: string, params?: Record<string, string>): string {
  // Dev mode — mock navigate (react-router)
  if (typeof window !== "undefined" && window.__MIDDAG_MOCK_NAVIGATE__) {
    const url = path + (params ? "?" + new URLSearchParams(params).toString() : "");
    window.__MIDDAG_MOCK_NAVIGATE__(url);
    return url;
  }

  // Production — adjust base path to match your backend routing
  const base = "/app";
  const query = params ? "?" + new URLSearchParams(params).toString() : "";
  return `${base}${path}${query}`;
}
