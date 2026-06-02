/**
 * Mock @inertiajs/core for standalone dev server.
 * Vite alias redirects @inertiajs/core here.
 */
let _navigate: ((to: string) => void) | null = null;

export function setMockNavigate(fn: (to: string) => void) { _navigate = fn; }

type MockOpts = {
  only?: string[];
  preserveState?: boolean;
  preserveScroll?: boolean;
  onSuccess?: () => void;
  onFinish?: () => void;
  onError?: (errors: Record<string, string>) => void;
};

// A synchronous mock resolves instantly — fire the lifecycle callbacks the
// engine expects (e.g. useLazyTabs marks a tab loaded in onSuccess; blocks
// reset loading flags in onFinish), else those UI states stick forever.
const settle = (o?: MockOpts) => { o?.onSuccess?.(); o?.onFinish?.(); };

export const router = {
  get: (url: string, _params?: Record<string, unknown>, opts?: MockOpts) => {
    if (_navigate) _navigate(url); else console.log("[mock] GET", url);
    settle(opts);
  },
  post: (url: string, _data?: unknown, opts?: MockOpts) => { console.log("[mock] POST", url); settle(opts); },
  put: (url: string, _data?: unknown, opts?: MockOpts) => { console.log("[mock] PUT", url); settle(opts); },
  patch: (url: string, _data?: unknown, opts?: MockOpts) => { console.log("[mock] PATCH", url); settle(opts); },
  delete: (url: string, opts?: MockOpts) => { console.log("[mock] DELETE", url); settle(opts); },
  reload: (opts?: MockOpts) => { settle(opts); },
  visit: (url: string) => { if (_navigate) _navigate(url); else console.log("[mock] VISIT", url); },
  on: (_event?: string, _cb?: (event: unknown) => void) => () => {},
  poll: (_intervalMs?: number, _reload?: unknown, _opts?: unknown) => ({ stop: () => {}, start: () => {}, destroy: () => {} }),
};
