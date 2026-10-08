import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { show as showBoq } from '@/actions/App/Http/Controllers/Operations/ProjectBoqController';
import { show as projectShow } from '@/actions/App/Http/Controllers/Operations/ProjectController';
import { show as showEstimate } from '@/actions/App/Http/Controllers/Operations/ProjectEstimateController';
import {
    index,
    upload,
    template,
    source,
    preview as previewImport,
    store,
} from '@/actions/App/Http/Controllers/Operations/ProjectEstimateImportController';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';

type Option = { value: string; label: string; dimension?: string };
type Project = {
    id: string;
    name: string;
    reference: string;
    base_currency_code: string;
};
type Line = {
    percentage_rate: string | null;
    percentage_base_keys: string[];
    daywork_resource_type: string | null;
    daywork_inventory_item_id: string | null;
    daywork_equipment_category_id: string | null;
    daywork_workforce_trade_id: string | null;
    work_item_key: string;
    bill: string | null;
    section: string | null;
    element: string | null;
    item_type: string;
    boq_reference: string | null;
    name: string;
    description: string | null;
    unit_of_measure_id: string;
    planned_quantity: string;
    selling_rate: string | null;
    source_sheet: string;
    source_row: number;
};
type Row = {
    id: string | number;
    line: Line;
    source_unit: string;
    source_amount: string;
    warnings: string[];
    blocked: boolean;
    change: string;
    classification?: string;
    commercial_review?: boolean;
    daywork_resource_required?: boolean;
};
type Preview = {
    preview_id: string;
    title: string;
    notes: string;
    currency_code: string;
    rows: Row[];
    skipped: {
        sheet: string;
        row: number;
        description: string;
        reason: string;
    }[];
    retained: {
        name: string;
        boq_reference: string | null;
        work_item_key: string;
        item_type: string;
    }[];
};
type Props = {
    pageMode: 'upload' | 'review';
    targetId: string | null;
    imports: Array<{ id: string; filename: string; created_at: string; saved: boolean; can_resume: boolean; estimate_id: string | null }>;
    project: Project;
    token: string | null;
    filename: string | null;
    preview: Preview | null;
    drafts: { id: string; title: string; version_number: number }[];
    units: Option[];
    itemTypes: Option[];
    items: Array<Option & { unit_id: string }>;
    equipmentCategories: Option[];
    workforceTrades: Option[];
};

function Errors({ errors }: { errors: Record<string, string | undefined> }) {
    const messages = [
        ...new Set(
            Object.values(errors).filter(
                (message): message is string => typeof message === 'string',
            ),
        ),
    ];
    return messages.length > 0 ? (
        <div
            role="alert"
            className="rounded-md border border-destructive p-3 text-sm text-destructive"
        >
            {messages.map((message) => (
                <p key={message}>{message}</p>
            ))}
        </div>
    ) : null;
}

export default function BoqImport(props: Props) {
    const { project, token, filename } = props;
    const uploadForm = useForm<{ file: File | null }>({ file: null });

    const reviewUrl = token ? `/projects/${project.id}/estimates/import/${token}/review` : '';

    if (props.preview && token && props.pageMode === 'review') {
        return (
            <AppLayout breadcrumbs={[
                { title: project.reference, href: projectShow.url(project.id) },
                { title: 'Import BOQ', href: index.url(project.id) },
                { title: 'Review', href: reviewUrl },
            ]}>
                <Head title="Review BOQ import" />
                <div className="flex flex-col gap-5 p-4 md:p-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 className="text-2xl font-semibold">Review import</h1>
                            <p className="text-sm text-muted-foreground">{filename}</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button asChild variant="outline"><Link href={index.url(project.id)}>Back</Link></Button>
                            <Button type="submit" form="boq-import-review">Commit import</Button>
                        </div>
                    </div>
                    {props.drafts.length > 0 && (
                        <PreviewSetup
                            key={`${token}-${props.targetId ?? 'baseline'}`}
                            project={project}
                            token={token}
                            drafts={props.drafts}
                            targetId={props.targetId}
                        />
                    )}
                    <Review
                        key={props.preview.preview_id}
                        project={project}
                        token={token}
                        preview={props.preview}
                        units={props.units}
                        itemTypes={props.itemTypes}
                        items={props.items}
                        equipmentCategories={props.equipmentCategories}
                        workforceTrades={props.workforceTrades}
                    />
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: project.reference, href: projectShow.url(project.id) },
                { title: 'Import BOQ', href: index.url(project.id) },
            ]}
        >
            <Head title="Import BOQ" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Import BOQ</h1>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={showBoq.url(project.id)}>Back to BOQ</Link>
                    </Button>
                </div>
                <div className="rounded-lg border bg-card p-4 text-sm">
                    Start with the supplied template. Upload creates a pending import; commit it after checking the items to create a BOQ draft.
                </div>
                <form
                    className="grid gap-4 rounded-lg border bg-card p-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        uploadForm.post(upload.url(project.id));
                    }}
                >
                    <h2 className="text-lg font-semibold">Upload BOQ items</h2>
                    <div className="flex flex-wrap items-end gap-3">
                        <label className="grid min-w-64 flex-1 gap-1 text-sm">
                            BOQ Excel workbook (.xlsx, up to 15 MB)
                            <Input
                                id="boq-file"
                                type="file"
                                accept=".xlsx"
                                onChange={(event) =>
                                    uploadForm.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </label>
                        <Button
                            className="w-fit"
                            disabled={!uploadForm.data.file || uploadForm.processing}
                        >
                            {uploadForm.processing ? 'Uploading...' : 'Upload and import'}
                        </Button>
                        <Button asChild variant="outline">
                            <a href={template.url(project.id)}>
                                Download template
                            </a>
                        </Button>
                    </div>
                    <Errors errors={uploadForm.errors} />
                </form>
                <section className="grid gap-3 rounded-lg border bg-card p-6">
                    <div>
                        <h2 className="text-lg font-semibold">Template columns</h2>
                        <p className="text-sm text-muted-foreground">Keep the six headers and their order as provided.</p>
                    </div>
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader><TableRow><TableHead>Column</TableHead><TableHead>Required?</TableHead></TableRow></TableHeader>
                            <TableBody>
                                <TableRow><TableCell>Reference</TableCell><TableCell>No</TableCell></TableRow>
                                <TableRow><TableCell>Description</TableCell><TableCell>Yes</TableCell></TableRow>
                                <TableRow><TableCell>Unit</TableCell><TableCell>Yes</TableCell></TableRow>
                                <TableRow><TableCell>Quantity</TableCell><TableCell>Yes</TableCell></TableRow>
                                <TableRow><TableCell>Client rate</TableCell><TableCell>No</TableCell></TableRow>
                                <TableRow><TableCell>Amount</TableCell><TableCell>No, calculated from quantity × rate</TableCell></TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </section>
                {props.imports.length > 0 && <div className="mt-12 grid gap-3 rounded-lg border bg-card p-6">
                    <h2 className="text-lg font-semibold">Import history</h2>
                    <Table><TableHeader><TableRow><TableHead>Workbook</TableHead><TableHead>Uploaded</TableHead><TableHead>Status</TableHead><TableHead>Actions</TableHead></TableRow></TableHeader>
                        <TableBody>{props.imports.map((item) => <TableRow key={item.id}>
                            <TableCell>{item.filename}</TableCell><TableCell>{item.created_at}</TableCell><TableCell>{item.saved ? 'Saved to draft' : 'Review pending'}</TableCell>
                            <TableCell><div className="flex gap-2"><Button asChild variant="outline" size="sm"><a href={source.url({ project: project.id, boqImport: item.id })}>Download source</a></Button>
                        {item.can_resume && <Button asChild variant="outline" size="sm"><Link preserveScroll={false} href={index.url(project.id, { query: { import: item.id } })}>Review</Link></Button>}
                                {item.saved && item.estimate_id && <Button asChild variant="outline" size="sm"><Link href={showEstimate.url(item.estimate_id)}>View saved draft</Link></Button>}
                            </div></TableCell></TableRow>)}</TableBody>
                    </Table>
                </div>}
            </div>
        </AppLayout>
    );
}

function PreviewSetup({
    project,
    token,
    drafts,
    targetId,
}: Pick<Props, 'project' | 'drafts' | 'targetId'> & { token: string }) {
    const form = useForm({ target_id: targetId ?? '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({ target_id: data.target_id || null }));
        form.post(previewImport.url({ project: project.id, import: token }), {
            preserveScroll: true,
        });
    }

    return (
        <details className="rounded-lg border p-3">
            <summary className="cursor-pointer text-sm">Import into an existing draft</summary>
            <form onSubmit={submit} className="mt-3 grid max-w-xl gap-3">
                <label className="grid gap-1 text-sm">
                    Draft
                    <NativeSelect
                        value={form.data.target_id}
                        onChange={(event) => form.setData('target_id', event.target.value)}
                    >
                        <NativeSelectOption value="">Create a new draft from the approved BOQ</NativeSelectOption>
                        {drafts.map((draft) => (
                            <NativeSelectOption key={draft.id} value={draft.id}>
                                Draft v{draft.version_number}: {draft.title}
                            </NativeSelectOption>
                        ))}
                    </NativeSelect>
                </label>
                <Errors errors={form.errors} />
                <Button className="w-fit" variant="outline" disabled={form.processing}>
                    {form.processing ? 'Loading draft...' : 'Load draft'}
                </Button>
            </form>
        </details>
    );
}

function Review({
    project,
    token,
    preview,
    units,
    itemTypes,
    items,
    equipmentCategories,
    workforceTrades,
}: {
    project: Project;
    token: string;
    preview: Preview;
    units: Option[];
    itemTypes: Option[];
    items: Array<Option & { unit_id: string }>;
    equipmentCategories: Option[];
    workforceTrades: Option[];
}) {
    const [selected, setSelected] = useState(
        preview.rows.filter((row) => !row.blocked).map((row) => row.id),
    );
    const form = useForm({
        import_mode: 'scope',
        remove_keys: [] as string[],
        title: preview.title,
        currency_code: preview.currency_code,
        notes: preview.notes,
        preview_id: preview.preview_id,
        lines: preview.rows.map((row) => ({
            ...row.line,
            selling_rate: row.line.selling_rate ?? '',
            percentage_rate: row.line.percentage_rate ?? '',
            percentage_base_keys: row.line.percentage_base_keys ?? [],
            daywork_resource_type: row.line.daywork_resource_type ?? '',
            daywork_inventory_item_id: row.line.daywork_inventory_item_id ?? '',
            daywork_equipment_category_id:
                row.line.daywork_equipment_category_id ?? '',
            daywork_workforce_trade_id:
                row.line.daywork_workforce_trade_id ?? '',
            bill: row.line.bill ?? '',
            section: row.line.section ?? '',
            element: row.line.element ?? '',
            description: row.line.description ?? '',
            boq_reference: row.line.boq_reference ?? '',
        })),
    });
    function change(i: number, field: string, value: string) {
        form.setData(
            'lines',
            form.data.lines.map((line, index) =>
                i === index ? { ...line, [field]: value } : line,
            ),
        );
    }
    function canInclude(row: Row, index: number): boolean {
        if (!row.blocked) return true;
        if (!row.daywork_resource_required) return false;

        const line = form.data.lines[index];
        return Boolean(
            (line.daywork_resource_type === 'labour' && line.daywork_workforce_trade_id) ||
            (line.daywork_resource_type === 'equipment' && line.daywork_equipment_category_id) ||
            (line.daywork_resource_type === 'material' && line.daywork_inventory_item_id),
        );
    }
    const missing = form.data.lines.some(
        (line, i) =>
            selected.includes(preview.rows[i].id) &&
            (!line.unit_of_measure_id ||
                !line.name ||
                (line.item_type === 'percentage_adjustment' &&
                    line.percentage_base_keys.length === 0) ||
                (line.item_type === 'daywork' &&
                    (!line.daywork_resource_type ||
                        !(
                            line.daywork_inventory_item_id ||
                            line.daywork_equipment_category_id ||
                            line.daywork_workforce_trade_id
                        ))) ||
                !Number.isFinite(Number(line.planned_quantity)) ||
                Number(line.planned_quantity) <= 0),
    );
    const unresolvedUnits = [
        ...new Set(preview.rows.map((row) => row.source_unit)),
    ].filter((symbol) => {
        const resolved = new Set(
            form.data.lines
                .filter((_, i) => preview.rows[i].source_unit === symbol)
                .map((line) => line.unit_of_measure_id)
                .filter(Boolean),
        );
        return resolved.size !== 1;
    });
    const readyCount = preview.rows.filter((row, i) => {
        const line = form.data.lines[i];
        return canInclude(row, i) && Boolean(line.unit_of_measure_id) && Boolean(line.name)
            && Number.isFinite(Number(line.planned_quantity)) && Number(line.planned_quantity) > 0
            && (line.item_type !== 'daywork' || Boolean(line.daywork_resource_type && (line.daywork_inventory_item_id || line.daywork_equipment_category_id || line.daywork_workforce_trade_id)))
            && (line.item_type !== 'percentage_adjustment' || line.percentage_base_keys.length > 0);
    }).length;
    return (
        <form
            id="boq-import-review"
            className="grid gap-4 rounded-lg border p-4"
            onSubmit={(event) => {
                event.preventDefault();
                if (selected.length === 0 || missing) return;
                form.transform((data) => ({
                    ...data,
                    lines: data.lines.filter((_, i) =>
                        selected.includes(preview.rows[i].id),
                    ),
                }));
                form.post(store.url({ project: project.id, import: token }), {
                    preserveScroll: true,
                });
            }}
        >
            <h2 className="text-lg font-semibold">BOQ items</h2>
            <details>
                <summary className="cursor-pointer text-sm">Import options</summary>
                <label className="mt-2 grid max-w-xl gap-1 text-sm">Apply uploaded items as
                <NativeSelect value={form.data.import_mode} onChange={(event) => {
                    form.setData('import_mode', event.target.value);
                    form.setData('remove_keys', []);
                    if (event.target.value === 'prices') setSelected(preview.rows.filter((row) => !row.blocked && row.change !== 'New').map((row) => row.id));
                }}><NativeSelectOption value="scope">Update BOQ items and prices</NativeSelectOption><NativeSelectOption value="prices">Update prices only</NativeSelectOption></NativeSelect>
                </label>
            </details>
            {form.data.import_mode === 'prices' && <p className="text-sm text-muted-foreground">Only prices of existing BOQ items change. Quantities, descriptions, resources and missing items stay as they are. Blank prices keep the current price.</p>}
            {form.data.import_mode === 'scope' && preview.retained.length > 0 && <details className="rounded-md border p-3"><summary>Existing BOQ items not found in this workbook</summary>
                <p className="my-2 text-sm text-muted-foreground">Keep missing items unless this revision deliberately omits them. Their earlier revisions and progress history remain available.</p>
                {preview.retained.map((item) => <label key={item.work_item_key} className="flex items-center gap-2 py-1 text-sm"><input type="checkbox" checked={form.data.remove_keys.includes(item.work_item_key)} onChange={(event) => {
                    form.setData('remove_keys', event.target.checked ? [...form.data.remove_keys, item.work_item_key] : form.data.remove_keys.filter((key) => key !== item.work_item_key));
                }} />Remove from the next draft: {item.boq_reference} {item.name}</label>)}
            </details>}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                {[
                    ['Total', preview.rows.length],
                    ['Ready', readyCount],
                    ['Need attention', preview.rows.length - readyCount],
                    ['New', preview.rows.filter((row) => row.change === 'New').length],
                    ['Existing', preview.rows.filter((row) => row.change !== 'New').length],
                ].map(([label, count]) => (
                    <div key={label} className="rounded-lg border p-4">
                        <div className="text-sm text-muted-foreground">{label}</div>
                        <div className="mt-3 text-2xl font-semibold">{count}</div>
                    </div>
                ))}
            </div>
            <details>
                <summary className="cursor-pointer text-sm">Draft details</summary>
                <div className="mt-3 grid gap-3 sm:max-w-2xl">
                    <label className="grid gap-1 text-sm">
                        Draft title
                        <Input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />
                    </label>
                    <label className="grid gap-1 text-sm">
                        Notes
                        <Textarea value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} rows={2} />
                    </label>
                </div>
            </details>
            {unresolvedUnits.length > 0 && (
            <div className="grid gap-3 rounded-md border p-3 sm:grid-cols-2">
                {unresolvedUnits.map(
                    (symbol) => (
                        <div key={symbol} className="min-w-56">
                            <Label>Choose system unit for “{symbol || '(blank)'}”</Label>
                            <SearchableSelect
                                value={(() => {
                                    const mapped = new Set(
                                        form.data.lines
                                            .filter(
                                                (_, i) =>
                                                    preview.rows[i]
                                                        .source_unit === symbol,
                                            )
                                            .map(
                                                (line) =>
                                                    line.unit_of_measure_id,
                                            ),
                                    );
                                    return mapped.size === 1
                                        ? [...mapped][0]
                                        : '';
                                })()}
                                options={units}
                                placeholder="Choose a system unit"
                                onValueChange={(value) => {
                                    form.setData(
                                        'lines',
                                        form.data.lines.map((line, i) =>
                                            preview.rows[i].source_unit ===
                                            symbol
                                                ? {
                                                      ...line,
                                                      unit_of_measure_id: value,
                                                  }
                                                : line,
                                        ),
                                    );
                                }}
                            />
                        </div>
                    ),
                )}
            </div>
            )}
            <div className="overflow-x-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                    <TableHead className="w-16">Use</TableHead>
                    <TableHead>BOQ item</TableHead>
                    <TableHead>Quantity / rate</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Details</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {preview.rows.map((row, i) => (
                            <TableRow key={row.id}>
                                <TableCell>
                            <input
                                type="checkbox"
                                aria-label={`Include ${row.line.name || 'BOQ item'}`}
                                disabled={!canInclude(row, i)}
                                checked={selected.includes(row.id)}
                                onChange={(event) => {
                                    setSelected(
                                        event.target.checked
                                            ? [...selected, row.id]
                                            : selected.filter(
                                                  (id) => id !== row.id,
                                              ),
                                    );
                                }}
                            />
                                </TableCell>
                                <TableCell>
                                    <div className="font-medium">{row.line.name || '(missing description)'}</div>
                                    <div className="text-xs text-muted-foreground">
                                        {row.line.boq_reference ? `${row.line.boq_reference} · ` : ''}{row.line.source_sheet}, row {row.line.source_row}
                                    </div>
                                </TableCell>
                                <TableCell className="whitespace-nowrap">
                                    {row.line.planned_quantity || '—'} {row.source_unit}
                                    {' · '}
                                    {row.line.selling_rate ? `${preview.currency_code} ${row.line.selling_rate}` : 'Unpriced'}
                                </TableCell>
                                <TableCell>
                                    {canInclude(row, i)
                                        ? row.change === 'New'
                                            ? 'New item'
                                            : row.change === 'Changed'
                                              ? 'Existing item will be updated'
                                              : 'Existing item'
                                        : row.classification === 'Dayworks'
                                        ? 'Choose daywork resource'
                                          : row.commercial_review
                                            ? 'Needs commercial review'
                                            : 'Needs attention'}
                                </TableCell>
                                <TableCell>
                                    <details open={!canInclude(row, i) || !form.data.lines[i].unit_of_measure_id}>
                                        <summary className="cursor-pointer text-sm">{row.warnings.length || !canInclude(row, i) ? 'Fix item' : 'Edit'}</summary>
                                        <div className="mt-3 grid gap-3">
                        {row.warnings.length > 0 && (
                            <ul className="list-disc pl-5 text-sm text-muted-foreground">
                                {row.warnings.map((warning, index) => (
                                    <li key={index}>{warning}</li>
                                ))}
                            </ul>
                        )}
                        <div className="grid gap-3 sm:grid-cols-3">
                            {(
                                [
                                    'bill',
                                    'section',
                                    'element',
                                    'boq_reference',
                                    'name',
                                ] as const
                            ).map((field) => (
                                <label
                                    key={field}
                                    className="grid gap-1 text-sm"
                                >
                                    {field.replaceAll('_', ' ')}
                                    <Input
                                        value={form.data.lines[i][field]}
                                        onChange={(event) =>
                                            change(i, field, event.target.value)
                                        }
                                    />
                                </label>
                            ))}
                            <label className="grid gap-1 text-sm">
                                Item type
                                <NativeSelect
                                    disabled={
                                        (row.commercial_review &&
                                            row.classification !== 'Dayworks') ||
                                        [
                                            'percentage_adjustment',
                                            'daywork',
                                        ].includes(row.line.item_type)
                                    }
                                    value={
                                        row.commercial_review &&
                                        row.classification !== 'Dayworks'
                                            ? ''
                                            : form.data.lines[i].item_type
                                    }
                                    onChange={(event) =>
                                        change(
                                            i,
                                            'item_type',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {row.commercial_review &&
                                        row.classification !== 'Dayworks' && (
                                        <NativeSelectOption value="">
                                            {row.classification} — review
                                            required
                                        </NativeSelectOption>
                                    )}
                                    {row.classification === 'Dayworks' && (
                                        <NativeSelectOption value="daywork">
                                            Daywork
                                        </NativeSelectOption>
                                    )}
                                    {itemTypes.map((type) => (
                                        <NativeSelectOption
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                            </label>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <label className="grid gap-1 text-sm">
                                Unit
                                <SearchableSelect
                                    value={
                                        form.data.lines[i].unit_of_measure_id
                                    }
                                    options={
                                        form.data.lines[i].item_type ===
                                            'preliminary_time' ||
                                        (form.data.lines[i].item_type ===
                                            'daywork' &&
                                            ['labour', 'equipment'].includes(
                                                form.data.lines[i]
                                                    .daywork_resource_type,
                                            ))
                                            ? units.filter(
                                                  (unit) =>
                                                      unit.dimension === 'time',
                                              )
                                            : units
                                    }
                                    onValueChange={(value) =>
                                        change(i, 'unit_of_measure_id', value)
                                    }
                                />
                            </label>
                            <label className="grid gap-1 text-sm">
                                {form.data.lines[i].item_type ===
                                'preliminary_time'
                                    ? 'Planned duration'
                                    : 'Quantity'}
                                <Input
                                    type="number"
                                    step="any"
                                    value={form.data.lines[i].planned_quantity}
                                    onChange={(event) =>
                                        change(
                                            i,
                                            'planned_quantity',
                                            event.target.value,
                                        )
                                    }
                                />
                            </label>
                            <label className="grid gap-1 text-sm">
                                            {form.data.lines[i].item_type === 'preliminary_time'
                                    ? 'Rate per time unit'
                                    : form.data.lines[i].item_type === 'daywork'
                                      ? 'Agreed rate'
                                      : 'Selling rate'}{' '}
                                (blank = unpriced)
                                <Input
                                    type="number"
                                    step="any"
                                    min="0"
                                    value={form.data.lines[i].selling_rate}
                                    readOnly={
                                        form.data.lines[i].item_type ===
                                        'percentage_adjustment'
                                    }
                                    onChange={(event) =>
                                        change(
                                            i,
                                            'selling_rate',
                                            event.target.value,
                                        )
                                    }
                                />
                            </label>
                        </div>
                        {form.data.lines[i].item_type === 'daywork' && (
                            <div className="grid gap-3 sm:grid-cols-2">
                                <label className="grid gap-1 text-sm">
                                    What will this daywork charge for?
                                    <NativeSelect
                                        value={
                                            form.data.lines[i]
                                                .daywork_resource_type
                                        }
                                        onChange={(event) => {
                                            const value = event.target.value;
                                            form.setData(
                                                'lines',
                                                form.data.lines.map(
                                                    (line, index) =>
                                                        index === i
                                                            ? {
                                                                  ...line,
                                                                  daywork_resource_type:
                                                                      value,
                                                                  daywork_inventory_item_id:
                                                                      '',
                                                                  daywork_equipment_category_id:
                                                                      '',
                                                                  daywork_workforce_trade_id:
                                                                      '',
                                                              }
                                                            : line,
                                                ),
                                            );
                                        }}
                                    >
                                        <NativeSelectOption value="">
                                            Choose one
                                        </NativeSelectOption>
                                        <NativeSelectOption value="labour">
                                            Labour hours for a trade
                                        </NativeSelectOption>
                                        <NativeSelectOption value="equipment">
                                            Equipment hours for a category
                                        </NativeSelectOption>
                                        <NativeSelectOption value="material">
                                            Materials issued from stock
                                        </NativeSelectOption>
                                    </NativeSelect>
                                </label>
                                <label className="grid gap-1 text-sm">
                                    Choose the trade, equipment category or stock item
                                    <SearchableSelect
                                        value={
                                            form.data.lines[i]
                                                .daywork_resource_type ===
                                            'material'
                                                ? form.data.lines[i]
                                                      .daywork_inventory_item_id
                                                : form.data.lines[i]
                                                        .daywork_resource_type ===
                                                    'equipment'
                                                  ? form.data.lines[i]
                                                        .daywork_equipment_category_id
                                                  : form.data.lines[i]
                                                        .daywork_workforce_trade_id
                                        }
                                        options={
                                            form.data.lines[i]
                                                .daywork_resource_type ===
                                            'material'
                                                ? items
                                                : form.data.lines[i]
                                                        .daywork_resource_type ===
                                                    'equipment'
                                                  ? equipmentCategories
                                                  : workforceTrades
                                        }
                                        onValueChange={(value) => {
                                            const current = form.data.lines[i];
                                            const item = items.find(
                                                (candidate) =>
                                                    candidate.value === value,
                                            );
                                            form.setData(
                                                'lines',
                                                form.data.lines.map(
                                                    (line, index) =>
                                                        index === i
                                                            ? {
                                                                  ...line,
                                                                  daywork_inventory_item_id:
                                                                      current.daywork_resource_type ===
                                                                      'material'
                                                                          ? value
                                                                          : '',
                                                                  daywork_equipment_category_id:
                                                                      current.daywork_resource_type ===
                                                                      'equipment'
                                                                          ? value
                                                                          : '',
                                                                  daywork_workforce_trade_id:
                                                                      current.daywork_resource_type ===
                                                                      'labour'
                                                                          ? value
                                                                          : '',
                                                                  ...(current.daywork_resource_type ===
                                                                      'material' &&
                                                                  item
                                                                      ? {
                                                                            unit_of_measure_id:
                                                                                item.unit_id,
                                                                        }
                                                                      : {}),
                                                              }
                                                            : line,
                                                ),
                                            );
                                        }}
                                    />
                                </label>
                            </div>
                        )}
                        {form.data.lines[i].item_type === 'daywork' && (
                            <p className="text-sm text-muted-foreground">
                                The agreed rate is charged against approved daily site records: labour or equipment hours, or materials issued from stock. This is separate from resources attached to a normal BOQ item.
                            </p>
                        )}
                        {form.data.lines[i].item_type ===
                            'percentage_adjustment' && (
                            <div className="space-y-2">
                                <Label>
                                    Percentage (negative for a deduction; blank
                                    = unpriced)
                                </Label>
                                <Input
                                    type="number"
                                    step="0.0001"
                                    value={form.data.lines[i].percentage_rate}
                                    onChange={(event) =>
                                        change(
                                            i,
                                            'percentage_rate',
                                            event.target.value,
                                        )
                                    }
                                />
                                <Label>Calculation base</Label>
                                <div className="max-h-64 overflow-auto rounded-md border p-3">
                                    {[
                                        ...preview.retained,
                                        ...form.data.lines.filter((_, index) =>
                                            selected.includes(
                                                preview.rows[index].id,
                                            ),
                                        ),
                                    ]
                                        .filter(
                                            (base) =>
                                                base.work_item_key !== form.data.lines[i].work_item_key && !form.data.remove_keys.includes(base.work_item_key),
                                        )
                                        .map((base) => (
                                            <label
                                                key={base.work_item_key}
                                                className="flex items-center gap-2 py-1 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={form.data.lines[
                                                        i
                                                    ].percentage_base_keys.includes(
                                                        base.work_item_key,
                                                    )}
                                                    onChange={(event) => {
                                                        form.setData(
                                                            'lines',
                                                            form.data.lines.map(
                                                                (
                                                                    line,
                                                                    index,
                                                                ) =>
                                                                    index === i
                                                                        ? {
                                                                              ...line,
                                                                              percentage_base_keys:
                                                                                  event
                                                                                      .target
                                                                                      .checked
                                                                                      ? [
                                                                                            ...line.percentage_base_keys,
                                                                                            base.work_item_key,
                                                                                        ]
                                                                                      : line.percentage_base_keys.filter(
                                                                                            (
                                                                                                key,
                                                                                            ) =>
                                                                                                key !==
                                                                                                base.work_item_key,
                                                                                        ),
                                                                          }
                                                                        : line,
                                                            ),
                                                        );
                                                    }}
                                                />
                                                {base.boq_reference} {base.name}
                                            </label>
                                        ))}
                                </div>
                            </div>
                        )}
                        <label className="grid gap-1 text-sm">
                            Full specification
                            <Textarea
                                value={form.data.lines[i].description}
                                onChange={(event) =>
                                    change(i, 'description', event.target.value)
                                }
                            />
                        </label>
                                        </div>
                                    </details>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
            <details>
                <summary className="cursor-pointer text-sm">
                    Spreadsheet headings and totals skipped ({preview.skipped.length})
                </summary>
                <div className="max-h-64 overflow-auto text-sm">
                    {preview.skipped.map((row) => (
                        <p key={`${row.sheet}:${row.row}`}>
                            {row.sheet}, row {row.row}: {row.reason} —{' '}
                            {row.description}
                        </p>
                    ))}
                </div>
            </details>
            {preview.retained.length > 0 && (
                <details>
                    <summary className="cursor-pointer text-sm">
                        Existing items absent from upload ({preview.retained.length})
                    </summary>
                    {preview.retained.map((line, i) => (
                        <p key={i} className="text-sm">
                            {line.boq_reference} {line.name}
                        </p>
                    ))}
                </details>
            )}
            <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                <span className="text-sm text-muted-foreground">Draft currency: {form.data.currency_code}</span>
                <Errors errors={form.errors} />
            </div>
            {missing && (
                <p className="text-sm text-destructive">
                    Add a description, system unit and quantity above zero for
                    each selected item. Daywork items also need a usage type
                    and the trade, equipment category or stock item to charge.
                </p>
            )}
        </form>
    );
}
