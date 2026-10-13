import http from "node:http";
import https from "node:https";
import type { IncomingMessage, ServerResponse } from "node:http";
import path from "node:path";
import { fileURLToPath } from "node:url";
import tailwindcss from "@tailwindcss/vite";
import { tanstackStart } from "@tanstack/react-start/plugin/vite";
import viteReact from "@vitejs/plugin-react";
import { nitro } from "nitro/vite";
import { defineConfig, loadEnv, type Plugin } from "vite";

// TanStack Start handles every path before Vite's server.proxy, so this
// middleware is registered first and forwards Laravel routes to VITE_API_URL.
function laravelProxy(apiOrigin: string): Plugin {
  return {
    name: "laravel-proxy",
    configureServer(server) {
      let target: URL;
      try {
        target = new URL(apiOrigin);
      } catch {
        return;
      }
      const transport = target.protocol === "https:" ? https : http;
      const defaultPort = target.protocol === "https:" ? 443 : 80;
      const port = target.port ? Number(target.port) : defaultPort;

      server.middlewares.use((req: IncomingMessage, res: ServerResponse, next: () => void) => {
        const url = req.url ?? "";
        if (!url.startsWith("/api") && !url.startsWith("/sanctum")) {
          next();
          return;
        }
        const proxyReq = transport.request(
          {
            hostname: target.hostname,
            port,
            path: url,
            method: req.method,
            headers: { ...req.headers, host: target.host },
          },
          (proxyRes) => {
            res.writeHead(proxyRes.statusCode ?? 502, proxyRes.headers);
            proxyRes.pipe(res);
          },
        );
        proxyReq.on("error", () => {
          if (!res.headersSent) res.statusCode = 502;
          res.end("API unavailable");
        });
        req.pipe(proxyReq);
      });
    },
  };
}

const frontendRoot = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig(({ command, mode }) => {
  const env = loadEnv(mode, frontendRoot, "");
  const apiOrigin = (env.VITE_API_URL ?? "").trim().replace(/\/$/, "");
  if (!apiOrigin) {
    throw new Error("Set VITE_API_URL in frontend/.env to the Laravel API address.");
  }

  return {
    server: {
      port: 3000,
    },
    resolve: {
      tsconfigPaths: true,
    },
    plugins: [
      laravelProxy(apiOrigin),
      tailwindcss(),
      tanstackStart({
        srcDirectory: "src",
        // Keep the SSR error wrapper in src/server.ts.
        server: { entry: "server" },
        // Static host: one index.html shell, with client routing after it loads.
        spa: {
          enabled: true,
          prerender: {
            outputPath: "/index",
          },
        },
      }),
      viteReact(),
      nitro(
        command === "build"
          ? { output: { publicDir: path.join(frontendRoot, "dist") } }
          : {},
      ),
    ],
  };
});
