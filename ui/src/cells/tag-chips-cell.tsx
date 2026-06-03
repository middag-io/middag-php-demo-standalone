/**
 * Custom `tag_chips` CELL renderer — readable text chips for a list of tags.
 *
 * The lib's free `link_group` cell renders each item as an icon-only ghost
 * button (the label rides as a tooltip), which is right for icon-links but makes
 * text tags read as anonymous grey icons. This is the cell-level twin of the
 * custom `sparkline` cell + `chart` block: registered host-side via
 * registerCellRenderer("tag_chips", TagChipsCell), it renders each tag as a
 * labelled pill (a filter link when the item carries an href). The PHP side
 * emits a `[{label, href?}]` list at the column key.
 */
import type { CellRendererProps } from "@middag-io/react";

interface TagChip {
  label?: string;
  href?: string | null;
}

export function TagChipsCell({ value }: CellRendererProps) {
  const items = Array.isArray(value) ? (value as TagChip[]) : [];
  const tags = items.filter((t) => t && typeof t.label === "string" && t.label !== "");

  if (tags.length === 0) {
    return <span className="text-muted-foreground">—</span>;
  }

  return (
    <div className="flex flex-wrap items-center gap-1">
      {tags.map((t, i) => {
        const pill = (
          <span className="bg-muted text-foreground border-border inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium">
            {t.label}
          </span>
        );
        return t.href ? (
          <a
            key={`${t.label}-${i}`}
            href={t.href}
            className="transition-opacity hover:opacity-70"
            title={`Filter by ${t.label}`}
          >
            {pill}
          </a>
        ) : (
          <span key={`${t.label}-${i}`}>{pill}</span>
        );
      })}
    </div>
  );
}
