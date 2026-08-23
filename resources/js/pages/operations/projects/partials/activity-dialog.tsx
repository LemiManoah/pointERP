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
import type { Site } from './site-dialog';

export type ProjectActivity = {
    id: string;
    project_id: string;
    site_id: string | null;
    site_name: string | null;
    code: string | null;
    boq_item_number: string | null;
    name: string;
    unit: string | null;
    planned_quantity: string | null;
    approved_quantity: string;
    rate_amount: string | null;
    currency_code: string | null;
    status: 'active' | 'inactive';
    sort_order: number;
};

type ActivityFormData = Record<string, string> & {
    project_id: string;
    site_id: string;
    code: string;
    boq_item_number: string;
    name: string;
    unit: string;
    planned_quantity: string;
    approved_quantity: string;
    rate_amount: string;
    currency_code: string;
    status: ProjectActivity['status'];
    sort_order: string;
};

type Props = {
    projectId: string;
    activity?: ProjectActivity;
    sites: Site[];
    currencies: Option[];
    canViewRates: boolean;
};

export function ActivityDialog({
    projectId,
    activity,
    sites,
    currencies,
    canViewRates,
}: Props) {
    const [open, setOpen] = useState(false);
    const isEditing = Boolean(activity);
    const form = useForm<ActivityFormData>({
        project_id: activity?.project_id ?? projectId,
        site_id: activity?.site_id ?? '',
        code: activity?.code ?? '',
        boq_item_number: activity?.boq_item_number ?? '',
        name: activity?.name ?? '',
        unit: activity?.unit ?? '',
        planned_quantity: activity?.planned_quantity ?? '',
        approved_quantity: activity?.approved_quantity ?? '0',
        rate_amount: activity?.rate_amount ?? '',
        currency_code: activity?.currency_code ?? currencies[0]?.id ?? 'UGX',
        status: activity?.status ?? 'active',
        sort_order: String(activity?.sort_order ?? 0),
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (activity) {
            form.put(`/project-activities/${activity.id}`, {
                onSuccess: () => setOpen(false),
            });

            return;
        }

        form.post('/project-activities', {
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
                    {isEditing ? 'Edit' : 'New activity'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit activity' : 'New activity'}
                    </DialogTitle>
                    <DialogDescription>
                        Activities and BOQ items become the structure for DSR
                        work quantities.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label>Site</Label>
                            <SearchableSelect
                                value={form.data.site_id}
                                onValueChange={(value) =>
                                    form.setData('site_id', value)
                                }
                                options={[
                                    { value: '', label: 'Project-wide' },
                                    ...sites.map((site) => ({
                                        value: site.id,
                                        label: site.name,
                                    })),
                                ]}
                                placeholder="Select site"
                                searchPlaceholder="Search sites..."
                            />
                            <InputError message={form.errors.site_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="boq_item_number">BOQ item</Label>
                            <Input
                                id="boq_item_number"
                                value={form.data.boq_item_number}
                                onChange={(event) =>
                                    form.setData(
                                        'boq_item_number',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.boq_item_number} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="code">Code</Label>
                            <Input
                                id="code"
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData('code', event.target.value)
                                }
                            />
                            <InputError message={form.errors.code} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="name" required>
                            Name
                        </Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-4">
                        <div className="grid gap-2">
                            <Label htmlFor="unit">Unit</Label>
                            <Input
                                id="unit"
                                value={form.data.unit}
                                onChange={(event) =>
                                    form.setData('unit', event.target.value)
                                }
                            />
                            <InputError message={form.errors.unit} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="planned_quantity">
                                Planned qty
                            </Label>
                            <Input
                                id="planned_quantity"
                                value={form.data.planned_quantity}
                                onChange={(event) =>
                                    form.setData(
                                        'planned_quantity',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.planned_quantity}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="approved_quantity">
                                Approved qty
                            </Label>
                            <Input
                                id="approved_quantity"
                                value={form.data.approved_quantity}
                                onChange={(event) =>
                                    form.setData(
                                        'approved_quantity',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.approved_quantity}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label required>Status</Label>
                            <NativeSelect
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData(
                                        'status',
                                        event.target
                                            .value as ProjectActivity['status'],
                                    )
                                }
                            >
                                <NativeSelectOption value="active">
                                    Active
                                </NativeSelectOption>
                                <NativeSelectOption value="inactive">
                                    Inactive
                                </NativeSelectOption>
                            </NativeSelect>
                            <InputError message={form.errors.status} />
                        </div>
                    </div>

                    {canViewRates && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="rate_amount">Rate</Label>
                                <Input
                                    id="rate_amount"
                                    value={form.data.rate_amount}
                                    onChange={(event) =>
                                        form.setData(
                                            'rate_amount',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.rate_amount} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Currency</Label>
                                <SearchableSelect
                                    value={form.data.currency_code}
                                    onValueChange={(value) =>
                                        form.setData('currency_code', value)
                                    }
                                    options={currencies.map((currency) => ({
                                        value: currency.id,
                                        label: currency.name,
                                    }))}
                                    placeholder="Currency"
                                    searchPlaceholder="Search currencies..."
                                />
                                <InputError
                                    message={form.errors.currency_code}
                                />
                            </div>
                        </div>
                    )}

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
                            Save activity
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
