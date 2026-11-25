# Instructions for AI coding assistants

Home automation platform: ESP32 sensors and a household web app (school reminders, shopping list,
AI features), running in Docker on a laptop or a Raspberry Pi 5 with a touch screen.

@CONTRIBUTING.md
@docs/packages.md
@docs/touch.md

## Architecture

- Sensors: ESP32 ATOM Lite + BME280 → MQTT (Mosquitto) → `ingestion/` (Node.js + TypeScript, Zod)
  → PostgreSQL → Grafana and the Laravel app. Hardware details: [docs/hardware.md](docs/hardware.md).
- Live path: Mosquitto → `mqtt-listener` container (`php artisan mqtt:listen`, `packages/mqtt`) →
  `BroadcastSensorReading` → `ReadingReceived` on the **private** channel `readings` → Reverb →
  `hooks/use-live-readings.ts`. Ingestion stores; Laravel only broadcasts. The database is the source
  of truth.
- `web/`: Laravel 13, React 19 starter kit (Inertia 3, Tailwind 4, Wayfinder, Pest), FrankenPHP in
  Docker (not Sail, not Herd). Containers: web :8000, vite :5173, reverb :8080, mqtt-listener,
  queue (`queue:work`), scheduler (`schedule:work`). Shared env in the `x-laravel-env` anchor of
  `docker-compose.yml`.
- MQTT requires a login (no anonymous access): users admin/sensor/ingestion/laravel, passwords in
  the git-ignored repo-root `.env` (template `.env.example`), permissions in `mosquitto/acl`,
  users created at start by `mosquitto/entrypoint.sh`. New device kind = new user in all four places.
- Postgres 17: host port **5433** (container 5432). One database `home` holds sensor and app tables;
  all schema comes from Laravel migrations (app + packages).
- AI: `laravel/ai` (`web/config/ai.php`: anthropic + ollama). Each agent's model comes from config.
  Every call is logged to `ai_usage` by `App\Listeners\RecordAiUsage` (prices in
  `config/household-ai.php`).
- Accounts: registration disabled; admins add family members (Settings → Family, Gate `admin`). The
  last admin can't be removed or demoted.
- Outdoor weather: `packages/weather` (Open-Meteo, no key; `WEATHER_LATITUDE/LONGITUDE` in web/.env), cached
  30 min, null on failure. Web tests block all unfaked HTTP (`Http::preventStrayRequests()` in
  tests/TestCase.php) and run with Inertia SSR off (phpunit.xml).
- Times are stored in UTC; household-local times use `HOUSEHOLD_TIMEZONE` (default `Europe/London`).

## Hard rules

- **Never weaken the test-database guard** (`phpunit.xml` forcing in-memory SQLite, the
  `setUpTraits()` check in each `TestCase`). Tests use `RefreshDatabase`; a test run once wiped the
  real database.
- **Ask before anything that could drop or rewrite data** in the real database, and take a `pg_dump`
  first.
- **No real AI calls or pushes in tests**: fake agents (`Agent::fake`) and HTTP (`Http::fake`).
- **No secrets in git**: API keys only in `web/.env`, WiFi only in `firmware/…/secrets.h`.
- **`env()` only inside `config/`**; read settings with `config()` (PHPStan enforces it).
- **Commits:** short lowercase messages; **no Co-Authored-By or other AI attribution lines** in
  commits or pull requests. Commit only when asked.
- Don't run `npm audit fix --force` in `ingestion/` (it downgrades vitest).
- Ingestion lockfile: macOS npm omits Linux-only optional deps that CI's `npm ci` needs. After
  changing deps, check with `docker run --rm --platform linux/arm64 -v "$PWD":/repo -w /repo/ingestion node:22 npm ci`.

## Gotchas

- Run PHP/Node commands in the containers (`docker compose exec web …` / `vite …`). `web/.env` points
  artisan on the host at `127.0.0.1:5433`; containers override it with `postgres:5432`.
- Private channels: Echo's `channelAuthorization.customHandler` in `app.tsx` POSTs
  `/broadcasting/auth` with `X-XSRF-TOKEN` read from the cookie at call time. The Pusher event name
  is `App\Events\ReadingReceived`.
- php-mqtt can't auto-reconnect with a clean session: `mqtt:listen` exits on broker loss and Docker
  restarts it.
- Wayfinder generates route/action types during the Vite build; run `npm run build` (or keep vite
  running) after adding routes.
