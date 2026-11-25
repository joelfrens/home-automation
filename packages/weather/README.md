# joelfrens/home-automation-weather

Laravel package for the outdoor weather at home, from [Open-Meteo](https://open-meteo.com) (free for
non-commercial use, no account or API key). Indoor sensors say how warm the living room is; this
says whether the children need gloves on the way to school.

## What it provides

| | |
|---|---|
| `Forecast::current()` | Temperature now (`Current`: temperature, time), or null. |
| `Forecast::between($from, $to)` | The weather during a time window, e.g. the school run (`Outlook`: coldest and warmest hour, highest rain chance, hours covered), or null. |
| `Forecast::enabled()` | False when no location is configured; then nothing is ever fetched. |
| `php artisan weather:show` | Quick check: temperature now and the next 3 hours. |
| `config/weather.php` | Location, time zone, cache and timeout settings. |

## How it behaves

- **One request, shared.** Today and tomorrow are fetched hour by hour in one request and cached
  (30 minutes by default), however many screens and widgets ask.
- **Never breaks a page.** Timeouts, server errors and unexpected answers are logged and return
  null; after a failure it waits 5 minutes before trying again.
- **Clock-change safe.** Hours are requested as Unix timestamps (UTC), so summer/winter time can't
  shift them.
- **Privacy.** Only the coordinates, rounded to 2 decimals (about 1 km), are sent.

## What the host app must do

Set the location in `.env` (empty = weather off):

```env
WEATHER_LATITUDE=51.51
WEATHER_LONGITUDE=-0.13
```

## Tests

Testbench, with every HTTP request faked (`Http::preventStrayRequests()`), so no test reaches
Open-Meteo:

```bash
docker compose exec -w /packages/weather web sh -c "composer install && vendor/bin/phpunit"
```
