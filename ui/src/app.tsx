import { useEffect } from "react";
import { BrowserRouter, Routes, Route, useNavigate } from "react-router";
import { ContractPage, I18nProvider } from "@middag-io/react";
import type { PageContract } from "@middag-io/react";
import { PageProvider } from "@mock/adapters/inertia-react";
import { setMockNavigate } from "@mock/adapters/inertia-core";
import { loginContract } from "./pages/login";
import { tasksContract } from "./pages/tasks";
import { newTaskContract, editTaskContract } from "./pages/task-form";
import { taskDetailContract } from "./pages/task-detail";

function NavigateBridge() {
  const navigate = useNavigate();
  useEffect(() => { setMockNavigate((to: string) => navigate(to)); }, [navigate]);
  return null;
}

const sharedProps = {
  auth: { id: 1, name: "Dev User", email: "dev@localhost", capabilities: [] },
  theme: { appearance: "light" as const, strings: {} as Record<string, string> },
  flash: {},
  locale: "en",
  version: "0.0.0-dev",
  scope: { extension: null, context: "global" },
};

function buildNavigation(activeKey: string) {
  return {
    tree: [
      { key: "tasks", label: "Tasks", icon: "list-check", href: "/", children: [] },
      { key: "tasks.new", label: "New task", icon: "plus", href: "/tasks/new", children: [] },
    ],
    footer: [],
    activeKey,
  };
}

function MockRoute({ contract, activeKey }: { contract: PageContract; activeKey: string }) {
  return (
    <PageProvider value={{ props: { ...sharedProps, contract, navigation: buildNavigation(activeKey) }, url: window.location.pathname }}>
      <ContractPage contract={contract} />
    </PageProvider>
  );
}

export function App() {
  return (
    <PageProvider value={{ props: { ...sharedProps, contract: null, navigation: buildNavigation("") }, url: "/" }}>
      <I18nProvider>
        <BrowserRouter>
          <NavigateBridge />
          <Routes>
            <Route path="/login" element={<MockRoute contract={loginContract} activeKey="" />} />
            <Route path="/" element={<MockRoute contract={tasksContract} activeKey="tasks" />} />
            <Route path="/tasks/new" element={<MockRoute contract={newTaskContract} activeKey="tasks.new" />} />
            <Route path="/tasks/:id/edit" element={<MockRoute contract={editTaskContract} activeKey="tasks" />} />
            <Route path="/tasks/:id" element={<MockRoute contract={taskDetailContract} activeKey="tasks" />} />
          </Routes>
        </BrowserRouter>
      </I18nProvider>
    </PageProvider>
  );
}
