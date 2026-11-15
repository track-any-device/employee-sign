import { Head, Link, router, useForm } from '@inertiajs/react';
import { GitMerge, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { NativeSelect } from '@/components/devices/native-select';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, index, store, update } from '@/routes/employees';
import { create as mergeCreate } from '@/routes/employees/merge';
import type { EmployeeRow, Paginated } from '@/types';

type Props = {
    employees: Paginated<EmployeeRow>;
    devices: { id: string; name: string }[];
    filters: { search: string };
};

export default function EmployeesIndex({ employees, devices, filters }: Props) {
    const [editing, setEditing] = useState<EmployeeRow | 'new' | null>(null);
    const [search, setSearch] = useState(filters.search);

    return (
        <>
            <Head title="Employees" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Employees"
                        description={`${employees.total} employees`}
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={mergeCreate()}>
                                <GitMerge /> Merge employees
                            </Link>
                        </Button>
                        <Button onClick={() => setEditing('new')}>
                            <Plus /> Add employee
                        </Button>
                    </div>
                </div>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(index.url(), search ? { search } : {}, {
                            preserveState: true,
                        });
                    }}
                    className="max-w-sm"
                >
                    <Input
                        placeholder="Search name or employee number…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </form>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">
                                    Employee no.
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Devices
                                </th>
                                <th className="px-4 py-3 font-medium">Face</th>
                                <th className="px-4 py-3 font-medium">
                                    Fingerprint
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {employees.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No employees found.
                                    </td>
                                </tr>
                            )}
                            {employees.data.map((e) => (
                                <tr key={e.id} className="hover:bg-muted/30">
                                    <td className="px-4 py-3 font-medium">
                                        {e.name}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {e.code ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                e.status === 'active'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                            className="capitalize"
                                        >
                                            {e.status}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        {e.all_devices
                                            ? 'All devices'
                                            : e.devices_count}
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {e.templates.face_mfn_s8_v1} / 3
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {e.templates.fp_zn600}
                                    </td>
                                    <td className="px-4 py-3 text-right whitespace-nowrap">
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            aria-label="Edit"
                                            onClick={() => setEditing(e)}
                                        >
                                            <Pencil />
                                        </Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            aria-label="Delete"
                                            onClick={() =>
                                                window.confirm(
                                                    `Delete ${e.name}? Devices will remove them on next sync.`,
                                                ) &&
                                                router.delete(
                                                    destroy.url(e.id),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {employees.last_page > 1 && (
                    <div className="flex items-center gap-3 text-sm">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!employees.prev_page_url}
                            asChild={!!employees.prev_page_url}
                        >
                            {employees.prev_page_url ? (
                                <Link href={employees.prev_page_url}>
                                    Previous
                                </Link>
                            ) : (
                                <span>Previous</span>
                            )}
                        </Button>
                        <span>
                            Page {employees.current_page} of{' '}
                            {employees.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!employees.next_page_url}
                            asChild={!!employees.next_page_url}
                        >
                            {employees.next_page_url ? (
                                <Link href={employees.next_page_url}>Next</Link>
                            ) : (
                                <span>Next</span>
                            )}
                        </Button>
                    </div>
                )}
            </div>

            {editing !== null && (
                <EmployeeDialog
                    employee={editing === 'new' ? null : editing}
                    devices={devices}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

function EmployeeDialog({
    employee,
    devices,
    onClose,
}: {
    employee: EmployeeRow | null;
    devices: Props['devices'];
    onClose: () => void;
}) {
    const form = useForm({
        name: employee?.name ?? '',
        code: employee?.code ?? '',
        status: employee?.status ?? 'active',
        all_devices: employee?.all_devices ?? false,
        device_ids: employee?.device_ids ?? ([] as string[]),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, code: d.code || null }));
        const options = { preserveScroll: true, onSuccess: onClose };

        if (employee) {
            form.put(update.url(employee.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    const toggleDevice = (id: string, on: boolean) =>
        form.setData(
            'device_ids',
            on
                ? [...form.data.device_ids, id]
                : form.data.device_ids.filter((d) => d !== id),
        );

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {employee ? 'Edit employee' : 'Add employee'}
                        </DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                            autoFocus
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="code">Employee number</Label>
                            <Input
                                id="code"
                                value={form.data.code}
                                onChange={(e) =>
                                    form.setData('code', e.target.value)
                                }
                            />
                            <InputError message={form.errors.code} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="status">Status</Label>
                            <NativeSelect
                                id="status"
                                value={form.data.status}
                                onChange={(e) =>
                                    form.setData(
                                        'status',
                                        e.target.value as 'active' | 'inactive',
                                    )
                                }
                            >
                                <option value="active">Active</option>
                                <option value="inactive">
                                    Inactive (removed from devices)
                                </option>
                            </NativeSelect>
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Allowed devices</Label>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.all_devices}
                                onCheckedChange={(v) =>
                                    form.setData('all_devices', v === true)
                                }
                            />
                            All devices
                        </label>
                        {!form.data.all_devices && (
                            <div className="max-h-48 divide-y overflow-y-auto rounded-md border">
                                {devices.length === 0 && (
                                    <p className="p-3 text-sm text-muted-foreground">
                                        No devices yet.
                                    </p>
                                )}
                                {devices.map((d) => (
                                    <label
                                        key={d.id}
                                        className="flex items-center gap-2 px-3 py-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.data.device_ids.includes(
                                                d.id,
                                            )}
                                            onCheckedChange={(v) =>
                                                toggleDevice(d.id, v === true)
                                            }
                                        />
                                        {d.name}
                                    </label>
                                ))}
                            </div>
                        )}
                        <InputError message={form.errors.device_ids} />
                    </div>
                    {employee && (
                        <p className="text-sm text-muted-foreground">
                            Biometric templates:{' '}
                            {employee.templates.face_mfn_s8_v1} face,{' '}
                            {employee.templates.fp_zn600} fingerprint. Templates
                            are enrolled on the device.
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

EmployeesIndex.layout = {
    breadcrumbs: [{ title: 'Employees', href: index() }],
};
