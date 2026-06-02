// RENAME ME — This is an example custom block.
//
// It demonstrates how to create a block that integrates with
// the MIDDAG contract system via registerBlock().
//
// After renaming, update the registration in your setup code.
//
// Docs: https://docs.middag.io/blocks/custom-blocks

import type { BlockProps } from "@middag-io/react";

import { Greeting } from "../components/greeting";

/** Data shape for this block — define what the backend sends. */
export interface HelloBlockData {
  greeting: string;
  name: string;
  role?: string;
}

/** Custom block component. Receives block descriptor from the PageContract. */
export function HelloBlock({ block }: BlockProps<HelloBlockData>) {
  return (
    <div className="rounded-lg border bg-card p-6 text-card-foreground">
      <h2 className="text-lg font-semibold text-foreground">
        {block.data.greeting}
      </h2>
      <p className="mt-2 mb-4 text-muted-foreground">
        This is a custom block rendering a standalone component:
      </p>
      <Greeting name={block.data.name} role={block.data.role} />
      <p className="mt-4 text-sm text-muted-foreground">
        Edit this file at <code className="text-xs bg-muted px-1 py-0.5 rounded">src/blocks/hello-block.tsx</code>
      </p>
    </div>
  );
}
