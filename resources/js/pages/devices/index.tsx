import { Head, Link, router, useForm } from '@inertiajs/react';
import { KeyRound, Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { OnlineDot, StatusBadge } from '@/components/devices/device-status';
import { NativeSelect } from '@/components/devices/native-select';
import { PairingCodeDialog } from '@/components/devices/pairing-code-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useDeviceLive } from '@/hooks/use-device-live';
import { timeAgo } from '@/lib/format';
import { index, show, store } from '@/routes/devices';
import { store as createPairingCode } from '@/routes/devices/pairing';
import type { DeviceRow, SiteOption } from '@/types';

type Props = {
    devices: DeviceRow[];
    sites: SiteOption[];
    channel: string;
};

export default function DevicesIndex({
    devices: initial,
    sites,
    channel,
}: Props) {
    const { devices, pairing, claimed, closePairing } = useDeviceLive(
        channel,
        initial,
    );
    const [adding, setAdding] = useState(false);

    const pairingDevice = devices.find((d) => d.id === pairing?.device_id);

    return (
        <>
            <Head title="Devices" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Devices"
                        description="Attendance terminals, their pairing and live connection."
                    />
                    <Button onClick={() => setAdding(true)}>
                        <Plus /> Add device
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">
                                    Site / location
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Connection
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Last seen
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Firmware
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {devices.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No devices yet. Add one, then create a
                                        pairing code.
                                    </td>
                                </tr>
                            )}
                            {devices.map((device) => (
                                <tr
                                    key={device.id}
                                    className="hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={show(device.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {device.name}
                                        </Link>
                                        {device.hardware_id && (
                                            <span className="ml-2 font-mono text-xs text-muted-foreground">
                                                {device.hardware_id}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {[device.site, device.location]
                                            .filter(Boolean)
                                            .join(' / ') || '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusBadge status={device.status} />
                                    </td>
                                    <td className="px-4 py-3">
                                        <OnlineDot online={device.online} />
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {timeAgo(device.last_seen_at)}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {device.firmware_version ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    createPairingCode.url(
                                                        device.id,
                                                    ),
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
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <AddDeviceDialog
                open={adding}
                onClose={() => setAdding(false)}
                sites={sites}
            />
            <PairingCodeDialog
                pairing={pairing}
                deviceName={pairingDevice?.name ?? 'device'}
                claimed={claimed}
                onClose={closePairing}
            />
        </>
    );
}

export function SiteLocationFields({
    sites,
    siteId,
    locationId,
    onChange,
    errors,
}: {
    sites: SiteOption[];
    siteId: string;
    locationId: string;
    onChange: (siteId: string, locationId: string) => void;
    errors: { site_id?: string; location_id?: string };
}) {
    const locations =
        sites.find((s) => String(s.id) === siteId)?.locations ?? [];

    if (sites.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="site_id">Site (optional)</Label>
                <NativeSelect
                    id="site_id"
                    value={siteId}
                    onChange={(e) => onChange(e.target.value, '')}
                >
                    <option value="">—</option>
                    {sites.map((s) => (
                        <option key={s.id} value={s.id}>
                            {s.name}
                        </option>
                    ))}
                </NativeSelect>
                <InputError message={errors.site_id} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="location_id">Location (optional)</Label>
                <NativeSelect
                    id="location_id"
                    value={locationId}
                    disabled={locations.length === 0}
                    onChange={(e) => onChange(siteId, e.target.value)}
                >
                    <option value="">—</option>
                    {locations.map((l) => (
                        <option key={l.id} value={l.id}>
                            {l.name}
                        </option>
                    ))}
                </NativeSelect>
                <InputError message={errors.location_id} />
            </div>
        </div>
    );
}

function AddDeviceDialog({
    open,
    onClose,
    sites,
}: {
    open: boolean;
    onClose: () => void;
    sites: SiteOption[];
}) {
    const form = useForm({ name: '', site_id: '', location_id: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            name: data.name,
            site_id: data.site_id || null,
            location_id: data.location_id || null,
        }));
        form.post(store.url(), { onSuccess: () => form.reset() });
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Add device</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="Main gate"
                            required
                            autoFocus
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
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Add device
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

DevicesIndex.layout = {
    breadcrumbs: [{ title: 'Devices', href: index() }],
};
