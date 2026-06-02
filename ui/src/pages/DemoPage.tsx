/**
 * Demo Direct Page — example of a custom React page.
 *
 * Direct pages receive props from Inertia (via usePage) and render
 * custom UI. Use this pattern for complex pages that can't be
 * expressed as a PageContract (workflows, wizards, dashboards).
 *
 * In production, the host page controller calls:
 *   InertiaAdapter::render('DemoPage', { title: '...', items: [...] })
 *
 * The page-resolver matches "DemoPage" to this file via import.meta.glob.
 */
import { usePage } from "@inertiajs/react";

interface DemoPageProps {
  title: string;
  items: Array<{ id: number; name: string; status: string }>;
  // Inertia v3 usePage<T> constrains T to PageProps (string index signature).
  [key: string]: unknown;
}

export default function DemoPage() {
  const { props } = usePage<DemoPageProps>();
  const { title, items } = props;

  return (
    <div className="mx-auto max-w-2xl p-8">
      <h1 className="text-foreground text-2xl font-semibold">{title}</h1>
      <p className="text-muted-foreground mt-2 text-sm">
        This is a direct page — it uses usePage() instead of PageContract.
      </p>
      <ul className="mt-6 space-y-2">
        {items?.map((item) => (
          <li
            key={item.id}
            className="bg-card border-border flex items-center justify-between rounded-lg border px-4 py-3"
          >
            <span className="text-foreground text-sm font-medium">{item.name}</span>
            <span className="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-xs">
              {item.status}
            </span>
          </li>
        ))}
      </ul>
    </div>
  );
}
