import { existsSync } from "node:fs";
import { loadEnvFile } from "node:process";
import { fileURLToPath } from "node:url";

// Loads the repo-root .env (the same file docker compose reads) so the MQTT passwords live in
// one place. Variables already set in the shell win. Missing file = nothing happens (e.g. on
// the Pi under systemd, where the environment is set directly).
const rootEnv = fileURLToPath(new URL("../../.env", import.meta.url));

if (existsSync(rootEnv)) {
  loadEnvFile(rootEnv);
}
