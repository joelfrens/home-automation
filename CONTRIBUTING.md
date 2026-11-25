# Contributing

Thanks for helping out! This is a home automation project: ESP32 sensors → MQTT → ingestion →
PostgreSQL → Grafana and a Laravel + React app that also runs household features (school reminders,
shopping list, AI). Everything runs in Docker, so the same stack runs on a laptop and on a
Raspberry Pi 5.

More detailed guides:

- [docs/packages.md](docs/packages.md): where code goes and how to create a package
- [docs/touch.md](docs/touch.md): building pages that work on phones and touch screens
- [docs/hardware.md](docs/hardware.md): the sensor hardware and firmware

## Setting up

Follow **Quick start** in the [README](README.md#quick-start). In short:

```bash
cp web/.env.example web/.env          # set ADMIN_PASSWORD and the REVERB_APP_* values
docker compose up -d
docker compose exec web composer install
docker compose exec web php artisan key:generate
docker compose exec web php artisan migrate
docker compose exec web php artisan db:seed     # first login (an admin) from ADMIN_* in web/.env
cd ingestion && npm install && npm run dev      # stores readings (separate terminal)
npm run simulate                                # fake sensors, no hardware needed
```

Open http://localhost:8000. AI features need `ANTHROPIC_API_KEY` in `web/.env` (then
`docker compose restart queue`); everything else works without it.

Run PHP and Node commands **inside the containers** (`docker compose exec web …`,
`docker compose exec vite …`), not on your machine, so versions match CI.

## Day to day

| Task | Command |
|---|---|
| Artisan | `docker compose exec web php artisan <command>` |
| Laravel tests | `docker compose exec web php artisan test` |
| A package's tests | `docker compose exec -w /packages/<name> web sh -c "composer install && vendor/bin/phpunit"` |
| Ingestion tests | `cd ingestion && npm test` |
| Logs | `docker compose logs -f web queue scheduler mqtt-listener reverb` |
| Watch MQTT | `docker exec -it home-mosquitto mosquitto_sub -t 'home/#' -v` |
| Fake a reading | `docker exec home-mosquitto mosquitto_pub -t 'home/test/bme280' -m '{"temperature":21,"humidity":40,"pressure":1000}'` |
| Browse the database | Adminer at http://localhost:8081 (PostgreSQL, server `postgres`, user `home`, db `home`) |
| Back up the database | `docker compose exec -T postgres pg_dump -U home home > backups/backup.sql` |

## Before you push

CI ([.github/workflows/ci.yml](.github/workflows/ci.yml)) runs these on every push and pull request.
Run them locally first:

```bash
docker compose exec vite npm run check          # lint + format (npm run check:fix to fix)
docker compose exec vite npm run types:check    # TypeScript
docker compose exec web vendor/bin/pint --test  # PHP style (vendor/bin/pint to fix)
docker compose exec web vendor/bin/phpstan analyse
docker compose exec web php artisan test
docker compose exec web vendor/bin/pint /packages/sensors /packages/mqtt /packages/reminders --test
```

Plus the tests of any package you changed, and `cd ingestion && npm run typecheck && npm test` if you
touched ingestion. CI also compiles the firmware and validates `docker-compose.yml`.

## Branches, commits and pull requests

- One feature per branch (`shopping-list`, `touch-pass`), merged into `main` with a pull request.
  CI must be green.
- Short commit messages in lowercase, saying what changed: `shopping list with ai suggestions`.
- No AI co-author lines (`Co-Authored-By: Claude …`) in commits or pull requests.
- Update the README (roadmap, tests, layout) and the docs when you change how something works.

## Rules that keep the data safe

- **Tests only ever use in-memory SQLite.** Tests reset the database, and once a test run wiped the
  real database. `phpunit.xml` forces SQLite and every `TestCase` refuses any other database. Never
  weaken either; new packages copy both (see [docs/packages.md](docs/packages.md)).
- **Back up before anything that could drop tables** (risky migrations, image changes):
  `pg_dump`, see the table above. `backups/` is git-ignored.
- **Never commit secrets.** API keys go in `web/.env`, WiFi details in `firmware/…/secrets.h`;
  both are git-ignored. `.env.example` and `secrets.example.h` hold empty placeholders.
- **Tests never call a real AI model or push service.** Fake the agent (`MyAgent::fake([...])`) and
  HTTP (`Http::fake()`).
- **Read settings with `config()`, never `env()` outside `config/`.** PHPStan enforces it; `env()`
  returns null once config is cached.
- The AI only **proposes**: anything it reads from letters or receipts is shown to a person to check
  before it is saved, and text inside documents is treated as data, not instructions.

## Conventions

- **Non-UI logic goes in a package** under `packages/<name>`; pages, routes and controllers stay in
  `web/`. Details in [docs/packages.md](docs/packages.md).
- **Pages must work on touch screens** (phones and the Pi display). Use the shared UI components; see
  [docs/touch.md](docs/touch.md).
- **Room and sensor names**: lowercase letters, numbers and dashes (`living-room`).
- **Times**: stored in UTC; household-local times use `HOUSEHOLD_TIMEZONE` (default `Europe/London`).
- **Keep `docker-compose.yml` portable**: no Mac-only paths or settings, so it runs on the Pi.
- **Mosquitto allows anonymous access**: fine on a home network only; add auth before exposing it.
