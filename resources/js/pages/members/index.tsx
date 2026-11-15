import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/format';
import { index } from '@/routes/members';

type Member = {
    uuid: string;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
};

export default function MembersIndex({ members }: { members: Member[] }) {
    return (
        <>
            <Head title="Members" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Members"
                    description="People who can sign in and manage devices and employees."
                />
                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[560px] text-sm">
                        <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Email</th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Joined
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {members.map((m) => (
                                <tr key={m.uuid}>
                                    <td className="px-4 py-3 font-medium">
                                        {m.name}
                                    </td>
                                    <td className="px-4 py-3">{m.email}</td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                m.email_verified_at
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {m.email_verified_at
                                                ? 'Verified'
                                                : 'Unverified'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {formatDateTime(m.created_at)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

MembersIndex.layout = {
    breadcrumbs: [{ title: 'Members', href: index() }],
};
