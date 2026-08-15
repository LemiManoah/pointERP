import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
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
import type {
    BranchOption,
    EquipmentLocation,
    ProjectOption,
    SiteOption,
} from '../types';

type FormData = Record<string, string | boolean> & {
    branch_id: string;
    project_id: string;
    site_id: string;
    type: string;
    code: string;
    name: string;
    address: string;
    latitude: string;
    longitude: string;
    is_active: boolean;
};

type Props = {
    location?: EquipmentLocation;
    branches: BranchOption[];
    projects: ProjectOption[];
    sites: SiteOption[];
};

export function EquipmentLocationDialog({
    location,
    branches,
    projects,
    sites,
}: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm<FormData>({
        branch_id:
            location?.branch_id ??
            (branches.length === 1 ? (branches[0]?.id ?? '') : ''),
        project_id: location?.project_id ?? '',
        site_id: location?.site_id ?? '',
        type: location?.type ?? 'depot',
        code: location?.code ?? '',
        name: location?.name ?? '',
        address: location?.address ?? '',
        latitude: location?.latitude ?? '',
        longitude: location?.longitude ?? '',
        is_active: location?.is_active ?? true,
    });
    const projectOptions = useMemo(
        () =>
            projects.filter(
                (project) => project.branch_id === form.data.branch_id,
            ),
        [form.data.branch_id, projects],
    );
    const siteOptions = useMemo(
        () =>
            sites.filter(
                (site) =>
                    site.branch_id === form.data.branch_id &&
                    (!form.data.project_id ||
                        site.project_id === form.data.project_id),
            ),
        [form.data.branch_id, form.data.project_id, sites],
    );

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = {
            onSuccess: () => setOpen(false),
            preserveScroll: true,
        };
        if (location) form.put(`/equipment-locations/${location.id}`, options);
        else form.post('/equipment-locations', options);
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={location ? 'outline' : 'default'}
                    size={location ? 'sm' : 'default'}
                >
                    {location ? <Pencil /> : <Plus />}
                    {location ? 'Edit' : 'New location'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {location
                            ? `Edit ${location.name}`
                            : 'New equipment location'}
                    </DialogTitle>
                    <DialogDescription>
                        Define a depot, yard, workshop or site location within
                        an authorised branch.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            label="Branch"
                            error={form.errors.branch_id}
                            value={form.data.branch_id}
                            onChange={(value) => {
                                form.setData('branch_id', value);
                                form.setData('project_id', '');
                                form.setData('site_id', '');
                            }}
                            options={branches}
                        />
                        <Field label="Type" error={form.errors.type}>
                            <NativeSelect
                                value={form.data.type}
                                onChange={(e) =>
                                    form.setData('type', e.target.value)
                                }
                            >
                                {[
                                    'depot',
                                    'yard',
                                    'workshop',
                                    'site',
                                    'other',
                                ].map((type) => (
                                    <NativeSelectOption key={type} value={type}>
                                        {label(type)}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                        </Field>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Code" error={form.errors.code}>
                            <Input
                                value={form.data.code}
                                onChange={(e) =>
                                    form.setData(
                                        'code',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                            />
                        </Field>
                        <Field label="Name" error={form.errors.name}>
                            <Input
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                        </Field>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            label="Project (optional)"
                            error={form.errors.project_id}
                            value={form.data.project_id}
                            onChange={(value) => {
                                form.setData('project_id', value);
                                form.setData('site_id', '');
                            }}
                            options={projectOptions}
                            optional
                        />
                        <SelectField
                            label="Site (optional)"
                            error={form.errors.site_id}
                            value={form.data.site_id}
                            onChange={(value) => {
                                form.setData('site_id', value);
                                if (value) form.setData('type', 'site');
                            }}
                            options={siteOptions}
                            optional
                        />
                    </div>
                    <Field label="Address" error={form.errors.address}>
                        <Input
                            value={form.data.address}
                            onChange={(e) =>
                                form.setData('address', e.target.value)
                            }
                        />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Latitude" error={form.errors.latitude}>
                            <Input
                                type="number"
                                step="0.0000001"
                                value={form.data.latitude}
                                onChange={(e) =>
                                    form.setData('latitude', e.target.value)
                                }
                            />
                        </Field>
                        <Field label="Longitude" error={form.errors.longitude}>
                            <Input
                                type="number"
                                step="0.0000001"
                                value={form.data.longitude}
                                onChange={(e) =>
                                    form.setData('longitude', e.target.value)
                                }
                            />
                        </Field>
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
                            {form.processing && <Spinner />}Save location
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function SelectField({
    label: title,
    error,
    value,
    onChange,
    options,
    optional = false,
}: {
    label: string;
    error?: string;
    value: string;
    onChange: (value: string) => void;
    options: Array<{ id: string; name: string }>;
    optional?: boolean;
}) {
    return (
        <Field label={title} error={error}>
            <SearchableSelect
                value={value}
                onValueChange={onChange}
                options={[
                    ...(optional ? [{ value: '', label: 'None' }] : []),
                    ...options.map((option) => ({
                        value: option.id,
                        label: option.name,
                    })),
                ]}
                placeholder="Select option"
            />
        </Field>
    );
}
function Field({
    label: title,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{title}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
function label(value: string) {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
