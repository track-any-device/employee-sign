import { Head, router, useForm } from '@inertiajs/react';
import {
    KeyRound,
    MessageSquare,
    Power,
    RefreshCw,
    ShieldOff,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { OnlineDot, StatusBadge } from '@/components/devices/device-status';
import { PairingCodeDialog } from '@/components/devices/pairing-code-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useDeviceLive } from '@/hooks/use-device-live';
import { formatDateTime, timeAgo } from '@/lib/format';
import { SiteLocationFields } from '@/pages/devices/index';
import { destroy, index, update } from '@/routes/devices';
import { store as issueCommand } from '@/routes/devices/commands';
import { update as updateEmployees } from '@/routes/devices/employees';
import { store as createPairingCode } from '@/routes/devices/pairing';
import { destroy as revokeToken } from '@/routes/devices/token';
import type { DeviceRow, SiteOption } from '@/types';

type DeviceDetail = DeviceRow & {
    mac: string | null;
    model: string | null;
    variant: string | null;
    hardware_id_full: string | null;
    claimed_at: string | null;
    last_ip: string | null;
    last_stats: {
        uptime_s?: number | null;
        free_heap?: number | null;
        at?: string;
    } | null;
    site_id: number | null;
    location_id: number | null;
    has_token: boolean;
};

type Props = {
    device: DeviceDetail;
    livePairingExpiresAt: string | null;
    sites: SiteOption[];
    employees: {
        id: string;
        name: string;
        code: string | null;
        all_devices: boolean;
    }[];
    allowedEmployeeIds: string[];
    attendance: {
        id: number;
        employee: string | null;
        occurred_at: string | null;
        received_at: string;
        method: string;
        similarity: number | null;
    }[];
    connectionLog: {
        id: number;
        event: string;
        ip: string | null;
        meta: { reason?: string } | null;
        created_at: string;
    }[];
    commands: {
        id: string;
        name: string;
        args: Record<string, unknown> | null;
        status: string;
        issued_at: string;
        completed_at: string | null;
        result_detail: string | null;
    }[];
    channel: string;
};

export default function DeviceShow(props: Props) {
    const initial = useMemo(() => [props.device], [props.device]);
    const { devices, pairing, claimed, closePairing } = useDeviceLive(
        props.channel,
        initial,
    );
    const device = devices[0];

    const confirmThen = (message: string, fn: () => void) => {
        if (window.confirm(message)) {
            fn();
        }
    };

    return (
        <>
            <Head title={device.name} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <Heading
                            title={device.name}
                            description={
                                [device.site, device.location]
                                    .filter(Boolean)
                                    .join(' / ') || undefined
                            }
                        />
                        <div className="flex flex-wrap items-center gap-3">
                            <StatusBadge status={device.status} />
                            <OnlineDot online={device.online} />
                            <span className="text-sm text-muted-foreground">
                                Last seen {timeAgo(device.last_seen_at)}
                            </span>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            onClick={() =>
                                router.post(
                                    createPairingCode.url(device.id),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <KeyRound />{' '}
                            {device.status === 'active'
                                ? 'Re-pair'
                                : 'Create OTT'}
                        </Button>
                        {props.device.has_token && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    confirmThen(
                                        'Revoke this device? It will stop syncing until it is paired again.',
                                        () =>
                                            router.delete(
                                                revokeToken.url(device.id),
                                                { preserveScroll: true },
                                            ),
                                    )
                                }
                            >
                                <ShieldOff /> Revoke device
                            </Button>
                        )}
                    </div>
                </div>

                {props.livePairingExpiresAt && !pairing && (
                    <p className="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm">
                        A pairing code is live until{' '}
                        {formatDateTime(props.livePairingExpiresAt)}. Codes are
                        shown only once; create a new one if you lost it.
                    </p>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <DetailsCard device={props.device} sites={props.sites} />
                    <HardwareCard device={props.device} />
                    <CommandsCard device={device} commands={props.commands} />
                    <AllowedEmployeesCard
                        deviceId={device.id}
                        employees={props.employees}
                        allowed={props.allowedEmployeeIds}
                    />
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent attendance</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <SimpleTable
                                empty="No attendance uploaded yet."
                                head={[
                                    'Employee',
                                    'Time',
                                    'Method',
                                    'Similarity',
                                ]}
                                rows={props.attendance.map((a) => [
                                    a.employee ?? (
                                        <span className="text-muted-foreground">
                                            Unknown
                                        </span>
                                    ),
                                    a.occurred_at ? (
                                        formatDateTime(a.occurred_at)
                                    ) : (
                                        <span title="Device clock was not set">
                                            received{' '}
                                            {formatDateTime(a.received_at)}
                                        </span>
                                    ),
                                    a.method,
                                    a.similarity?.toFixed(2) ?? '—',
                                ])}
                            />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Connection log</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <SimpleTable
                                empty="No connection events."
                                head={['Event', 'When', 'IP']}
                                rows={props.connectionLog.map((l) => [
                                    <span className="capitalize">
                                        {l.event}
                                        {l.meta?.reason
                                            ? ` (${l.meta.reason})`
                                            : ''}
                                    </span>,
                                    formatDateTime(l.created_at),
                                    l.ip ?? '—',
                                ])}
                            />
                        </CardContent>
                    </Card>
                </div>

                <div>
                    <Button
                        variant="ghost"
                        className="text-destructive"
                        onClick={() =>
                            confirmThen(
                                'Delete this device and its attendance history?',
                                () => router.delete(destroy.url(device.id)),
                            )
                        }
                    >
                        <Trash2 /> Delete device
                    </Button>
                </div>
            </div>

            <PairingCodeDialog
                pairing={pairing}
                deviceName={device.name}
                claimed={claimed}
                onClose={closePairing}
            />
        </>
    );
}

function DetailsCard({
    device,
    sites,
}: {
    device: DeviceDetail;
    sites: SiteOption[];
}) {
    const form = useForm({
        name: device.name,
        site_id: device.site_id ? String(device.site_id) : '',
        location_id: device.location_id ? String(device.location_id) : '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            name: d.name,
            site_id: d.site_id || null,
            location_id: d.location_id || null,
        }));
        form.put(update.url(device.id), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Details</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <SiteLocationFields
                        sites={sites}
                        siteId={form.data.site_id}
                        locationId={form.data.location_id}
                        onChange={(site_id, location_id) =>
                            form.setData({ ...form.data, site_id, location_id })
                        }
                        errors={form.errors}
                    />
                    <div>
                        <Button
                            type="submit"
                            disabled={form.processing || !form.isDirty}
                        >
                            Save
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function HardwareCard({ device }: { device: DeviceDetail }) {
    const rows: [string, string | null | undefined][] = [
        ['Hardware ID', device.hardware_id_full],
        ['MAC', device.mac],
        ['Model', device.model],
        ['Variant', device.variant],
        ['Firmware', device.firmware_version],
        [
            'Paired',
            device.claimed_at ? formatDateTime(device.claimed_at) : null,
        ],
        ['Last IP', device.last_ip],
        [
            'Uptime',
            device.last_stats?.uptime_s != null
                ? `${Math.round(device.last_stats.uptime_s / 60)} min`
                : null,
        ],
        [
            'Free heap',
            device.last_stats?.free_heap != null
                ? `${Math.round(device.last_stats.free_heap / 1024)} KB`
                : null,
        ],
    ];

    return (
        <Card>
            <CardHeader>
                <CardTitle>Hardware</CardTitle>
                <CardDescription>
                    Reported by the device when it paired and on heartbeat.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                    {rows.map(([label, value]) => (
                        <div key={label} className="contents">
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd className="font-mono break-all">
                                {value ?? '—'}
                            </dd>
                        </div>
                    ))}
                </dl>
            </CardContent>
        </Card>
    );
}

function CommandsCard({
    device,
    commands,
}: {
    device: DeviceRow;
    commands: Props['commands'];
}) {
    const [text, setText] = useState('');
    const [seconds, setSeconds] = useState('10');
    const disabled = device.status !== 'active';

    const send = (name: string, args?: Record<string, string | number>) =>
        router.post(
            issueCommand.url(device.id),
            { name, args },
            { preserveScroll: true },
        );

    return (
        <Card>
            <CardHeader>
                <CardTitle>Commands</CardTitle>
                <CardDescription>
                    {device.online
                        ? 'Sent immediately over the live connection.'
                        : 'The device is offline: commands wait until it reconnects (10 min).'}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={disabled}
                        onClick={() => send('sync_now')}
                    >
                        <RefreshCw /> Sync now
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={disabled}
                        onClick={() =>
                            window.confirm('Reboot the device?') &&
                            send('reboot')
                        }
                    >
                        <Power /> Reboot
                    </Button>
                </div>
                <form
                    className="flex flex-wrap items-end gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        send('show_message', {
                            text,
                            seconds: Number(seconds),
                        });
                        setText('');
                    }}
                >
                    <div className="grid flex-1 gap-1">
                        <Label htmlFor="msg">Show message</Label>
                        <Input
                            id="msg"
                            value={text}
                            maxLength={120}
                            onChange={(e) => setText(e.target.value)}
                            placeholder="Please look at the camera"
                        />
                    </div>
                    <div className="grid w-20 gap-1">
                        <Label htmlFor="secs">Seconds</Label>
                        <Input
                            id="secs"
                            type="number"
                            min={1}
                            max={600}
                            value={seconds}
                            onChange={(e) => setSeconds(e.target.value)}
                        />
                    </div>
                    <Button
                        type="submit"
                        size="sm"
                        disabled={disabled || text === ''}
                    >
                        <MessageSquare /> Send
                    </Button>
                </form>
                <SimpleTable
                    empty="No commands yet."
                    head={['Command', 'Issued', 'Status']}
                    rows={commands.map((c) => [
                        <span className="font-mono">{c.name}</span>,
                        formatDateTime(c.issued_at),
                        <span title={c.result_detail ?? undefined}>
                            <Badge
                                variant={
                                    c.status === 'failed' ||
                                    c.status === 'expired'
                                        ? 'destructive'
                                        : c.status === 'done'
                                          ? 'default'
                                          : 'secondary'
                                }
                            >
                                {c.status}
                            </Badge>
                        </span>,
                    ])}
                />
            </CardContent>
        </Card>
    );
}

function AllowedEmployeesCard({
    deviceId,
    employees,
    allowed,
}: {
    deviceId: string;
    employees: Props['employees'];
    allowed: string[];
}) {
    const [selected, setSelected] = useState(() => new Set(allowed));
    const [filter, setFilter] = useState('');
    const dirty =
        selected.size !== allowed.length ||
        allowed.some((id) => !selected.has(id));

    const toggle = (id: string, on: boolean) => {
        const next = new Set(selected);
        if (on) {
            next.add(id);
        } else {
            next.delete(id);
        }
        setSelected(next);
    };

    const visible = employees.filter((e) =>
        `${e.name} ${e.code ?? ''}`
            .toLowerCase()
            .includes(filter.toLowerCase()),
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle>Allowed employees</CardTitle>
                <CardDescription>
                    Only these employees (and those allowed on all devices) are
                    synced to this device.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3">
                <Input
                    placeholder="Filter…"
                    value={filter}
                    onChange={(e) => setFilter(e.target.value)}
                />
                <div className="max-h-72 divide-y overflow-y-auto rounded-md border">
                    {visible.length === 0 && (
                        <p className="p-3 text-sm text-muted-foreground">
                            No employees.
                        </p>
                    )}
                    {visible.map((e) => (
                        <label
                            key={e.id}
                            className="flex items-center gap-3 px-3 py-2 text-sm"
                        >
                            <Checkbox
                                checked={e.all_devices || selected.has(e.id)}
                                disabled={e.all_devices}
                                onCheckedChange={(v) =>
                                    toggle(e.id, v === true)
                                }
                            />
                            <span className="flex-1">{e.name}</span>
                            {e.code && (
                                <span className="font-mono text-xs text-muted-foreground">
                                    {e.code}
                                </span>
                            )}
                            {e.all_devices && (
                                <Badge variant="secondary">All devices</Badge>
                            )}
                        </label>
                    ))}
                </div>
                <div>
                    <Button
                        size="sm"
                        disabled={!dirty}
                        onClick={() =>
                            router.put(
                                updateEmployees.url(deviceId),
                                { employee_ids: [...selected] },
                                { preserveScroll: true },
                            )
                        }
                    >
                        Save allowed employees
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function SimpleTable({
    head,
    rows,
    empty,
}: {
    head: string[];
    rows: React.ReactNode[][];
    empty: string;
}) {
    if (rows.length === 0) {
        return <p className="text-sm text-muted-foreground">{empty}</p>;
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead className="text-left text-xs text-muted-foreground uppercase">
                    <tr>
                        {head.map((h) => (
                            <th key={h} className="py-2 pr-4 font-medium">
                                {h}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {rows.map((row, i) => (
                        <tr key={i}>
                            {row.map((cell, j) => (
                                <td key={j} className="py-2 pr-4">
                                    {cell}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

DeviceShow.layout = {
    breadcrumbs: [{ title: 'Devices', href: index() }],
};
