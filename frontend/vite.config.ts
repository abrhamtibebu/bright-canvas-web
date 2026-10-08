import http from "node:http";
import type { IncomingMessage, ServerResponse } from "node:http";
import path from "node:path";
import { fileURLToPath } from "node:url";
import tailwindcss from "@tailwindcss/vite";
import { tanstackStart } from "@tanstack/react-start/plugin/vite";
import viteReact from "@vitejs/plugin-react";
import { nitro } from "nitro/vite";
import { defineConfig, type Plugin } from "vite";

// TanStack Start handles every path before Vite's server.proxy, so this
// middleware is registered first and forwards the Laravel routes.
function laravelProxy(): Plugin {
  return {
    name: "laravel-proxy",
    configureServer(server) {
      server.middlewares.use((req: IncomingMessage, res: ServerResponse, next: () => void) => {
        const url = req.url ?? "";
        if (!url.startsWith("/api") && !url.startsWith("/sanctum")) {
          next();
          return;
        }
        const proxyReq = http.request(
          {
            hostname: "127.0.0.1",
            port: 8000,
            path: url,
            method: req.method,
            headers: req.headers,
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

export default defineConfig(({ command }) => ({
  server: {
    port: 3000,
  },
  resolve: {
    tsconfigPaths: true,
  },
  plugins: [
    laravelProxy(),
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
}));
