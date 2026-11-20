<?php

return [

    // Registry of sensor types and their metrics (shared with the Node ingestion service).
    'types_path' => env('SENSOR_TYPES_PATH', __DIR__.'/../sensor-types.json'),

    // A sensor sends every 5 s. After this many seconds without a reading it is shown as "stale",
    // and after offline_after_seconds as "offline" (same thresholds as the Grafana panel).
    'stale_after_seconds' => 30,
    'offline_after_seconds' => 300,

    // How far back latestPerRoom() looks. Sensors silent for longer drop off the dashboard.
    'latest_lookback_hours' => 168,

];
