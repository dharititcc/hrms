import type { NextConfig } from "next"
import path from "path"

const nextConfig: NextConfig = {
  transpilePackages: ["@workspace/ui"],
  output: "standalone",
  // Monorepo: trace deps from the repo root so hoisted node_modules are bundled.
  outputFileTracingRoot: path.join(__dirname, "../../"),
}

export default nextConfig
