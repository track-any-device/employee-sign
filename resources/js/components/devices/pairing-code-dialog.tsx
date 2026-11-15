import { router } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { store as createPairingCode } from '@/routes/devices/pairing';
import type { PairingFlash } from '@/types';

type Props = {
    pairing: PairingFlash | null;
    deviceName: string;
    claimed: boolean;
    onClose: () => void;
};

function useCountdown(expiresAt: string | null): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        if (!expiresAt) {
            return;
        }

        const id = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(id);
    }, [expiresAt]);

    if (!expiresAt) {
        return 0;
    }

    return Math.max(
        0,
        Math.round((new Date(expiresAt).getTime() - now) / 1000),
    );
}

export function PairingCodeDialog({
    pairing,
    deviceName,
    claimed,
    onClose,
}: Props) {
    const remaining = useCountdown(pairing?.expires_at ?? null);
    const expired = pairing !== null && remaining === 0 && !claimed;
    const minutes = Math.floor(remaining / 60);
    const seconds = String(remaining % 60).padStart(2, '0');

    const newCode = () => {
        if (pairing) {
            router.post(
                createPairingCode.url(pairing.device_id),
                {},
                { preserveScroll: true, preserveState: true },
            );
        }
    };

    return (
        <Dialog
            open={pairing !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Pair “{deviceName}”</DialogTitle>
                    <DialogDescription>
                        On the device choose “Pair with server” and enter this
                        one-time code. It is shown only once.
                    </DialogDescription>
                </DialogHeader>

                {claimed ? (
                    <div className="flex flex-col items-center gap-3 py-6 text-center">
                        <CheckCircle2 className="size-12 text-emerald-500" />
                        <p className="text-lg font-medium">Device paired</p>
                    </div>
                ) : (
                    <div className="flex flex-col items-center gap-3 py-4">
                        <p
                            className={`font-mono text-4xl font-semibold tracking-[0.15em] tabular-nums select-all ${expired ? 'text-muted-foreground line-through' : ''}`}
                            aria-live="polite"
                        >
                            {pairing?.code}
                        </p>
                        <p className="text-sm text-muted-foreground tabular-nums">
                            {expired
                                ? 'This code has expired.'
                                : `Expires in ${minutes}:${seconds}`}
                        </p>
                    </div>
                )}

                <DialogFooter>
                    {!claimed && (
                        <Button variant="outline" onClick={newCode}>
                            New code
                        </Button>
                    )}
                    <Button onClick={onClose}>
                        {claimed ? 'Done' : 'Close'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
