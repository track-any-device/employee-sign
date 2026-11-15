import { Head, useForm } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { FormEvent } from 'react';
import { NativeSelect } from '@/components/devices/native-select';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/employees';
import { store } from '@/routes/employees/merge';

type Candidate = {
    id: string;
    name: string;
    code: string | null;
    status: string;
    templates_count: number;
    attendance_count: number;
    devices_count: number;
};

type Field = 'name' | 'code' | 'status';

export default function MergeEmployees({
    employees,
    fields,
}: {
    employees: Candidate[];
    fields: Field[];
}) {
    const form = useForm({
        survivor_id: '',
        merged_id: '',
        take_from_merged: [] as Field[],
    });
    const survivor = employees.find((e) => e.id === form.data.survivor_id);
    const merged = employees.find((e) => e.id === form.data.merged_id);

    const choose = (field: Field, fromMerged: boolean) =>
        form.setData(
            'take_from_merged',
            fromMerged
                ? [...new Set([...form.data.take_from_merged, field])]
                : form.data.take_from_merged.filter((f) => f !== field),
        );

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (
            survivor &&
            merged &&
            window.confirm(
                `Merge ${merged.name} into ${survivor.name}? This cannot be undone.`,
            )
        ) {
            form.post(store.url());
        }
    };

    const label = (e: Candidate) => `${e.name}${e.code ? ` (${e.code})` : ''}`;

    return (
        <>
            <Head title="Merge employees" />
            <form
                onSubmit={submit}
                className="flex max-w-4xl flex-1 flex-col gap-6 p-4"
            >
                <Heading
                    title="Merge employees"
                    description="Templates, device assignments and attendance move to the kept profile. The other profile is removed and devices are told on their next sync."
                />

                <div className="grid items-end gap-4 sm:grid-cols-[1fr_auto_1fr]">
                    <div className="grid gap-2">
                        <Label htmlFor="merged">Merge this profile…</Label>
                        <NativeSelect
                            id="merged"
                            value={form.data.merged_id}
                            onChange={(e) =>
                                form.setData('merged_id', e.target.value)
                            }
                        >
                            <option value="">Choose…</option>
                            {employees
                                .filter((e) => e.id !== form.data.survivor_id)
                                .map((e) => (
                                    <option key={e.id} value={e.id}>
                                        {label(e)}
                                    </option>
                                ))}
                        </NativeSelect>
                        <InputError message={form.errors.merged_id} />
                    </div>
                    <ArrowRight className="mb-2 hidden text-muted-foreground sm:block" />
                    <div className="grid gap-2">
                        <Label htmlFor="survivor">
                            …into this profile (kept)
                        </Label>
                        <NativeSelect
                            id="survivor"
                            value={form.data.survivor_id}
                            onChange={(e) =>
                                form.setData('survivor_id', e.target.value)
                            }
                        >
                            <option value="">Choose…</option>
                            {employees
                                .filter((e) => e.id !== form.data.merged_id)
                                .map((e) => (
                                    <option key={e.id} value={e.id}>
                                        {label(e)}
                                    </option>
                                ))}
                        </NativeSelect>
                        <InputError message={form.errors.survivor_id} />
                    </div>
                </div>

                {survivor && merged && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Choose the surviving values</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {fields.map((field) => {
                                const fromMerged =
                                    form.data.take_from_merged.includes(field);

                                return (
                                    <fieldset
                                        key={field}
                                        className="grid gap-2 sm:grid-cols-[8rem_1fr_1fr] sm:items-center"
                                    >
                                        <legend className="sr-only">
                                            {field}
                                        </legend>
                                        <span className="text-sm font-medium capitalize">
                                            {field}
                                        </span>
                                        {[
                                            {
                                                from: false,
                                                value: survivor[field],
                                            },
                                            {
                                                from: true,
                                                value: merged[field],
                                            },
                                        ].map((opt) => (
                                            <label
                                                key={String(opt.from)}
                                                className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm ${fromMerged === opt.from ? 'border-primary bg-primary/5' : ''}`}
                                            >
                                                <input
                                                    type="radio"
                                                    name={field}
                                                    checked={
                                                        fromMerged === opt.from
                                                    }
                                                    onChange={() =>
                                                        choose(field, opt.from)
                                                    }
                                                />
                                                {opt.value || (
                                                    <span className="text-muted-foreground">
                                                        empty
                                                    </span>
                                                )}
                                            </label>
                                        ))}
                                    </fieldset>
                                );
                            })}
                            <p className="text-sm text-muted-foreground">
                                Moving to {survivor.name}:{' '}
                                {merged.templates_count} templates,{' '}
                                {merged.attendance_count} attendance records,{' '}
                                {merged.devices_count} device assignments.
                            </p>
                        </CardContent>
                    </Card>
                )}

                <div>
                    <Button
                        type="submit"
                        disabled={!survivor || !merged || form.processing}
                    >
                        Merge
                    </Button>
                </div>
            </form>
        </>
    );
}

MergeEmployees.layout = {
    breadcrumbs: [{ title: 'Employees', href: index() }],
};
