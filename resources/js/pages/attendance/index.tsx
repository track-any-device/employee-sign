import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { NativeSelect } from '@/components/devices/native-select';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import { index } from '@/routes/attendance';
import type { Paginated, SiteOption } from '@/types';

type Event = {
    id: number;
    employee: { id: string; name: string; code: string | null } | null;
    device: string | null;
    occurred_at: string | null;
    received_at: string;
    method: string;
    similarity: number | null;
};

type Filters = {
    site_id?: string;
    location_id?: string;
    device_id?: string;
    from?: string;
    to?: string;
};

export default function AttendanceIndex({
    events,
    filters: initial,
    sites,
    devices,
}: {
    events: Paginated<Event>;
    filters: Filters;
    sites: SiteOption[];
    devices: { id: string; name: string }[];
}) {
    const [filters, setFilters] = useState<Filters>(initial);
    const locations =
        sites.find((s) => String(s.id) === String(filters.site_id ?? ''))
            ?.locations ?? [];

    const apply = (next: Filters) => {
        setFilters(next);
        const query = Object.fromEntries(
            Object.entries(next).filter(([, v]) => v),
        );
        router.get(index.url(), query, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Attendance" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Attendance"
                    description={`${events.total} records`}
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    {sites.length > 0 && (
                        <>
                            <div className="grid gap-1">
                                <Label htmlFor="site">Site</Label>
                                <NativeSelect
                                    id="site"
                                    value={filters.site_id ?? ''}
                                    onChange={(e) =>
                                        apply({
                                            ...filters,
                                            site_id: e.target.value,
                                            location_id: '',
                                        })
                                    }
                                >
                                    <option value="">All sites</option>
                                    {sites.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="location">Location</Label>
                                <NativeSelect
                                    id="location"
                                    value={filters.location_id ?? ''}
                                    disabled={locations.length === 0}
                                    onChange={(e) =>
                                        apply({
                                            ...filters,
                                            location_id: e.target.value,
                                        })
                                    }
                                >
                                    <option value="">All locations</option>
                                    {locations.map((l) => (
                                        <option key={l.id} value={l.id}>
                                            {l.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                        </>
                    )}
                    <div className="grid gap-1">
                        <Label htmlFor="device">Device</Label>
                        <NativeSelect
                            id="device"
                            value={filters.device_id ?? ''}
                            onChange={(e) =>
                                apply({ ...filters, device_id: e.target.value })
                            }
                        >
                            <option value="">All devices</option>
                            {devices.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="from">From</Label>
                        <Input
                            id="from"
                            type="date"
                            value={filters.from ?? ''}
                            onChange={(e) =>
                                apply({ ...filters, from: e.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="to">To</Label>
                        <Input
                            id="to"
                            type="date"
                            value={filters.to ?? ''}
                            onChange={(e) =>
                                apply({ ...filters, to: e.target.value })
                            }
                        />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[640px] text-sm">
                        <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">Time</th>
                                <th className="px-4 py-3 font-medium">
                                    Employee
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Device
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Method
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Similarity
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {events.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No attendance records.
                                    </td>
                                </tr>
                            )}
                            {events.data.map((e) => (
                                <tr key={e.id}>
                                    <td className="px-4 py-3 tabular-nums">
                                        {e.occurred_at ? (
                                            formatDateTime(e.occurred_at)
                                        ) : (
                                            <span
                                                className="text-muted-foreground"
                                                title="The device clock was not set"
                                            >
                                                No device time · received{' '}
                                                {formatDateTime(e.received_at)}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {e.employee ? (
                                            <>
                                                {e.employee.name}
                                                {e.employee.code && (
                                                    <span className="ml-2 font-mono text-xs text-muted-foreground">
                                                        {e.employee.code}
                                                    </span>
                                                )}
                                            </>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                Unknown
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {e.device ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 capitalize">
                                        {e.method}
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {e.similarity?.toFixed(2) ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {events.last_page > 1 && (
                    <div className="flex items-center gap-3 text-sm">
                        {events.prev_page_url && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={events.prev_page_url}>
                                    Previous
                                </Link>
                            </Button>
                        )}
                        <span>
                            Page {events.current_page} of {events.last_page}
                        </span>
                        {events.next_page_url && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={events.next_page_url}>Next</Link>
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

AttendanceIndex.layout = {
    breadcrumbs: [{ title: 'Attendance', href: index() }],
};
