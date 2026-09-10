import { Client } from "@modelcontextprotocol/sdk/client/index.js";
import {
  StdioClientTransport,
  getDefaultEnvironment,
} from "@modelcontextprotocol/sdk/client/stdio.js";
import type { AikidoScanFile } from "./sentinel";

const AIKIDO_MCP_PACKAGE = "@aikidosec/mcp@1.0.17";

function mcpEnv(): Record<string, string> {
  const env = { ...getDefaultEnvironment() };
  const apiKey = process.env.AIKIDO_API_KEY;
  if (apiKey) {
    env.AIKIDO_API_KEY = apiKey;
  }
  return env;
}

/**
 * Deterministic Aikido hook: spawn the same MCP server the Cursor plugin uses
 * and call aikido_full_scan. Not an agent-chosen tool call.
 */
export async function scanFilesWithAikidoMcp(files: AikidoScanFile[]): Promise<unknown> {
  const transport = new StdioClientTransport({
    command: "npx",
    args: ["-y", AIKIDO_MCP_PACKAGE],
    env: mcpEnv(),
    stderr: "pipe",
  });
  const client = new Client({ name: "osticket-sentinel", version: "1.0.0" });
  await client.connect(transport);
  try {
    const result = await client.callTool(
      {
        name: "aikido_full_scan",
        arguments: { files_to_scan: files },
      },
      undefined,
      { timeout: 90_000 }
    );
    if (result.isError) {
      const text = Array.isArray(result.content)
        ? result.content
            .map((part) => ("text" in part ? part.text : ""))
            .join("\n")
        : "";
      throw new Error(text || "Aikido MCP aikido_full_scan returned isError");
    }
    return result;
  } finally {
    await client.close();
  }
}
