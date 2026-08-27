import { useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
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
import { Textarea } from '@/components/ui/textarea';
import type {
    RequisitionFormOptions,
    RequisitionLineForm,
} from '../types';

type ExistingRequisition = {
    id: string;
    branch_id: string;
    inventory_store_id: string;
    project_id: string | null;
    site_id: string | null;
    department: string | null;
    required_by_date: string;
    priority: string;
    reason: string;
    lines: RequisitionLineForm[];
};

const emptyLine = (): RequisitionLineForm => ({
    inventory_item_id: '',
    description: '',
    unit_of_measure_id: '',
    requested_quantity: '',
    project_activity_id: '',
    purpose: '',
    notes: '',
});

export function RequisitionDialog({
    options,
    requisition,
}: {
    options: RequisitionFormOptions;
    requisition?: ExistingRequisition;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        branch_id: requisition?.branch_id ?? options.defaultBranchId,
        inventory_store_id: requisition?.inventory_store_id ?? '',
        project_id: requisition?.project_id ?? '',
        site_id: requisition?.site_id ?? '',
        department: requisition?.department ?? '',
        required_by_date:
            requisition?.required_by_date ??
            new Date(Date.now() + 7 * 86_400_000).toISOString().slice(0, 10),
        priority: requisition?.priority ?? 'normal',
        reason: requisition?.reason ?? '',
        lines: requisition?.lines ?? [emptyLine()],
    });

    const stores = options.stores.filter(
        (store) => store.branch_id === form.data.branch_id,
    );
    const projects = options.projects.filter(
        (project) => project.branch_id === form.data.branch_id,
    );
    const sites = options.sites.filter(
        (site) =>
            site.branch_id === form.data.branch_id &&
            (!form.data.project_id || site.project_id === form.data.project_id),
    );
    const updateLine = (index: number, values: Partial<RequisitionLineForm>) =>
        form.setData(
            'lines',
            form.data.lines.map((line, current) =>
                current === index ? { ...line, ...values } : line,
            ),
        );
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const requestOptions = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };
        if (requisition) {
            form.put(`/inventory/requisitions/${requisition.id}`, requestOptions);
        } else {
            form.post('/inventory/requisitions', requestOptions);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant={requisition ? 'outline' : 'default'}>
                    {requisition ? <Pencil /> : <Plus />}
                    {requisition ? 'Edit draft' : 'New requisition'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-6xl">
                <DialogHeader>
                    <DialogTitle>
                        {requisition
                            ? 'Edit material requisition'
                            : 'New material requisition'}
                    </DialogTitle>
                    <DialogDescription>
                        Request materials from an operational store for a
                        project, site or department.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-6">
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {options.canChangeBranch && (
                            <Field label="Branch" required error={form.errors.branch_id}>
                                <SearchableSelect
                                    value={form.data.branch_id}
                                    options={options.branches.map((branch) => ({
                                        value: branch.id,
                                        label: branch.name,
                                        description: branch.code,
                                    }))}
                                    onValueChange={(value) => {
                                        form.setData('branch_id', value);
                                        form.setData('inventory_store_id', '');
                                        form.setData('project_id', '');
                                        form.setData('site_id', '');
                                    }}
                                />
                            </Field>
                        )}
                        <Field label="Source store" required error={form.errors.inventory_store_id}>
                            <SearchableSelect
                                value={form.data.inventory_store_id}
                                options={stores.map((store) => ({
                                    value: store.id,
                                    label: store.name,
                                    description: store.code,
                                }))}
                                onValueChange={(value) =>
                                    form.setData('inventory_store_id', value)
                                }
                                placeholder="Select source store"
                            />
                        </Field>
                        <Field label="Project">
                            <SearchableSelect
                                value={form.data.project_id}
                                options={[
                                    { value: '', label: 'No project' },
                                    ...projects.map((project) => ({
                                        value: project.id,
                                        label: project.name,
                                        description: project.reference,
                                    })),
                                ]}
                                onValueChange={(value) => {
                                    form.setData('project_id', value);
                                    form.setData('site_id', '');
                                }}
                            />
                        </Field>
                        <Field label="Site">
                            <SearchableSelect
                                value={form.data.site_id}
                                options={[
                                    { value: '', label: 'No site' },
                                    ...sites.map((site) => ({
                                        value: site.id,
                                        label: site.name,
                                        description: site.reference,
                                    })),
                                ]}
                                onValueChange={(value) => form.setData('site_id', value)}
                                disabled={!form.data.project_id}
                            />
                        </Field>
                        <Field label="Department">
                            <Input
                                value={form.data.department}
                                onChange={(event) =>
                                    form.setData('department', event.target.value)
                                }
                                placeholder="e.g. Civil works"
                            />
                        </Field>
                        <Field label="Required by" required error={form.errors.required_by_date}>
                            <Input
                                type="date"
                                value={form.data.required_by_date}
                                onChange={(event) =>
                                    form.setData('required_by_date', event.target.value)
                                }
                            />
                        </Field>
                        <Field label="Priority" required error={form.errors.priority}>
                            <NativeSelect
                                value={form.data.priority}
                                onChange={(event) =>
                                    form.setData('priority', event.target.value)
                                }
                            >
                                <NativeSelectOption value="low">Low</NativeSelectOption>
                                <NativeSelectOption value="normal">Normal</NativeSelectOption>
                                <NativeSelectOption value="high">High</NativeSelectOption>
                                <NativeSelectOption value="urgent">Urgent</NativeSelectOption>
                            </NativeSelect>
                        </Field>
                    </div>
                    <Field label="Reason for request" required error={form.errors.reason}>
                        <Textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    </Field>
                    <section className="grid gap-4 border-t pt-5">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <h3 className="font-medium">Requested materials</h3>
                                <p className="text-sm text-muted-foreground">
                                    Select a registered item when available. An
                                    unregistered description can continue to procurement.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    form.setData('lines', [
                                        ...form.data.lines,
                                        emptyLine(),
                                    ])
                                }
                            >
                                <Plus /> Add line
                            </Button>
                        </div>
                        {form.data.lines.map((line, index) => {
                            const item = options.items.find(
                                (row) => row.id === line.inventory_item_id,
                            );
                            const activities = options.activities.filter(
                                (activity) =>
                                    !form.data.project_id ||
                                    activity.project_id === form.data.project_id,
                            );
                            return (
                                <div
                                    key={index}
                                    className="grid min-w-0 gap-3 border-b pb-4 last:border-0 lg:grid-cols-12"
                                >
                                    <div className="min-w-0 lg:col-span-3">
                                        <Field label="Inventory item">
                                            <SearchableSelect
                                                value={line.inventory_item_id}
                                                options={[
                                                    { value: '', label: 'Unregistered item' },
                                                    ...options.items.map((row) => ({
                                                        value: row.id,
                                                        label: row.name,
                                                        description: row.code,
                                                    })),
                                                ]}
                                                onValueChange={(value) => {
                                                    const selected = options.items.find(
                                                        (row) => row.id === value,
                                                    );
                                                    updateLine(index, {
                                                        inventory_item_id: value,
                                                        description: selected?.name ?? line.description,
                                                        unit_of_measure_id:
                                                            selected?.stock_unit_id ??
                                                            line.unit_of_measure_id,
                                                    });
                                                }}
                                            />
                                        </Field>
                                    </div>
                                    <div className="lg:col-span-2">
                                        <Field label="Description" required={!item}>
                                            <Input
                                                value={line.description}
                                                disabled={Boolean(item)}
                                                onChange={(event) =>
                                                    updateLine(index, {
                                                        description: event.target.value,
                                                    })
                                                }
                                            />
                                        </Field>
                                    </div>
                                    <div className="lg:col-span-2">
                                        <Field label="Quantity" required>
                                            <Input
                                                type="number"
                                                min="0.0001"
                                                step="0.0001"
                                                value={line.requested_quantity}
                                                onChange={(event) =>
                                                    updateLine(index, {
                                                        requested_quantity: event.target.value,
                                                    })
                                                }
                                            />
                                        </Field>
                                    </div>
                                    <div className="min-w-0 lg:col-span-2">
                                        <Field label="Unit" required>
                                            <SearchableSelect
                                                value={line.unit_of_measure_id}
                                                options={options.units.map((unit) => ({
                                                    value: unit.id,
                                                    label: unit.name,
                                                    description: unit.symbol ?? unit.code,
                                                }))}
                                                onValueChange={(value) =>
                                                    updateLine(index, {
                                                        unit_of_measure_id: value,
                                                    })
                                                }
                                            />
                                        </Field>
                                    </div>
                                    <div className="min-w-0 lg:col-span-2">
                                        <Field label="Activity">
                                            <SearchableSelect
                                                value={line.project_activity_id}
                                                options={[
                                                    { value: '', label: 'No activity' },
                                                    ...activities.map((activity) => ({
                                                        value: activity.id,
                                                        label: activity.name,
                                                        description: activity.code,
                                                    })),
                                                ]}
                                                onValueChange={(value) =>
                                                    updateLine(index, {
                                                        project_activity_id: value,
                                                    })
                                                }
                                            />
                                        </Field>
                                    </div>
                                    <div className="flex items-end justify-end lg:col-span-1">
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            title="Remove line"
                                            disabled={form.data.lines.length === 1}
                                            onClick={() =>
                                                form.setData(
                                                    'lines',
                                                    form.data.lines.filter(
                                                        (_, current) => current !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}
                        <InputError message={form.errors.lines} />
                    </section>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Save draft
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Field({
    label,
    required,
    error,
    children,
}: {
    label: string;
    required?: boolean;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label required={required}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
