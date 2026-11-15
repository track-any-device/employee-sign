import { Head, router, useForm } from '@inertiajs/react';
import { MapPin, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { destroy, index, store } from '@/routes/sites';
import {
    destroy as destroyLocation,
    store as storeLocation,
} from '@/routes/sites/locations';
import type { SiteOption } from '@/types';

type Site = SiteOption & { address: string | null };

export default function SitesIndex({ sites }: { sites: Site[] }) {
    const form = useForm({ name: '', address: '' });

    return (
        <>
            <Head title="Sites" />
            <div className="flex max-w-4xl flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Sites & locations"
                    description="Optional. When set, use them to group devices and filter attendance."
                />

                <form
                    className="flex flex-wrap items-start gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(store.url(), {
                            preserveScroll: true,
                            onSuccess: () => form.reset(),
                        });
                    }}
                >
                    <div className="grid gap-1">
                        <Input
                            placeholder="Site name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <Input
                        className="w-72"
                        placeholder="Address (optional)"
                        value={form.data.address}
                        onChange={(e) =>
                            form.setData('address', e.target.value)
                        }
                    />
                    <Button type="submit" disabled={form.processing}>
                        Add site
                    </Button>
                </form>

                {sites.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No sites yet.
                    </p>
                )}
                <div className="grid gap-4 md:grid-cols-2">
                    {sites.map((site) => (
                        <SiteCard key={site.id} site={site} />
                    ))}
                </div>
            </div>
        </>
    );
}

function SiteCard({ site }: { site: Site }) {
    const [name, setName] = useState('');

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-2">
                <div>
                    <CardTitle>{site.name}</CardTitle>
                    {site.address && (
                        <CardDescription>{site.address}</CardDescription>
                    )}
                </div>
                <Button
                    size="icon"
                    variant="ghost"
                    aria-label="Delete site"
                    onClick={() =>
                        window.confirm(
                            `Delete ${site.name}? Devices keep working without a site.`,
                        ) &&
                        router.delete(destroy.url(site.id), {
                            preserveScroll: true,
                        })
                    }
                >
                    <Trash2 />
                </Button>
            </CardHeader>
            <CardContent className="grid gap-3">
                <ul className="grid gap-1">
                    {site.locations.map((l) => (
                        <li
                            key={l.id}
                            className="flex items-center gap-2 text-sm"
                        >
                            <MapPin className="size-4 text-muted-foreground" />
                            <span className="flex-1">{l.name}</span>
                            <Button
                                size="icon"
                                variant="ghost"
                                className="size-7"
                                aria-label={`Remove ${l.name}`}
                                onClick={() =>
                                    router.delete(
                                        destroyLocation.url({
                                            site: site.id,
                                            location: l.id,
                                        }),
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <X />
                            </Button>
                        </li>
                    ))}
                </ul>
                <form
                    className="flex gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.post(
                            storeLocation.url(site.id),
                            { name },
                            {
                                preserveScroll: true,
                                onSuccess: () => setName(''),
                            },
                        );
                    }}
                >
                    <Input
                        placeholder="New location, e.g. Main gate"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        required
                    />
                    <Button type="submit" variant="outline">
                        Add
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

SitesIndex.layout = {
    breadcrumbs: [{ title: 'Sites', href: index() }],
};
