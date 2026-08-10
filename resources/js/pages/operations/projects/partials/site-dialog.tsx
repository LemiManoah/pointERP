import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import type { Option } from './project-dialog';

export type Site = {
    id: string;
    project_id: string;
    reference: string;
    name: string;
    location_name: string | null;
    manager_id: string | null;
    manager_name: string | null;
    reporting_deadline: string | null;
    status:
        | 'planned'
        | 'active'
        | 'suspended'
        | 'completed'
        | 'closed'
        | 'archived';
};

type SiteFormData = Record<string, string> & {
    project_id: string;
    reference: string;
    name: string;
    location_name: string;
    latitude: string;
    longitude: string;
    manager_id: string;
    reporting_deadline: string;
    status: Site['status'];
};

type Props = {
    projectId: string;
    site?: Site;
    users: Option[];
};

export function SiteDialog({ projectId, site, users }: Props) {
    const [open, setOpen] = useState(false);
    const isEditing = Boolean(site);
    const form = useForm<SiteFormData>({
        project_id: site?.project_id ?? projectId,
        reference: site?.reference ?? '',
        name: site?.name ?? '',
        location_name: site?.location_name ?? '',
        latitude: '',
        longitude: '',
        manager_id: site?.manager_id ?? '',
        reporting_deadline: site?.reporting_deadline?.slice(0, 5) ?? '',
        status: site?.status ?? 'planned',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (site) {
            form.put(`/sites/${site.id}`, {
                onSuccess: () => setOpen(false),
            });

            return;
        }

        form.post('/sites', {
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={isEditing ? 'outline' : 'default'}
                    size={isEditing ? 'sm' : 'default'}
                >
                    {isEditing ? <Pencil /> : <Plus />}
                    {isEditing ? 'Edit' : 'New site'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? `Edit ${site?.reference}` : 'New site'}
                    </DialogTitle>
                    <DialogDescription>
                        Sites are physical reporting locations under this
                        project.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="reference">Reference</Label>
                            <Input
                                id="reference"
                                value={form.data.reference}
                                onChange={(event) =>
                                    form.setData(
                                        'reference',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                            />
                            <InputError message={form.errors.reference} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                            <InputError message={form.errors.name} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="location_name">Location</Label>
                        <Input
                            id="location_name"
                            value={form.data.location_name}
                            onChange={(event) =>
                                form.setData(
                                    'location_name',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.location_name} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label>Manager</Label>
                            <SearchableSelect
                                value={form.data.manager_id}
                                onValueChange={(value) =>
                                    form.setData('manager_id', value)
                                }
                                options={[
                                    { value: '', label: 'No manager' },
                                    ...users.map((user) => ({
                                        value: user.id,
                                        label: user.name,
                                    })),
                                ]}
                                placeholder="Select manager"
                                searchPlaceholder="Search users..."
                            />
                            <InputError message={form.errors.manager_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="reporting_deadline">
                                DSR deadline
                            </Label>
                            <Input
                                id="reporting_deadline"
                                type="time"
                                value={form.data.reporting_deadline}
                                onChange={(event) =>
                                    form.setData(
                                        'reporting_deadline',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.reporting_deadline}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Status</Label>
                            <NativeSelect
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData(
                                        'status',
                                        event.target.value as Site['status'],
                                    )
                                }
                            >
                                {[
                                    'planned',
                                    'active',
                                    'suspended',
                                    'completed',
                                    'closed',
                                    'archived',
                                ].map((status) => (
                                    <NativeSelectOption
                                        key={status}
                                        value={status}
                                    >
                                        {status}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <InputError message={form.errors.status} />
                        </div>
                    </div>
                    <div className="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save site
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
