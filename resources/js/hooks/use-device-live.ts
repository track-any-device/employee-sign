import { useEcho } from '@laravel/echo-react';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import type {
    DevicePairedPayload,
    DeviceRow,
    DeviceStatusPayload,
    PairingFlash,
} from '@/types';

/**
 * Live device rows (online/offline via Reverb) plus the one-time pairing
 * code flashed by the server, closed automatically when the device claims it.
 */
export function useDeviceLive<T extends Pick<DeviceRow, 'id'>>(
    channel: string,
    initial: T[],
) {
    const [devices, setDevices] = useState(initial);
    const [pairing, setPairing] = useState<PairingFlash | null>(null);
    const [claimed, setClaimed] = useState(false);
    const pairingRef = useRef<PairingFlash | null>(null);
    const flash = usePage().flash;

    useEffect(() => {
        pairingRef.current = pairing;
    }, [pairing]);

    useEffect(() => setDevices(initial), [initial]);

    useEffect(() => {
        if (flash?.pairing) {
            setPairing(flash.pairing);
            setClaimed(false);
        }
    }, [flash]);

    useEcho<DeviceStatusPayload>(channel, '.DeviceStatusChanged', (e) => {
        setDevices((rows) =>
            rows.map((row) =>
                row.id === e.device_id
                    ? {
                          ...row,
                          status: e.status,
                          online: e.online,
                          last_seen_at: e.last_seen_at,
                          firmware_version: e.firmware_version,
                      }
                    : row,
            ),
        );
    });

    useEcho<DevicePairedPayload>(channel, '.DevicePaired', (e) => {
        toast.success(
            `${e.name} paired${e.hardware_id ? ` (${e.hardware_id})` : ''}.`,
        );
        if (pairingRef.current?.device_id === e.device_id) {
            setClaimed(true);
            router.reload();
        }
    });

    return {
        devices,
        pairing,
        claimed,
        closePairing: () => {
            setPairing(null);
            setClaimed(false);
        },
    };
}
