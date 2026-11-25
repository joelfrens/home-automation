# joelfrens/home-automation-reminders

Laravel package for school and family events with early reminders on everyone's phone.
You give it a school letter (PDF), a screenshot or pasted text. An AI proposes the events and
reminders, the family checks them, and the scheduler pushes each reminder through
[ntfy](https://ntfy.sh). This package has no UI; the host app (`web/`) has the pages.

## What it provides

| | |
|---|---|
| `EventPlanner` | `create()`, `update()`, `upcoming()`, `present()`. Saves events in UTC from household-local input and works out each reminder's exact moment. Reminders already sent are kept when an event is edited. |
| `ReminderTime` | "N days before at HH:MM" → UTC moment in the household time zone. Handles clock changes (19:00 is 18:00 UTC in summer). |
| `EventExtractor` + `Ai\ExtractEvents` | A [laravel/ai](https://github.com/laravel/ai) agent with structured output. Reads an image or PDF and/or text, together with today's date and the children's names. Its answer is validated like user input: impossible or past dates and bad times are dropped, and children are matched by name. Nothing is saved; the result is a proposal. |
| `reminders:send` | Scheduled every minute. Each reminder is claimed before it is sent, so it never goes out twice. Reminders that are due but whose event is already over (for example after downtime) are skipped. |
| `Notifications\ReminderDue`, `Channels\NtfyChannel` | Queued notification → one HTTP POST to ntfy per family member who has a topic (`routeNotificationForNtfy()`). Same-day reminders are louder (priority 4). |
| `Notifications\NtfyTest` | "Send test notification" from the profile page. |
| Models + migration | `children`, `events`, `reminders` (`days_before`, `time`, `remind_at`, `sent_at`, `skipped_at`). |
| `config/reminders.php` | Household time zone, AI provider/model (Claude Opus by default), storage disk, ntfy server/token, notification click URL. |

## What the host app must do

- **Users:** give the user model a `routeNotificationForNtfy(): ?string` method that returns that person's topic. The package finds users through `auth.providers.users.model` and never touches user columns itself.
- **Scheduler and queue:** keep `php artisan schedule:work` (the scheduler) and `php artisan queue:work` (sends the notifications) running.
- **AI provider:** configure laravel/ai with an `anthropic` provider and `ANTHROPIC_API_KEY`.

## Tests

Testbench, in-memory SQLite, and the AI always faked (`ExtractEvents::fake()`), so no test calls a real model:

```bash
docker compose exec -w /packages/reminders web sh -c "composer install && vendor/bin/phpunit"
```
