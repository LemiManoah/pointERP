import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatCurrencyAmount } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Option = { value: string; label: string };
type ItemOption = Option & { unit_id: string; unit_cost: string | null };
type Resource = {
    resource_type: string;
    inventory_item_id: string;
    unit_of_measure_id: string;
    name: string;
    quantity_per_work_unit: string;
    estimated_unit_cost: string;
    notes: string;
};
type EstimateLine = {
    work_item_key: string | null;
    site_id: string;
    unit_of_measure_id: string;
    boq_reference: string;
    code: string;
    name: string;
    planned_quantity: string;
    selling_rate: string;
    estimated_unit_cost: string;
    notes: string;
    resources: Resource[];
};
type Estimate = {
    id: string;
    version_number: number;
    title: string;
    currency_code: string;
    notes: string | null;
    status: string | null;
    status_label: string | null;
    is_baseline: boolean;
    approved_by: string | null;
    approved_at: string | null;
    lines: Array<
        Omit<EstimateLine, 'site_id' | 'boq_reference' | 'code' | 'notes'> & {
            site_id: string | null;
            boq_reference: string | null;
            code: string | null;
            notes: string | null;
            resources: Array<
                Omit<
                    Resource,
                    | 'inventory_item_id'
                    | 'unit_of_measure_id'
                    | 'estimated_unit_cost'
                    | 'notes'
                > & {
                    inventory_item_id: string | null;
                    unit_of_measure_id: string | null;
                    estimated_unit_cost: string | null;
                    notes: string | null;
                }
            >;
        }
    >;
};
type Props = {
    project: {
        id: string;
        reference: string;
        name: string;
        base_currency_code: string;
    };
    estimate: Estimate | null;
    source: Estimate | null;
    sites: Option[];
    units: Option[];
    items: ItemOption[];
    resourceTypes: Option[];
    can: { update: boolean; approve: boolean; viewCosts: boolean };
};

function blankResource(): Resource {
    return {
        resource_type: 'material',
        inventory_item_id: '',
        unit_of_measure_id: '',
        name: '',
        quantity_per_work_unit: '',
        estimated_unit_cost: '',
        notes: '',
    };
}

function blankLine(): EstimateLine {
    return {
        work_item_key: null,
        site_id: '',
        unit_of_measure_id: '',
        boq_reference: '',
        code: '',
        name: '',
        planned_quantity: '',
        selling_rate: '',
        estimated_unit_cost: '',
        notes: '',
        resources: [],
    };
}

function linesFrom(record: Estimate | null): EstimateLine[] {
    if (!record || record.lines.length === 0) return [blankLine()];

    return record.lines.map((line) => ({
        ...line,
        site_id: line.site_id ?? '',
        boq_reference: line.boq_reference ?? '',
        code: line.code ?? '',
        notes: line.notes ?? '',
        selling_rate: line.selling_rate ?? '',
        estimated_unit_cost: line.estimated_unit_cost ?? '',
        resources: line.resources.map((resource) => ({
            ...resource,
            inventory_item_id: resource.inventory_item_id ?? '',
            unit_of_measure_id: resource.unit_of_measure_id ?? '',
            estimated_unit_cost: resource.estimated_unit_cost ?? '',
            notes: resource.notes ?? '',
        })),
    }));
}

export default function EstimateEditor({
    project,
    estimate,
    source,
    sites,
    units,
    items,
    resourceTypes,
    can,
}: Props) {
    const confirm = useConfirmDialog();
    const seed = estimate ?? source;
    const editable = estimate === null || can.update;
    const form = useForm({
        title: estimate?.title ?? source?.title ?? `${project.name} estimate`,
        currency_code:
            estimate?.currency_code ??
            source?.currency_code ??
            project.base_currency_code,
        notes: estimate?.notes ?? source?.notes ?? '',
        lines: linesFrom(seed),
    });
    const errors = form.errors as Record<string, string | undefined>;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: '/projects' },
        { title: project.reference, href: `/projects/${project.id}` },
        {
            title: estimate
                ? `Estimate v${estimate.version_number}`
                : 'New estimate',
            href: estimate
                ? `/estimates/${estimate.id}`
                : `/projects/${project.id}/estimates/create`,
        },
    ];

    function updateLine(index: number, values: Partial<EstimateLine>) {
        form.setData(
            'lines',
            form.data.lines.map((line, lineIndex) =>
                lineIndex === index ? { ...line, ...values } : line,
            ),
        );
    }

    function updateResource(
        lineIndex: number,
        resourceIndex: number,
        values: Partial<Resource>,
    ) {
        const resources = form.data.lines[lineIndex].resources.map(
            (resource, index) =>
                index === resourceIndex ? { ...resource, ...values } : resource,
        );
        updateLine(lineIndex, { resources });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!editable || form.processing) return;

        if (estimate) {
            form.put(`/estimates/${estimate.id}`);
        } else {
            form.post(`/projects/${project.id}/estimates`);
        }
    }

    const baselineRevenue = form.data.lines.reduce(
        (sum, line) =>
            sum +
            Number(line.planned_quantity || 0) * Number(line.selling_rate || 0),
        0,
    );
    const baselineCost = form.data.lines.reduce(
        (sum, line) =>
            sum +
            Number(line.planned_quantity || 0) *
                Number(line.estimated_unit_cost || 0),
        0,
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={estimate ? estimate.title : 'New estimate'} />
            <form
                onSubmit={submit}
                className="flex flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold">
                                {estimate
                                    ? `Estimate version ${estimate.version_number}`
                                    : source
                                      ? 'New estimate revision'
                                      : 'New project estimate'}
                            </h1>
                            {estimate?.status_label && (
                                <Badge
                                    variant={
                                        estimate.is_baseline
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {estimate.status_label}
                                </Badge>
                            )}
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {project.reference} · {project.name}
                        </p>
                    </div>
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button asChild type="button" variant="outline">
                            <Link href={`/projects/${project.id}`}>
                                <ArrowLeft />
                                Project
                            </Link>
                        </Button>
                        {estimate?.status === 'draft' && editable && (
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() =>
                                    confirm({
                                        title: 'Delete this draft?',
                                        description:
                                            'The draft estimate and its work assumptions will be permanently removed.',
                                        confirmLabel: 'Delete draft',
                                        variant: 'destructive',
                                        onConfirm: () =>
                                            router.delete(
                                                `/estimates/${estimate.id}`,
                                            ),
                                    })
                                }
                            >
                                <Trash2 />
                                Delete
                            </Button>
                        )}
                        {can.approve && estimate?.status === 'draft' && (
                            <Button
                                type="button"
                                onClick={() =>
                                    confirm({
                                        title: 'Approve this baseline?',
                                        description:
                                            'This version will become the project baseline and its lines will become the work items used by daily reports.',
                                        confirmLabel: 'Approve baseline',
                                        onConfirm: () =>
                                            router.post(
                                                `/estimates/${estimate.id}/approve`,
                                            ),
                                    })
                                }
                            >
                                <Check />
                                Approve baseline
                            </Button>
                        )}
                    </div>
                </div>

                <Card>
                    <CardContent className="grid gap-6 pt-6">
                        <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_12rem]">
                            <div className="grid gap-2">
                                <Label htmlFor="title" required>
                                    Estimate title
                                </Label>
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    disabled={!editable}
                                    onChange={(event) =>
                                        form.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.title} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Currency</Label>
                                <Input
                                    value={form.data.currency_code}
                                    disabled
                                />
                            </div>
                        </div>

                        {can.viewCosts && (
                            <div className="grid gap-4 sm:grid-cols-3">
                                <Metric
                                    label="Estimated revenue"
                                    value={formatCurrencyAmount(
                                        form.data.currency_code,
                                        baselineRevenue,
                                    )}
                                />
                                <Metric
                                    label="Estimated cost"
                                    value={formatCurrencyAmount(
                                        form.data.currency_code,
                                        baselineCost,
                                    )}
                                />
                                <Metric
                                    label="Estimated margin"
                                    value={formatCurrencyAmount(
                                        form.data.currency_code,
                                        baselineRevenue - baselineCost,
                                    )}
                                />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Textarea
                                id="notes"
                                value={form.data.notes}
                                disabled={!editable}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                            <InputError message={form.errors.notes} />
                        </div>

                        <div className="flex items-center justify-between gap-3 border-t pt-5">
                            <h2 className="font-semibold">Work items</h2>
                            {editable && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        form.setData('lines', [
                                            ...form.data.lines,
                                            blankLine(),
                                        ])
                                    }
                                >
                                    <Plus />
                                    Add work item
                                </Button>
                            )}
                        </div>

                        <div className="grid gap-6">
                            {form.data.lines.map((line, lineIndex) => (
                                <section
                                    key={`${line.work_item_key ?? 'new'}-${lineIndex}`}
                                    className="grid gap-4 border-b pb-6 last:border-0 last:pb-0"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <h3 className="font-medium">
                                            Work item {lineIndex + 1}
                                        </h3>
                                        {editable &&
                                            form.data.lines.length > 1 && (
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    title="Remove work item"
                                                    onClick={() =>
                                                        form.setData(
                                                            'lines',
                                                            form.data.lines.filter(
                                                                (_, index) =>
                                                                    index !==
                                                                    lineIndex,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            )}
                                    </div>

                                    <div className="grid gap-4 lg:grid-cols-2">
                                        <Field label="Work item name" required>
                                            <Input
                                                value={line.name}
                                                disabled={!editable}
                                                onChange={(event) =>
                                                    updateLine(lineIndex, {
                                                        name: event.target
                                                            .value,
                                                    })
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `lines.${lineIndex}.name`
                                                    ]
                                                }
                                            />
                                        </Field>
                                        <Field label="Site">
                                            <SearchableSelect
                                                value={line.site_id}
                                                disabled={!editable}
                                                onValueChange={(value) =>
                                                    updateLine(lineIndex, {
                                                        site_id: value,
                                                    })
                                                }
                                                options={[
                                                    {
                                                        value: '',
                                                        label: 'Project-wide',
                                                    },
                                                    ...sites,
                                                ]}
                                                placeholder="Project-wide"
                                                searchPlaceholder="Search sites..."
                                            />
                                        </Field>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <Field label="Unit" required>
                                            <SearchableSelect
                                                value={line.unit_of_measure_id}
                                                disabled={!editable}
                                                onValueChange={(value) =>
                                                    updateLine(lineIndex, {
                                                        unit_of_measure_id:
                                                            value,
                                                    })
                                                }
                                                options={units}
                                                placeholder="Select unit"
                                                searchPlaceholder="Search units..."
                                            />
                                        </Field>
                                        <Field
                                            label="Estimated quantity"
                                            required
                                        >
                                            <Input
                                                type="number"
                                                min="0"
                                                step="any"
                                                value={line.planned_quantity}
                                                disabled={!editable}
                                                onChange={(event) =>
                                                    updateLine(lineIndex, {
                                                        planned_quantity:
                                                            event.target.value,
                                                    })
                                                }
                                            />
                                        </Field>
                                        {can.viewCosts && (
                                            <Field label="Selling rate">
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="any"
                                                    value={line.selling_rate}
                                                    disabled={!editable}
                                                    onChange={(event) =>
                                                        updateLine(lineIndex, {
                                                            selling_rate:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                />
                                            </Field>
                                        )}
                                        {can.viewCosts && (
                                            <Field label="Estimated unit cost">
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="any"
                                                    value={
                                                        line.estimated_unit_cost
                                                    }
                                                    disabled={!editable}
                                                    onChange={(event) =>
                                                        updateLine(lineIndex, {
                                                            estimated_unit_cost:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                />
                                            </Field>
                                        )}
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="BOQ reference">
                                            <Input
                                                value={line.boq_reference}
                                                disabled={!editable}
                                                placeholder="e.g. 31.01(b)(i)"
                                                onChange={(event) =>
                                                    updateLine(lineIndex, {
                                                        boq_reference:
                                                            event.target.value,
                                                    })
                                                }
                                            />
                                        </Field>
                                        <Field label="Internal code">
                                            <Input
                                                value={line.code}
                                                disabled={!editable}
                                                onChange={(event) =>
                                                    updateLine(lineIndex, {
                                                        code: event.target
                                                            .value,
                                                    })
                                                }
                                            />
                                        </Field>
                                    </div>

                                    <details className="group rounded-md border px-4 py-3">
                                        <summary className="cursor-pointer font-medium">
                                            Resource assumptions (
                                            {line.resources.length})
                                        </summary>
                                        <div className="mt-4 grid gap-4">
                                            {line.resources.map(
                                                (resource, resourceIndex) => (
                                                    <div
                                                        key={resourceIndex}
                                                        className={`grid gap-3 border-b pb-4 last:border-0 last:pb-0 ${can.viewCosts ? 'lg:grid-cols-[10rem_minmax(12rem,1fr)_minmax(10rem,1fr)_9rem_9rem_auto]' : 'lg:grid-cols-[10rem_minmax(12rem,1fr)_minmax(10rem,1fr)_9rem_auto]'}`}
                                                    >
                                                        <NativeSelect
                                                            value={
                                                                resource.resource_type
                                                            }
                                                            disabled={!editable}
                                                            onChange={(event) =>
                                                                updateResource(
                                                                    lineIndex,
                                                                    resourceIndex,
                                                                    {
                                                                        resource_type:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            {resourceTypes.map(
                                                                (type) => (
                                                                    <NativeSelectOption
                                                                        key={
                                                                            type.value
                                                                        }
                                                                        value={
                                                                            type.value
                                                                        }
                                                                    >
                                                                        {
                                                                            type.label
                                                                        }
                                                                    </NativeSelectOption>
                                                                ),
                                                            )}
                                                        </NativeSelect>
                                                        {resource.resource_type ===
                                                        'material' ? (
                                                            <SearchableSelect
                                                                value={
                                                                    resource.inventory_item_id
                                                                }
                                                                disabled={
                                                                    !editable
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) => {
                                                                    const item =
                                                                        items.find(
                                                                            (
                                                                                option,
                                                                            ) =>
                                                                                option.value ===
                                                                                value,
                                                                        );
                                                                    updateResource(
                                                                        lineIndex,
                                                                        resourceIndex,
                                                                        {
                                                                            inventory_item_id:
                                                                                value,
                                                                            name:
                                                                                item?.label ??
                                                                                resource.name,
                                                                            unit_of_measure_id:
                                                                                item?.unit_id ??
                                                                                resource.unit_of_measure_id,
                                                                            estimated_unit_cost:
                                                                                item?.unit_cost ??
                                                                                resource.estimated_unit_cost,
                                                                        },
                                                                    );
                                                                }}
                                                                options={items}
                                                                placeholder="Select material"
                                                                searchPlaceholder="Search materials..."
                                                            />
                                                        ) : (
                                                            <Input
                                                                value={
                                                                    resource.name
                                                                }
                                                                disabled={
                                                                    !editable
                                                                }
                                                                placeholder="Resource name"
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateResource(
                                                                        lineIndex,
                                                                        resourceIndex,
                                                                        {
                                                                            name: event
                                                                                .target
                                                                                .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        )}
                                                        <SearchableSelect
                                                            value={
                                                                resource.unit_of_measure_id
                                                            }
                                                            disabled={!editable}
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                updateResource(
                                                                    lineIndex,
                                                                    resourceIndex,
                                                                    {
                                                                        unit_of_measure_id:
                                                                            value,
                                                                    },
                                                                )
                                                            }
                                                            options={units}
                                                            placeholder="Unit"
                                                            searchPlaceholder="Search units..."
                                                        />
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            step="any"
                                                            value={
                                                                resource.quantity_per_work_unit
                                                            }
                                                            disabled={!editable}
                                                            placeholder="Qty per unit"
                                                            onChange={(event) =>
                                                                updateResource(
                                                                    lineIndex,
                                                                    resourceIndex,
                                                                    {
                                                                        quantity_per_work_unit:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                        {can.viewCosts && (
                                                            <Input
                                                                type="number"
                                                                min="0"
                                                                step="any"
                                                                value={
                                                                    resource.estimated_unit_cost
                                                                }
                                                                disabled={
                                                                    !editable
                                                                }
                                                                placeholder="Unit cost"
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateResource(
                                                                        lineIndex,
                                                                        resourceIndex,
                                                                        {
                                                                            estimated_unit_cost:
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        )}
                                                        {editable && (
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="ghost"
                                                                title="Remove resource"
                                                                onClick={() =>
                                                                    updateLine(
                                                                        lineIndex,
                                                                        {
                                                                            resources:
                                                                                line.resources.filter(
                                                                                    (
                                                                                        _,
                                                                                        index,
                                                                                    ) =>
                                                                                        index !==
                                                                                        resourceIndex,
                                                                                ),
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 />
                                                            </Button>
                                                        )}
                                                    </div>
                                                ),
                                            )}
                                            {editable && (
                                                <div>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            updateLine(
                                                                lineIndex,
                                                                {
                                                                    resources: [
                                                                        ...line.resources,
                                                                        blankResource(),
                                                                    ],
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <Plus />
                                                        Add resource
                                                    </Button>
                                                </div>
                                            )}
                                        </div>
                                    </details>
                                </section>
                            ))}
                        </div>

                        {editable && (
                            <div className="flex justify-end border-t pt-5">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing && <Spinner />}
                                    Save estimate
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </form>
        </AppLayout>
    );
}

function Field({
    label,
    required = false,
    children,
}: {
    label: string;
    required?: boolean;
    children: React.ReactNode;
}) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label required={required}>{label}</Label>
            {children}
        </div>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border px-4 py-3">
            <div className="text-sm text-muted-foreground">{label}</div>
            <div className="mt-1 font-semibold">{value}</div>
        </div>
    );
}
