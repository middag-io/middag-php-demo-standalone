import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { registerDefaults, registerShell, registerBlock, BasicShell } from "@middag-io/react";
import "@middag-io/react/style.css";
import "./theme.css";
import "@fontsource-variable/figtree";
import { HelloBlock } from "./blocks/hello-block";
import { App } from "./app";

registerDefaults();
registerShell("product", BasicShell);
// Example: register your custom block so the contract can render it by type.
registerBlock("hello_block", HelloBlock);

createRoot(document.getElementById("root")!).render(
  <StrictMode><App /></StrictMode>,
);
