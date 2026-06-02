/**
 * Mock @inertiajs/react for standalone dev server (FREE).
 * Context-based usePage + react-router Link.
 */
import React from "react";
import { useNavigate } from "react-router";
import { router } from "./inertia-core";

const PageContext = React.createContext<{ props: Record<string, unknown>; url: string }>({ props: {}, url: "/" });

export function PageProvider({ value, children }: {
  value: { props: Record<string, unknown>; url: string };
  children: React.ReactNode;
}) {
  return React.createElement(PageContext.Provider, { value }, children);
}

export function usePage<T extends Record<string, unknown> = Record<string, unknown>>(): { props: T; url: string } {
  return React.useContext(PageContext) as { props: T; url: string };
}

export function Head({ title, children }: { title?: string; children?: React.ReactNode }) {
  React.useEffect(() => { if (title) document.title = title; }, [title]);
  return children ? React.createElement("span", { style: { display: "none" } }, children) : null;
}

interface MockLinkProps extends Omit<React.AnchorHTMLAttributes<HTMLAnchorElement>, "href"> {
  href?: string; method?: string; preserveScroll?: boolean; preserveState?: boolean; as?: string;
}

export const Link = React.forwardRef<HTMLAnchorElement, MockLinkProps>(function MockLink(
  { href, onClick, children, as: _as, method: _m, preserveScroll: _ps, preserveState: _pst, ...rest }, ref,
) {
  const navigate = useNavigate();
  const handleClick = (e: React.MouseEvent<HTMLAnchorElement>) => {
    if (onClick) onClick(e);
    if (e.defaultPrevented) return;
    e.preventDefault();
    if (href) navigate(href);
  };
  return React.createElement("a", { ...rest, href: href ?? "#", ref, onClick: handleClick }, children);
});

// ── Inertia v3 primitives the engine imports ──────────────────────────
// @middag-io/react imports Deferred/WhenVisible (lazy blocks) and useRemember
// (DataTable view-prefs) from @inertiajs/react. In the no-server mock the data
// is already present, so render children immediately; useRemember = useState.

export function Deferred({ children }: {
  data: string | string[];
  fallback?: React.ReactNode;
  rescue?: React.ReactNode;
  children: React.ReactNode;
}) {
  return children;
}

export function WhenVisible({ children }: {
  data?: string | string[];
  buffer?: number;
  as?: string;
  always?: boolean;
  fallback?: React.ReactNode;
  children: React.ReactNode;
}) {
  return children;
}

export function useRemember<T>(initialState: T, _key?: string): [T, React.Dispatch<React.SetStateAction<T>>] {
  return React.useState(initialState);
}

export { router };
