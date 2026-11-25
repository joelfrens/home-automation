/** Outdoor weather from packages/weather (Open-Meteo), for the dashboard's "Outside" card. */
export type WeatherNow = {
    temperature: number;
    time: string; // ISO 8601
};

export type WeatherOutlook = {
    minTemperature: number;
    maxTemperature: number;
    rainChance: number; // percent, highest hour in the window
    hours: number;
};

export type Weather = {
    enabled: boolean; // false = no location set in .env
    current: WeatherNow | null; // null = weather service unavailable right now
    next: WeatherOutlook | null; // the next 3 hours
};
