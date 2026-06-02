/**
 * Custom free `chart` block — inline SVG, no PRO / recharts dependency.
 *
 * React-free ships no `chart` block (chart_panel is PRO, in @middag-io/react-pro),
 * so the host registers this one via registerBlock("chart", ChartBlock). It reads
 * exactly what middag-io/ui's BlockBuilder::chart emits:
 *   { chartType: "line"|"bar"|"area", series: [{ name, data: number[] }], categories?: (string|number)[] }
 * This is the demonstrated host-extension seam the help-desk demo is built around.
 */
import type { ComponentType } from "react";
import type { BlockProps } from "@middag-io/react";

interface ChartSeries {
  name: string;
  data: number[];
}

interface ChartBlockData {
  chartType?: "line" | "bar" | "area";
  series?: ChartSeries[];
  categories?: (string | number)[];
  options?: Record<string, unknown>;
}

const PALETTE = ["var(--primary, #4f46e5)", "#06b6d4", "#f59e0b", "#10b981"];

export const ChartBlock: ComponentType<BlockProps<ChartBlockData>> = ({ block }) => {
  const data = block.data ?? {};
  const series = data.series ?? [];
  const categories = data.categories ?? [];
  const kind = data.chartType ?? "bar";

  const count = Math.max(categories.length, ...series.map((s) => s.data?.length ?? 0), 1);
  const values = series.flatMap((s) => s.data ?? []);
  const max = Math.max(1, ...values);

  const W = 640;
  const H = 240;
  const padL = 32;
  const padR = 8;
  const padT = 12;
  const padB = 26;
  const innerW = W - padL - padR;
  const innerH = H - padT - padB;

  const x = (i: number): number =>
    count <= 1 ? padL + innerW / 2 : padL + (i / (count - 1)) * innerW;
  const xBand = (i: number): number => padL + (i + 0.5) * (innerW / count);
  const y = (v: number): number => padT + innerH - (v / max) * innerH;

  return (
    <figure className="middag-chart" style={{ margin: 0 }}>
      <svg
        viewBox={`0 0 ${W} ${H}`}
        width="100%"
        role="img"
        aria-label={series.map((s) => s.name).join(", ") || "chart"}
        style={{ display: "block", maxWidth: "100%" }}
      >
        {/* baseline + max gridline */}
        <line x1={padL} y1={padT + innerH} x2={W - padR} y2={padT + innerH} stroke="currentColor" strokeOpacity={0.2} />
        <line x1={padL} y1={padT} x2={W - padR} y2={padT} stroke="currentColor" strokeOpacity={0.08} />
        <text x={padL - 6} y={padT + 4} textAnchor="end" fontSize={10} fill="currentColor" fillOpacity={0.5}>
          {max}
        </text>

        {series.map((s, si) => {
          const color = PALETTE[si % PALETTE.length];
          const points = (s.data ?? []).map((v, i) => `${x(i)},${y(v)}`).join(" ");

          if (kind === "bar") {
            const groupW = innerW / count;
            const barW = Math.max(2, (groupW / Math.max(series.length, 1)) * 0.7);
            return (s.data ?? []).map((v, i) => (
              <rect
                key={`${si}-${i}`}
                x={xBand(i) - (series.length * barW) / 2 + si * barW}
                y={y(v)}
                width={barW}
                height={padT + innerH - y(v)}
                rx={2}
                fill={color}
              />
            ));
          }

          return (
            <g key={si}>
              {kind === "area" && (
                <polygon
                  points={`${padL},${padT + innerH} ${points} ${W - padR},${padT + innerH}`}
                  fill={color}
                  fillOpacity={0.15}
                />
              )}
              <polyline points={points} fill="none" stroke={color} strokeWidth={2} />
              {(s.data ?? []).map((v, i) => (
                <circle key={i} cx={x(i)} cy={y(v)} r={2.5} fill={color} />
              ))}
            </g>
          );
        })}

        {categories.map((c, i) =>
          i % Math.ceil(count / 12) === 0 ? (
            <text key={i} x={kind === "bar" ? xBand(i) : x(i)} y={H - 8} textAnchor="middle" fontSize={9} fill="currentColor" fillOpacity={0.55}>
              {String(c)}
            </text>
          ) : null,
        )}
      </svg>
      {series.length > 0 && (
        <figcaption style={{ display: "flex", gap: "1rem", fontSize: "0.75rem", opacity: 0.7, marginTop: "0.25rem" }}>
          {series.map((s, si) => (
            <span key={si} style={{ display: "inline-flex", alignItems: "center", gap: "0.35rem" }}>
              <span style={{ width: 10, height: 10, borderRadius: 2, background: PALETTE[si % PALETTE.length] }} />
              {s.name}
            </span>
          ))}
        </figcaption>
      )}
    </figure>
  );
};
