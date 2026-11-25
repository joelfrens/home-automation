<?php

return [

    /*
     * Where the forecast is for. Leave empty to switch the weather off (no requests are made).
     * Rounded to 2 decimals (about 1 km) before being sent, so the exact address never leaves home.
     */
    'latitude' => env('WEATHER_LATITUDE'),
    'longitude' => env('WEATHER_LONGITUDE'),

    // Hours in the forecast are in this time zone (same as the household's).
    'timezone' => env('HOUSEHOLD_TIMEZONE', 'Europe/London'),

    // Open-Meteo: free for non-commercial use, no account or API key.
    'base_url' => env('WEATHER_BASE_URL', 'https://api.open-meteo.com/v1/forecast'),
    'timeout' => (int) env('WEATHER_TIMEOUT', 5),

    // One request per this many minutes at most, whatever number of screens are open.
    'cache_minutes' => (int) env('WEATHER_CACHE_MINUTES', 30),

    // After a failed request, wait this long before trying again.
    'retry_after_minutes' => (int) env('WEATHER_RETRY_AFTER_MINUTES', 5),

    // Today + tomorrow covers "this morning" and "tomorrow morning".
    'forecast_days' => (int) env('WEATHER_FORECAST_DAYS', 2),

];
