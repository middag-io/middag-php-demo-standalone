/**
 * Standalone dev app — renders the demo's REAL contracts with NO PHP backend.
 *
 * Each route replays a captured page fixture (mock/fixtures/*.json) the exact
 * way production does: the captured SharedProps go through the mock usePage
 * context, then ContractPage renders off props.contract. This mirrors the prod
 * resolver (app/page-resolver.tsx → InertiaContractPage), so the dev preview is
 * faithful to what the host bundle (entry-custom.tsx) renders in production.
 *
 * @inertiajs/* is aliased to local mock adapters by vite.config.ts; the mock
 * router is bridged to react-router below so contract links/actions navigate.
 */
import { useEffect } from "react";
import { BrowserRouter, Routes, Route, useNavigate } from "react-router";
import { ContractPage, I18nProvider, ProgressProvider } from "@middag-io/react";
import type { PageContract } from "@middag-io/react";
import { PageProvider } from "@mock/adapters/inertia-react";
import { setMockNavigate } from "@mock/adapters/inertia-core";
import { fixtures } from "@mock/fixtures";

/** Wire the lib's (mock) router to react-router so contract links navigate the dev app. */
function NavigateBridge() {
  const navigate = useNavigate();
  useEffect(() => {
    setMockNavigate((to: string) => navigate(to));
  }, [navigate]);
  return null;
}

/** Render one captured page: feed its SharedProps through usePage, then ContractPage. */
function FixturePage({ name }: { name: string }) {
  const fx = fixtures[name];
  if (!fx) {
    return (
      <PageProvider value={{ props: {}, url: "/" }}>
        <div style={{ padding: 24 }}>No dev fixture for &quot;{name}&quot;.</div>
      </PageProvider>
    );
  }
  return (
    <PageProvider value={{ props: fx.props, url: fx.url }}>
      <ContractPage contract={fx.props.contract as PageContract} />
    </PageProvider>
  );
}

export function App() {
  return (
    <ProgressProvider>
      <I18nProvider>
        <BrowserRouter>
          <NavigateBridge />
          <Routes>
            <Route path="/login" element={<FixturePage name="login" />} />
            <Route path="/" element={<FixturePage name="dashboard" />} />
            <Route path="/tickets" element={<FixturePage name="tickets" />} />
            <Route path="/tickets/new" element={<FixturePage name="ticket-new" />} />
            <Route path="/tickets/:id" element={<FixturePage name="ticket-detail" />} />
            <Route path="/agents" element={<FixturePage name="agents" />} />
            <Route path="/agents/:id" element={<FixturePage name="agent-detail" />} />
            <Route path="/customers" element={<FixturePage name="customers" />} />
            <Route path="/parity" element={<FixturePage name="parity" />} />
            <Route path="/help" element={<FixturePage name="help" />} />
            <Route path="/coverage" element={<FixturePage name="coverage" />} />
          </Routes>
        </BrowserRouter>
      </I18nProvider>
    </ProgressProvider>
  );
}
