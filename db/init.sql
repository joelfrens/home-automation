-- Runs once, the first time the PostgreSQL container starts with an empty volume.
-- (Later, Laravel migrations can take ownership of this table.)
CREATE TABLE IF NOT EXISTS sensor_readings (
    id            BIGSERIAL PRIMARY KEY,
    room          TEXT        NOT NULL,
    sensor        TEXT        NOT NULL,
    temperature_c REAL        NOT NULL,
    humidity_pct  REAL        NOT NULL,
    pressure_hpa  REAL        NOT NULL,
    recorded_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS sensor_readings_room_time_idx
    ON sensor_readings (room, recorded_at DESC);
