import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { show as showBoq } from '@/actions/App/Http/Controllers/Operations/ProjectBoqController';
import { show as projectShow } from '@/actions/App/Http/Controllers/Operations/ProjectController';
import {
    index,
    upload,
    template,
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
type Sheet = {
    id: string;
    name: string;
    hidden: boolean;
    last_row: number;
    sample: Record<string, Record<string, { value: string }>>;
};
type Line = {
    percentage_rate: string | null;
    percentage_base_keys: string[];
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
    id: string;
    line: Line;
    source_unit: string;
    source_amount: string;
    warnings: string[];
    blocked: boolean;
    change: string;
    classification?: string;
    commercial_review?: boolean;
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
    project: Project;
    token: string | null;
    filename: string | null;
    sheets: Sheet[];
    preview: Preview | null;
    drafts: { id: string; title: string; version_number: number }[];
    units: Option[];
    itemTypes: Option[];
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
    const { project, token, sheets, filename } = props;
    const [mappingDirty, setMappingDirty] = useState(false);
    const uploadForm = useForm<{ file: File | null }>({ file: null });
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
                        <p className="text-sm text-muted-foreground">
                            {project.name}. Upload, map and review before saving
                            a draft.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={showBoq.url(project.id)}>Back to BOQ</Link>
                    </Button>
                </div>
                <form
                    className="grid gap-3 rounded-lg border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        uploadForm.post(upload.url(project.id), {
                            onSuccess: () => setMappingDirty(false),
                        });
                    }}
                >
                    <Label htmlFor="boq-file">
                        Excel workbook (.xlsx, up to 15 MB)
                    </Label>
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
                    <Errors errors={uploadForm.errors} />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            className="w-fit"
                            disabled={
                                !uploadForm.data.file || uploadForm.processing
                            }
                        >
                            {uploadForm.processing
                                ? 'Reading workbook...'
                                : 'Upload workbook'}
                        </Button>
                        <Button asChild variant="outline">
                            <a href={template.url(project.id)}>
                                Download Excel template
                            </a>
                        </Button>
                    </div>
                    {filename && (
                        <p className="text-sm text-muted-foreground">
                            Current workbook: {filename}. Imports expire after
                            two hours. Formula results are read as saved in
                            Excel.
                        </p>
                    )}
                </form>
                {token && (
                    <Mapping
                        key={token}
                        project={project}
                        token={token}
                        sheets={sheets}
                        drafts={props.drafts}
                        onDirty={() => setMappingDirty(true)}
                        onPreview={() => setMappingDirty(false)}
                    />
                )}
                {mappingDirty && props.preview && (
                    <p role="status" className="text-sm text-muted-foreground">
                        Mapping changed. Generate a new preview before saving.
                    </p>
                )}
                {token && props.preview && !mappingDirty && (
                    <Review
                        key={props.preview.preview_id}
                        project={project}
                        token={token}
                        preview={props.preview}
                        units={props.units}
                        itemTypes={props.itemTypes}
                    />
                )}
            </div>
        </AppLayout>
    );
}

function Mapping({
    project,
    token,
    sheets,
    drafts,
    onDirty,
    onPreview,
}: Pick<Props, 'project' | 'sheets' | 'drafts'> & {
    token: string;
    onDirty: () => void;
    onPreview: () => void;
}) {
    const form = useForm({
        target_id: '',
        sheets: sheets.map((sheet) => {
            const isTemplate =
                sheet.sample['1']?.A?.value === 'Reference' &&
                sheet.sample['1']?.B?.value === 'Description';
            return {
                sheet: sheet.id,
                selected: false,
                start_row: isTemplate ? 2 : 1,
                end_row: sheet.last_row,
                bill: sheet.name,
                section: '',
                element: '',
                reference: isTemplate ? 'A' : 'B',
                description: isTemplate ? 'B' : 'C',
                unit: isTemplate ? 'C' : 'D',
                quantity: isTemplate ? 'D' : 'E',
                rate: isTemplate ? 'E' : 'F',
                amount: isTemplate ? 'F' : 'G',
            };
        }),
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            target_id: data.target_id || null,
            sheets: data.sheets.filter((sheet) => sheet.selected),
        }));
        form.post(previewImport.url({ project: project.id, import: token }), {
            preserveScroll: true,
            onSuccess: onPreview,
        });
    }
    function change(
        i: number,
        field: string,
        value: string | number | boolean,
    ) {
        onDirty();
        form.setData(
            'sheets',
            form.data.sheets.map((sheet, row) =>
                row === i ? { ...sheet, [field]: value } : sheet,
            ),
        );
    }
    return (
        <form onSubmit={submit} className="grid gap-4 rounded-lg border p-4">
            <h2 className="text-lg font-semibold">Choose sheets and columns</h2>
            <p className="text-sm text-muted-foreground">
                Select detailed BOQ sheets. Leave summaries, measurement
                workings and cover sheets unchecked. Enter Excel column letters.
                Separate headings and specifications are retained as description
                context. Leave section / floor blank to detect explicit floor
                headings in the workbook. Enter a section to apply it to the
                whole selected range.
            </p>
            <Label htmlFor="target-estimate">Save destination</Label>
            <NativeSelect
                id="target-estimate"
                value={form.data.target_id}
                onChange={(event) => {
                    onDirty();
                    form.setData('target_id', event.target.value);
                }}
            >
                <NativeSelectOption value="">
                    New draft revision based on the current baseline
                </NativeSelectOption>
                {drafts.map((draft) => (
                    <NativeSelectOption key={draft.id} value={draft.id}>
                        Update draft v{draft.version_number}: {draft.title}
                    </NativeSelectOption>
                ))}
            </NativeSelect>
            {sheets.map((sheet, i) => (
                <div key={sheet.id} className="rounded-md border p-3">
                    <label className="flex items-center gap-2 font-medium">
                        <input
                            type="checkbox"
                            checked={form.data.sheets[i].selected}
                            onChange={(event) =>
                                change(i, 'selected', event.target.checked)
                            }
                        />
                        {sheet.name}
                        {sheet.hidden ? ' (hidden sheet)' : ''}
                    </label>
                    {form.data.sheets[i].selected && (
                        <div className="mt-4 grid gap-4">
                            <div className="grid gap-3 sm:grid-cols-3">
                                {(['bill', 'section', 'element'] as const).map(
                                    (field) => (
                                        <label
                                            key={field}
                                            className="grid gap-1 text-sm"
                                        >
                                            {field === 'section'
                                                ? 'Section / floor'
                                                : field === 'bill'
                                                  ? 'Bill'
                                                  : 'Element (optional)'}
                                            <Input
                                                placeholder={
                                                    field === 'section'
                                                        ? 'Detect floor headings automatically'
                                                        : undefined
                                                }
                                                value={
                                                    form.data.sheets[i][field]
                                                }
                                                onChange={(event) =>
                                                    change(
                                                        i,
                                                        field,
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </label>
                                    ),
                                )}
                            </div>
                            <div className="grid gap-3 sm:grid-cols-4">
                                {(
                                    [
                                        'reference',
                                        'description',
                                        'unit',
                                        'quantity',
                                        'rate',
                                        'amount',
                                    ] as const
                                ).map((field) => (
                                    <label
                                        key={field}
                                        className="grid gap-1 text-sm"
                                    >
                                        {field} column
                                        <Input
                                            maxLength={3}
                                            value={form.data.sheets[i][field]}
                                            onChange={(event) =>
                                                change(
                                                    i,
                                                    field,
                                                    event.target.value.toUpperCase(),
                                                )
                                            }
                                        />
                                    </label>
                                ))}
                                {(['start_row', 'end_row'] as const).map(
                                    (field) => (
                                        <label
                                            key={field}
                                            className="grid gap-1 text-sm"
                                        >
                                            {field === 'start_row'
                                                ? 'First row'
                                                : 'Last row'}
                                            <Input
                                                type="number"
                                                min="1"
                                                value={
                                                    form.data.sheets[i][field]
                                                }
                                                onChange={(event) =>
                                                    change(
                                                        i,
                                                        field,
                                                        Number(
                                                            event.target.value,
                                                        ),
                                                    )
                                                }
                                            />
                                        </label>
                                    ),
                                )}
                            </div>
                            <details>
                                <summary className="cursor-pointer text-sm">
                                    See first rows with column letters
                                </summary>
                                <div className="mt-2 max-h-64 overflow-auto text-xs">
                                    {Object.entries(sheet.sample).map(
                                        ([row, cells]) => (
                                            <div
                                                key={row}
                                                className="border-b py-2"
                                            >
                                                <strong>Row {row}: </strong>
                                                {Object.entries(cells).map(
                                                    ([column, cell]) => (
                                                        <span
                                                            key={column}
                                                            className="mr-3"
                                                        >
                                                            <strong>
                                                                {column}:
                                                            </strong>{' '}
                                                            {cell.value}
                                                        </span>
                                                    ),
                                                )}
                                            </div>
                                        ),
                                    )}
                                </div>
                            </details>
                        </div>
                    )}
                </div>
            ))}
            <Errors errors={form.errors} />
            <Button
                className="w-fit"
                disabled={
                    form.processing ||
                    !form.data.sheets.some((sheet) => sheet.selected)
                }
            >
                {form.processing ? 'Preparing preview...' : 'Preview import'}
            </Button>
        </form>
    );
}

function Review({
    project,
    token,
    preview,
    units,
    itemTypes,
}: {
    project: Project;
    token: string;
    preview: Preview;
    units: Option[];
    itemTypes: Option[];
}) {
    const [selected, setSelected] = useState(
        preview.rows.filter((row) => !row.blocked).map((row) => row.id),
    );
    const [reviewed, setReviewed] = useState(false);
    const form = useForm({
        title: preview.title,
        currency_code: preview.currency_code,
        notes: preview.notes,
        preview_id: preview.preview_id,
        lines: preview.rows.map((row) => ({
            ...row.line,
            selling_rate: row.line.selling_rate ?? '',
            percentage_rate: row.line.percentage_rate ?? '',
            percentage_base_keys: row.line.percentage_base_keys ?? [],
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
        setReviewed(false);
    }
    const missing = form.data.lines.some(
        (line, i) =>
            selected.includes(preview.rows[i].id) &&
            (!line.unit_of_measure_id ||
                !line.name ||
                (line.item_type === 'percentage_adjustment' &&
                    line.percentage_base_keys.length === 0) ||
                !Number.isFinite(Number(line.planned_quantity)) ||
                Number(line.planned_quantity) <= 0),
    );
    return (
        <form
            className="grid gap-4 rounded-lg border p-4"
            onSubmit={(event) => {
                event.preventDefault();
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
            <h2 className="text-lg font-semibold">Review import</h2>
            <p className="text-sm text-muted-foreground">
                {selected.length} selected of {preview.rows.length} items.{' '}
                {preview.skipped.length} headings or totals excluded.{' '}
                {preview.retained.length} existing items absent from this upload
                will be kept. No items are automatically deleted.
            </p>
            <label className="grid gap-1 text-sm">
                Draft title
                <Input
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                />
            </label>
            <label className="grid gap-1 text-sm">
                BOQ notes
                <Textarea
                    value={form.data.notes}
                    onChange={(event) =>
                        form.setData('notes', event.target.value)
                    }
                />
            </label>
            <p className="text-sm">
                Currency: {form.data.currency_code}. Internal costs and resource
                assumptions are preserved on matched items.
            </p>
            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Detected BOQ entry</TableHead>
                            <TableHead className="text-right">Rows</TableHead>
                            <TableHead>Handling</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {[
                            ...new Set(
                                preview.rows.map(
                                    (row) =>
                                        row.classification ?? 'Unclassified',
                                ),
                            ),
                        ].map((classification) => (
                            <TableRow key={classification}>
                                <TableCell>{classification}</TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {
                                        preview.rows.filter(
                                            (row) =>
                                                (row.classification ??
                                                    'Unclassified') ===
                                                classification,
                                        ).length
                                    }
                                </TableCell>
                                <TableCell>
                                    {classification === 'Measured work'
                                        ? 'Progress from approved activity output'
                                        : classification ===
                                            'Percentage adjustment'
                                          ? 'Percentage × selected BOQ amounts; choose the calculation base below'
                                          : classification ===
                                              'Time-based preliminary'
                                            ? 'Planned duration × rate per time unit; no measured progress'
                                            : classification ===
                                                'Fixed preliminary'
                                              ? 'Quantity 1 × agreed amount; no measured progress'
                                              : [
                                                      'Lump sum',
                                                      'Provisional sum',
                                                  ].includes(classification)
                                                ? 'Allowance; excluded from measured progress'
                                                : 'Commercial review required before saving'}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
            <div className="flex flex-wrap gap-4">
                {[...new Set(preview.rows.map((row) => row.source_unit))].map(
                    (symbol) => (
                        <div key={symbol} className="min-w-56">
                            <Label>Map source unit {symbol || '(blank)'}</Label>
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
                                placeholder="Apply a unit to matching rows"
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
                                    setReviewed(false);
                                }}
                            />
                        </div>
                    ),
                )}
            </div>
            {preview.rows.map((row, i) => (
                <details
                    key={row.id}
                    className="rounded-md border p-3"
                    open={row.blocked || !form.data.lines[i].unit_of_measure_id}
                >
                    <summary className="cursor-pointer">
                        <span className="font-medium">
                            {row.change}:{' '}
                            {row.line.name || '(missing description)'}
                        </span>
                        <span className="ml-2 text-sm text-muted-foreground">
                            {row.classification ?? 'Unclassified'} ·{' '}
                            {row.line.source_sheet}, row {row.line.source_row} ·{' '}
                            {row.warnings.length} notices
                        </span>
                    </summary>
                    <div className="mt-3 grid gap-3">
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                disabled={row.blocked}
                                checked={selected.includes(row.id)}
                                onChange={(event) => {
                                    setSelected(
                                        event.target.checked
                                            ? [...selected, row.id]
                                            : selected.filter(
                                                  (id) => id !== row.id,
                                              ),
                                    );
                                    setReviewed(false);
                                }}
                            />
                            Include this item
                            {row.blocked
                                ? row.commercial_review
                                    ? ' (commercial valuation not yet supported)'
                                    : ' (resolve the notices below first)'
                                : ''}
                        </label>
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
                                        row.commercial_review ||
                                        row.line.item_type ===
                                            'percentage_adjustment'
                                    }
                                    value={
                                        row.commercial_review
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
                                    {row.commercial_review && (
                                        <NativeSelectOption value="">
                                            {row.classification} — review
                                            required
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
                                        'preliminary_time'
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
                                {form.data.lines[i].item_type ===
                                'preliminary_time'
                                    ? 'Rate per time unit'
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
                                                base.item_type !==
                                                'percentage_adjustment',
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
                                                        setReviewed(false);
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
            ))}
            <details>
                <summary className="cursor-pointer text-sm">
                    Review excluded rows ({preview.skipped.length})
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
                        Existing items retained ({preview.retained.length})
                    </summary>
                    {preview.retained.map((line, i) => (
                        <p key={i} className="text-sm">
                            {line.boq_reference} {line.name}
                        </p>
                    ))}
                </details>
            )}
            <Errors errors={form.errors} />
            {missing && (
                <p className="text-sm text-destructive">
                    Complete the unit, description and positive quantity for
                    every selected item.
                </p>
            )}
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={reviewed}
                    onChange={(event) => setReviewed(event.target.checked)}
                />
                I reviewed the selected rows, unit mappings, pricing notices and
                retained items.
            </label>
            <Button
                className="w-fit"
                disabled={
                    form.processing ||
                    !reviewed ||
                    selected.length === 0 ||
                    missing
                }
            >
                {form.processing ? 'Saving draft...' : 'Save draft BOQ'}
            </Button>
        </form>
    );
}
