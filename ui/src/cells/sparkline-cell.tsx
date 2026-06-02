/**
 * Custom free `sparkline` CELL renderer — inline SVG, no deps.
 *
 * The DataTable cell registry ships no sparkline (it ships status/timestamp/
 * link/rich_status/html/link_group/annotated/progress). This is the cell-level
 * twin of the custom `chart` BLOCK: the host registers it via
 * registerCellRenderer("sparkline", SparklineCell) in ui/src/app/register.ts,
 * and any dense_table column with `variant: "sparkline"` resolves to it. The PHP
 * side emits a per-row number[] at the column's key — the demonstrated
 * Open/Closed extension seam, cell edition.
 */
import type { CellRendererProps } from "@middag-io/react";

const W = 88;
const H = 22;
const PAD = 2;

export function SparklineCell({ value }: CellRendererProps) {
  const data = Array.isArray(value) ? (value as unknown[]).map(Number).filter((n) => !Number.isNaN(n)) : [];
  if (data.length === 0) {
    return <span className="text-muted-foreground">—</span>;
  }

  const max = Math.max(...data);
  const min = Math.min(0, ...data);
  const span = max - min || 1;
  const innerW = W - PAD * 2;
  const innerH = H - PAD * 2;

  const x = (i: number): number => (data.length <= 1 ? PAD + innerW / 2 : PAD + (i / (data.length - 1)) * innerW);
  const y = (v: number): number => PAD + innerH - ((v - min) / span) * innerH;

  const points = data.map((v, i) => `${x(i)},${y(v)}`).join(" ");
  const lastX = x(data.length - 1);
  const lastY = y(data[data.length - 1]);

  return (
    <svg
      width={W}
      height={H}
      viewBox={`0 0 ${W} ${H}`}
      role="img"
      aria-label={`trend: ${data.join(", ")}`}
      style={{ display: "block", overflow: "visible" }}
    >
      <polyline
        points={points}
        fill="none"
        stroke="var(--primary, #4f46e5)"
        strokeWidth={1.5}
        strokeLinejoin="round"
        strokeLinecap="round"
      />
      <circle cx={lastX} cy={lastY} r={2} fill="var(--primary, #4f46e5)" />
    </svg>
  );
}
