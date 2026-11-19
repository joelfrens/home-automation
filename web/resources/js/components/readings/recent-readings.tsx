import { roomLabel } from '@/components/readings/room-card';
import type { Reading } from '@/types';

export function RecentReadings({ readings }: { readings: Reading[] }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead className="text-left text-xs text-muted-foreground">
                    <tr className="border-b">
                        <th className="py-2 pr-4 font-medium">Time</th>
                        <th className="py-2 pr-4 font-medium">Room</th>
                        <th className="py-2 pr-4 text-right font-medium">
                            Temp °C
                        </th>
                        <th className="py-2 pr-4 text-right font-medium">
                            Humidity %
                        </th>
                        <th className="py-2 text-right font-medium">
                            Pressure hPa
                        </th>
                    </tr>
                </thead>
                <tbody className="tabular-nums">
                    {readings.map((r) => (
                        <tr key={r.id} className="border-b last:border-0">
                            <td className="py-2 pr-4 whitespace-nowrap text-muted-foreground">
                                {new Date(r.recordedAt).toLocaleString(
                                    undefined,
                                    {
                                        day: '2-digit',
                                        month: 'short',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        second: '2-digit',
                                    },
                                )}
                            </td>
                            <td className="py-2 pr-4">{roomLabel(r.room)}</td>
                            <td className="py-2 pr-4 text-right">
                                {r.temperature.toFixed(2)}
                            </td>
                            <td className="py-2 pr-4 text-right">
                                {r.humidity.toFixed(2)}
                            </td>
                            <td className="py-2 text-right">
                                {r.pressure.toFixed(2)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
