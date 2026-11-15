import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { DeviceStatus } from '@/types';

export function OnlineDot({ online }: { online: boolean }) {
    return (
        <span className="inline-flex items-center gap-2 text-sm">
            <span
                aria-hidden
                className={cn(
                    'size-2.5 rounded-full',
                    online
                        ? 'bg-emerald-500 shadow-[0_0_0_3px] shadow-emerald-500/20'
                        : 'bg-neutral-300 dark:bg-neutral-600',
                )}
            />
            {online ? 'Online' : 'Offline'}
        </span>
    );
}

export function StatusBadge({ status }: { status: DeviceStatus }) {
    const variant =
        status === 'active'
            ? 'default'
            : status === 'revoked'
              ? 'destructive'
              : 'secondary';

    return (
        <Badge variant={variant} className="capitalize">
            {status}
        </Badge>
    );
}
