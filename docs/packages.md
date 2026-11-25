# Packages

Logic that isn't UI lives in Composer packages under `packages/`. The Laravel app in `web/` installs
them and owns only what users see: routes, controllers, form requests and React pages.

| Package | What it does |
|---|---|
| [`sensors`](../packages/sensors) | Sensor-type registry, `measurements` table, reading queries, MQTT message parser |
| [`mqtt`](../packages/mqtt) | Broker connection and `php artisan mqtt:listen` |
| [`reminders`](../packages/reminders) | Family events, AI letter reading, `reminders:send`, ntfy push |

## Package or `web/`?

| Goes in a package | Stays in `web/` |
|---|---|
| Models, migrations, queries | Routes and controllers |
| Business rules (e.g. when a reminder fires) | Form requests (validation of user input) |
| Artisan commands, scheduled jobs, notifications | React pages and components |
| An AI agent that belongs to one feature | Anything that combines several packages (e.g. a daily briefing agent with tools from sensors, reminders and shopping, in `web/app/Ai/`) |

## Rules

- **One package per feature area** (`reminders`, `shopping`, `memory`). Packages **never depend on
  each other**; when two need to work together, the host app connects them.
- **Don't reference `App\Models\User`.** Store a nullable `user_id` (null on delete) and find the
  user model through `config('auth.providers.users.model')`. If a package needs something from users,
  use Laravel's conventions: a notification channel reads `routeNotificationFor<Channel>()`, which the
  host app's `User` model implements.
- **Settings in the package's own config file** (`config/<name>.php`, published under the package
  name), read with `config('<name>.…')`. Environment variables are read only in that file.
- **The package's own tests**: Orchestra Testbench, in-memory SQLite, AI and HTTP always faked.
- **No UI and no routes** in packages.

## Creating a package, step by step

Use `packages/reminders` as the example to copy from. For a package called `shopping`:

1. **Folder and `composer.json`**: `packages/shopping/composer.json` with
   - `"name": "joelfrens/home-automation-shopping"`
   - PSR-4 autoload `"Joelfrens\\HomeAutomation\\Shopping\\": "src/"` (tests: `…\\Shopping\\Tests\\`
     → `tests/`)
   - only the `illuminate/*` parts you use (plus `laravel/ai` if it has an agent), and
     `orchestra/testbench` in `require-dev`
   - auto-discovery: `"extra": {"laravel": {"providers": ["Joelfrens\\HomeAutomation\\Shopping\\ShoppingServiceProvider"]}}`
2. **Service provider** `src/ShoppingServiceProvider.php`: `mergeConfigFrom` the config file,
   `loadMigrationsFrom` the migrations, register commands and singletons, add schedules in `boot()`.
3. **Config and migrations**: `config/shopping.php`, `database/migrations/<date>_create_shopping_tables.php`.
4. **Tests**: copy `phpunit.xml` and `tests/TestCase.php` from `packages/reminders` and change the
   names. Keep both safety parts **exactly**: the forced `DB_*` values in `phpunit.xml` and the
   `setUpTraits()` check that refuses anything but in-memory SQLite.
5. **Install it in the app**:
   ```bash
   docker compose exec web composer require joelfrens/home-automation-shopping:@dev
   docker compose exec web php artisan migrate     # back up the database first
   ```
   `web/composer.json` has a path repository for `../packages/*` (a symlink), and `packages/` is
   mounted at `/packages` in the containers, so edits show up immediately.
6. **CI**: add the name to the `package` matrix in `.github/workflows/ci.yml`, and to the Pint
   command in [CONTRIBUTING.md](../CONTRIBUTING.md#before-you-push).
7. **Docs**: a `README.md` in the package (what it provides, what the host app must do, how to test),
   plus a line in the main README's stack table, tests section and repository layout.

Run its tests with:

```bash
docker compose exec -w /packages/shopping web sh -c "composer install && vendor/bin/phpunit"
```

## AI agents in packages

Agents use [laravel/ai](https://github.com/laravel/ai):

- The agent class lives in the package (`src/Ai/`); its provider, model and timeout come from the
  package config, never hard-coded.
- Prefer **structured output** (a JSON schema) over parsing free text, and validate the answer like
  user input: the model can be wrong.
- The result is a **proposal** shown to a person before anything is saved.
- Tests use `MyAgent::fake([...])` and `assertPrompted(...)`; no test calls a real model.
- Cost is logged by the host app (`ai_usage` table), so packages don't need to do anything for it.
