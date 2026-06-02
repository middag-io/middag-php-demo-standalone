// RENAME ME — This is an example standalone component.
//
// It shows you can write normal React components alongside
// contract-driven blocks. Not everything needs to be a block.
//
// Use standalone components for UI that doesn't come from
// a PageContract — headers, sidebars, modals, etc.

interface GreetingProps {
  name: string;
  role?: string;
}

export function Greeting({ name, role = "developer" }: GreetingProps) {
  return (
    <div className="flex items-center gap-3 rounded-md bg-muted px-4 py-3">
      <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-primary-foreground text-sm font-medium">
        {name.charAt(0).toUpperCase()}
      </div>
      <div>
        <p className="text-sm font-medium text-foreground">{name}</p>
        <p className="text-xs text-muted-foreground">{role}</p>
      </div>
    </div>
  );
}
