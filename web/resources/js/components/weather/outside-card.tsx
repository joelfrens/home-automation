import { CloudRain, Thermometer } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { Weather } from '@/types';

const degrees = (value: number) => `${Math.round(value)} °C`;

/** Dashboard card: outdoor temperature now and the next 3 hours (packages/weather). */
export function OutsideCard({ weather }: { weather: Weather }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Outside</CardTitle>
            </CardHeader>
            <CardContent>
                {!weather.enabled ? (
                    <p className="text-sm text-muted-foreground">
                        Set <code>WEATHER_LATITUDE</code> and{' '}
                        <code>WEATHER_LONGITUDE</code> in <code>web/.env</code>{' '}
                        to see the weather.
                    </p>
                ) : !weather.current ? (
                    <p className="text-sm text-muted-foreground">
                        Weather unavailable right now. It will try again in a
                        few minutes.
                    </p>
                ) : (
                    <div className="flex flex-wrap items-end gap-x-6 gap-y-2">
                        <p className="flex items-center gap-2 text-4xl font-semibold tabular-nums">
                            <Thermometer
                                className="size-7 text-muted-foreground"
                                aria-hidden
                            />
                            {degrees(weather.current.temperature)}
                        </p>
                        {weather.next && (
                            <div className="space-y-1 text-sm text-muted-foreground">
                                <p>
                                    Next {weather.next.hours} h:{' '}
                                    {degrees(weather.next.minTemperature)} to{' '}
                                    {degrees(weather.next.maxTemperature)}
                                </p>
                                <p className="flex items-center gap-1.5">
                                    <CloudRain className="size-4" aria-hidden />
                                    Rain chance {weather.next.rainChance}%
                                </p>
                            </div>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

/** Shown while the deferred weather prop is loading. */
export function OutsideCardSkeleton() {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Outside</CardTitle>
            </CardHeader>
            <CardContent>
                <Skeleton className="h-10 w-40" />
            </CardContent>
        </Card>
    );
}
