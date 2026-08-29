import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Pencil, Plus, Trash2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { formatCurrencyAmount, formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type {
    BranchOption,
    ExpenseCategory,
    ExpenseItem,
    Option,
    ScopedOption,
} from './types';

type Line = {
    key: string;
    expense_item_id: string;
    project_id: string;
    site_id: string;
    project_activity_id: string;
    description: string;
    quantity: string;
    unit_amount: string;
};
type ExpenseFormRecord = {
    id: string;
    branch_id: string;
    expense_date: string;
    payee_type: string;
    customer_id: string | null;
    staff_id: string | null;
    payee_name: string | null;
    currency_code: string;
    description: string | null;
    reference: string | null;
    lines: Omit<Line, 'key'>[];
};
type Props = {
    expense: ExpenseFormRecord | null;
    branches: BranchOption[];
    defaultBranchId: string | null;
    canChangeBranch: boolean;
    companies: ScopedOption[];
    staff: ScopedOption[];
    projects: ScopedOption[];
    sites: ScopedOption[];
    workItems: ScopedOption[];
    categories: ExpenseCategory[];
    expenseItems: ExpenseItem[];
    currencies: Option[];
    payeeTypes: Option[];
    paymentMethods: Option[];
    canRecordPayment: boolean;
};

let lineSequence = 0;
const key = () => `expense-line-${Date.now()}-${lineSequence++}`;
const blankLine = (): Line => ({
    key: key(),
    expense_item_id: '',
    project_id: '',
    site_id: '',
    project_activity_id: '',
    description: '',
    quantity: '1',
    unit_amount: '',
});

export default function ExpenseForm(props: Props) {
    const confirm = useConfirmDialog();
    const isEditing = props.expense !== null;
    const initialBranchId =
        props.expense?.branch_id ??
        props.defaultBranchId ??
        props.branches[0]?.value ??
        '';
    const initialBranch = props.branches.find(
        (branch) => branch.value === initialBranchId,
    );
    const form = useForm({
        branch_id: initialBranchId,
        expense_date:
            props.expense?.expense_date ??
            new Date().toISOString().slice(0, 10),
        payee_type: props.expense?.payee_type ?? 'company',
        customer_id: props.expense?.customer_id ?? '',
        staff_id: props.expense?.staff_id ?? '',
        payee_name: props.expense?.payee_name ?? '',
        currency_code:
            props.expense?.currency_code ?? initialBranch?.currency_code ?? '',
        description: props.expense?.description ?? '',
        reference: props.expense?.reference ?? '',
        lines:
            props.expense?.lines.map((line) => ({
                ...line,
                project_id: line.project_id ?? '',
                site_id: line.site_id ?? '',
                project_activity_id: line.project_activity_id ?? '',
                description: line.description ?? '',
                key: key(),
            })) ?? [],
        initial_payment_amount: '',
        initial_payment_method: '',
        initial_payment_reference: '',
    });
    const [draft, setDraft] = useState<Line>(blankLine());
    const [editingIndex, setEditingIndex] = useState<number | null>(null);
    const [categoryId, setCategoryId] = useState('');
    const selectedBranch = props.branches.find(
        (branch) => branch.value === form.data.branch_id,
    );
    const availableItems = props.expenseItems.filter(
        (item) => item.is_active && item.expense_category_id === categoryId,
    );
    const availableCompanies = props.companies.filter(
        (company) =>
            !company.branch_id || company.branch_id === form.data.branch_id,
    );
    const availableStaff = props.staff.filter(
        (staff) => staff.branch_id === form.data.branch_id,
    );
    const availableProjects = props.projects.filter(
        (project) => project.branch_id === form.data.branch_id,
    );
    const availableSites = props.sites.filter(
        (site) => site.project_id === draft.project_id,
    );
    const availableWorkItems = props.workItems.filter(
        (item) =>
            item.project_id === draft.project_id &&
            (!draft.site_id || !item.site_id || item.site_id === draft.site_id),
    );
    const total = useMemo(
        () =>
            form.data.lines.reduce(
                (sum, line) =>
                    sum +
                    Number(line.quantity || 0) * Number(line.unit_amount || 0),
                0,
            ),
        [form.data.lines],
    );
    const itemFor = (id: string) =>
        props.expenseItems.find((item) => item.id === id);

    function selectItem(value: string) {
        const item = itemFor(value);
        setDraft((line) => ({
            ...line,
            expense_item_id: value,
            description: line.description || item?.description || '',
        }));
    }
    function saveLine() {
        if (
            !draft.expense_item_id ||
            Number(draft.quantity) <= 0 ||
            Number(draft.unit_amount) <= 0
        )
            return;
        if (editingIndex === null)
            form.setData('lines', [...form.data.lines, draft]);
        else
            form.setData(
                'lines',
                form.data.lines.map((line, index) =>
                    index === editingIndex ? draft : line,
                ),
            );
        setDraft(blankLine());
        setCategoryId('');
        setEditingIndex(null);
    }
    function editLine(index: number) {
        const line = form.data.lines[index];
        setDraft(line);
        setCategoryId(itemFor(line.expense_item_id)?.expense_category_id ?? '');
        setEditingIndex(index);
    }
    function changeBranch(value: string) {
        const branch = props.branches.find((row) => row.value === value);
        form.setData((data) => ({
            ...data,
            branch_id: value,
            currency_code: branch?.currency_code ?? data.currency_code,
            customer_id: '',
            staff_id: '',
            lines: data.lines.map((line) => ({
                ...line,
                project_id: '',
                site_id: '',
                project_activity_id: '',
            })),
        }));
        setDraft((line) => ({
            ...line,
            project_id: '',
            site_id: '',
            project_activity_id: '',
        }));
    }
    function submit(event: FormEvent) {
        event.preventDefault();
        if (form.data.lines.length === 0 || form.processing) return;
        const paid = Number(form.data.initial_payment_amount || 0);
        confirm({
            title: isEditing
                ? 'Update this expense draft?'
                : 'Save this expense draft?',
            description: `The expense total is ${formatCurrencyAmount(form.data.currency_code, total)}${paid > 0 ? ` and an initial payment of ${formatCurrencyAmount(form.data.currency_code, paid)} will be recorded, leaving ${formatCurrencyAmount(form.data.currency_code, total - paid)} outstanding` : ' with no initial payment'}. It will still require submission and approval.`,
            confirmLabel: isEditing ? 'Update draft' : 'Save draft',
            onConfirm: () =>
                isEditing
                    ? form.put(`/expenses/${props.expense?.id}`)
                    : form.post('/expenses'),
        });
    }

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Expenses', href: '/expenses' },
        {
            title: isEditing ? 'Edit expense' : 'New expense',
            href: isEditing
                ? `/expenses/${props.expense?.id}/edit`
                : '/expenses/create',
        },
    ];
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isEditing ? 'Edit expense' : 'New expense'} />
            <form
                onSubmit={submit}
                className="flex flex-1 flex-col gap-5 p-4 md:p-6"
            >
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {isEditing ? 'Edit expense draft' : 'New expense'}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Add expense items to the cart, then record the payee
                            and settlement.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link
                            href={
                                isEditing
                                    ? `/expenses/${props.expense?.id}`
                                    : '/expenses'
                            }
                        >
                            <ArrowLeft />
                            Back
                        </Link>
                    </Button>
                </div>

                <div className="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_23rem] xl:items-start">
                    <div className="grid min-w-0 gap-5">
                        <Card>
                            <CardHeader>
                                <CardTitle>Expense cart</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Item</TableHead>
                                            <TableHead>Allocation</TableHead>
                                            <TableHead className="text-right">
                                                Quantity
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Unit amount
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Total
                                            </TableHead>
                                            <TableHead className="w-20" />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {form.data.lines.map((line, index) => {
                                            const item = itemFor(
                                                line.expense_item_id,
                                            );
                                            const project = props.projects.find(
                                                (row) =>
                                                    row.value ===
                                                    line.project_id,
                                            );
                                            return (
                                                <TableRow key={line.key}>
                                                    <TableCell>
                                                        <div className="font-medium">
                                                            {item?.name}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {item?.unit ??
                                                                'Each'}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell
                                                        className="max-w-52 truncate"
                                                        title={project?.label}
                                                    >
                                                        {project?.label ??
                                                            'Branch overhead'}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {formatNumber(
                                                            line.quantity,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {formatCurrencyAmount(
                                                            form.data
                                                                .currency_code,
                                                            line.unit_amount,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right font-medium tabular-nums">
                                                        {formatCurrencyAmount(
                                                            form.data
                                                                .currency_code,
                                                            Number(
                                                                line.quantity,
                                                            ) *
                                                                Number(
                                                                    line.unit_amount,
                                                                ),
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex justify-end">
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                title="Edit line"
                                                                onClick={() =>
                                                                    editLine(
                                                                        index,
                                                                    )
                                                                }
                                                            >
                                                                <Pencil />
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                title="Remove line"
                                                                onClick={() =>
                                                                    form.setData(
                                                                        'lines',
                                                                        form.data.lines.filter(
                                                                            (
                                                                                _,
                                                                                current,
                                                                            ) =>
                                                                                current !==
                                                                                index,
                                                                        ),
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 />
                                                            </Button>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                        {form.data.lines.length === 0 && (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={6}
                                                    className="h-24 text-center text-muted-foreground"
                                                >
                                                    Add an expense item from the
                                                    panel.
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                                <div className="mt-4 flex justify-end border-t pt-4">
                                    <div className="text-right">
                                        <div className="text-sm text-muted-foreground">
                                            Expense total
                                        </div>
                                        <div className="text-2xl font-semibold tabular-nums">
                                            {formatCurrencyAmount(
                                                form.data.currency_code,
                                                total,
                                            )}
                                        </div>
                                    </div>
                                </div>
                                <InputError
                                    message={
                                        (form.errors as Record<string, string>)
                                            .lines
                                    }
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Expense details</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-5">
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    <Field
                                        label="Branch"
                                        required
                                        error={form.errors.branch_id}
                                    >
                                        <SearchableSelect
                                            value={form.data.branch_id}
                                            onValueChange={changeBranch}
                                            options={props.branches}
                                            placeholder="Select branch"
                                            disabled={!props.canChangeBranch}
                                        />
                                    </Field>
                                    <Field
                                        label="Expense date"
                                        required
                                        error={form.errors.expense_date}
                                    >
                                        <Input
                                            type="date"
                                            max={new Date()
                                                .toISOString()
                                                .slice(0, 10)}
                                            value={form.data.expense_date}
                                            onChange={(event) =>
                                                form.setData(
                                                    'expense_date',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Currency"
                                        required
                                        error={form.errors.currency_code}
                                    >
                                        <SearchableSelect
                                            value={form.data.currency_code}
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'currency_code',
                                                    value,
                                                )
                                            }
                                            options={props.currencies}
                                            disabled={
                                                props.currencies.length === 1
                                            }
                                        />
                                    </Field>
                                </div>
                                <div className="grid gap-4 md:grid-cols-3">
                                    <Field
                                        label="Payee type"
                                        required
                                        error={form.errors.payee_type}
                                    >
                                        <SearchableSelect
                                            value={form.data.payee_type}
                                            onValueChange={(value) =>
                                                form.setData((data) => ({
                                                    ...data,
                                                    payee_type: value,
                                                    customer_id: '',
                                                    staff_id: '',
                                                    payee_name: '',
                                                }))
                                            }
                                            options={props.payeeTypes}
                                        />
                                    </Field>
                                    <div className="md:col-span-2">
                                        {form.data.payee_type === 'company' && (
                                            <Field
                                                label="Company"
                                                required
                                                error={form.errors.customer_id}
                                            >
                                                <SearchableSelect
                                                    value={
                                                        form.data.customer_id
                                                    }
                                                    onValueChange={(value) =>
                                                        form.setData(
                                                            'customer_id',
                                                            value,
                                                        )
                                                    }
                                                    options={availableCompanies}
                                                    placeholder="Select company"
                                                    searchPlaceholder="Search companies..."
                                                />
                                            </Field>
                                        )}
                                        {form.data.payee_type === 'staff' && (
                                            <Field
                                                label="Staff member"
                                                required
                                                error={form.errors.staff_id}
                                            >
                                                <SearchableSelect
                                                    value={form.data.staff_id}
                                                    onValueChange={(value) =>
                                                        form.setData(
                                                            'staff_id',
                                                            value,
                                                        )
                                                    }
                                                    options={availableStaff}
                                                    placeholder="Select staff member"
                                                />
                                            </Field>
                                        )}
                                        {form.data.payee_type === 'other' && (
                                            <Field
                                                label="Payee name"
                                                required
                                                error={form.errors.payee_name}
                                            >
                                                <Input
                                                    value={form.data.payee_name}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'payee_name',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        )}
                                    </div>
                                </div>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field
                                        label="Invoice or transaction reference"
                                        error={form.errors.reference}
                                    >
                                        <Input
                                            value={form.data.reference}
                                            onChange={(event) =>
                                                form.setData(
                                                    'reference',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Description"
                                        error={form.errors.description}
                                    >
                                        <Input
                                            value={form.data.description}
                                            onChange={(event) =>
                                                form.setData(
                                                    'description',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                </div>
                            </CardContent>
                        </Card>

                        {!isEditing && props.canRecordPayment && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Initial payment</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 md:grid-cols-3">
                                    <Field
                                        label="Amount paid now"
                                        error={
                                            form.errors.initial_payment_amount
                                        }
                                    >
                                        <Input
                                            type="number"
                                            min="0"
                                            max={total || undefined}
                                            step="0.01"
                                            value={
                                                form.data.initial_payment_amount
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'initial_payment_amount',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="0"
                                        />
                                    </Field>
                                    {Number(form.data.initial_payment_amount) >
                                        0 && (
                                        <>
                                            <Field
                                                label="Payment method"
                                                required
                                                error={
                                                    form.errors
                                                        .initial_payment_method
                                                }
                                            >
                                                <SearchableSelect
                                                    value={
                                                        form.data
                                                            .initial_payment_method
                                                    }
                                                    onValueChange={(value) =>
                                                        form.setData(
                                                            'initial_payment_method',
                                                            value,
                                                        )
                                                    }
                                                    options={
                                                        props.paymentMethods
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                label="Payment reference"
                                                error={
                                                    form.errors
                                                        .initial_payment_reference
                                                }
                                            >
                                                <Input
                                                    value={
                                                        form.data
                                                            .initial_payment_reference
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'initial_payment_reference',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <Card className="order-first xl:sticky xl:top-4 xl:order-none">
                        <CardHeader>
                            <CardTitle>
                                {editingIndex === null
                                    ? 'Add expense item'
                                    : 'Edit cart line'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <Field label="Expense type" required>
                                <SearchableSelect
                                    value={categoryId}
                                    onValueChange={(value) => {
                                        setCategoryId(value);
                                        setDraft((line) => ({
                                            ...line,
                                            expense_item_id: '',
                                        }));
                                    }}
                                    options={props.categories
                                        .filter((row) => row.is_active)
                                        .map((row) => ({
                                            value: row.id,
                                            label: row.name,
                                        }))}
                                    placeholder="Select category"
                                />
                            </Field>
                            <Field label="Expense item" required>
                                <SearchableSelect
                                    value={draft.expense_item_id}
                                    onValueChange={selectItem}
                                    options={availableItems.map((item) => ({
                                        value: item.id,
                                        label: item.name,
                                        description: item.code,
                                    }))}
                                    placeholder="Select expense item"
                                    searchPlaceholder="Search expense items..."
                                />
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-2">
                                <Field
                                    label={`Quantity${itemFor(draft.expense_item_id)?.unit ? ` (${itemFor(draft.expense_item_id)?.unit})` : ''}`}
                                    required
                                >
                                    <Input
                                        type="number"
                                        min="0.0001"
                                        step="any"
                                        value={draft.quantity}
                                        onChange={(event) =>
                                            setDraft((line) => ({
                                                ...line,
                                                quantity: event.target.value,
                                            }))
                                        }
                                    />
                                </Field>
                                <Field label="Unit amount" required>
                                    <Input
                                        type="number"
                                        min="0.01"
                                        step="any"
                                        value={draft.unit_amount}
                                        onChange={(event) =>
                                            setDraft((line) => ({
                                                ...line,
                                                unit_amount: event.target.value,
                                            }))
                                        }
                                    />
                                </Field>
                            </div>
                            <Field label="Project allocation">
                                <SearchableSelect
                                    value={draft.project_id}
                                    onValueChange={(value) =>
                                        setDraft((line) => ({
                                            ...line,
                                            project_id: value,
                                            site_id: '',
                                            project_activity_id: '',
                                        }))
                                    }
                                    options={[
                                        { value: '', label: 'Branch overhead' },
                                        ...availableProjects,
                                    ]}
                                    placeholder="Select project"
                                />
                            </Field>
                            {draft.project_id && (
                                <>
                                    <Field label="Site">
                                        <SearchableSelect
                                            value={draft.site_id}
                                            onValueChange={(value) =>
                                                setDraft((line) => ({
                                                    ...line,
                                                    site_id: value,
                                                    project_activity_id: '',
                                                }))
                                            }
                                            options={[
                                                {
                                                    value: '',
                                                    label: 'Project-wide',
                                                },
                                                ...availableSites,
                                            ]}
                                        />
                                    </Field>
                                    <Field label="Work item">
                                        <SearchableSelect
                                            value={draft.project_activity_id}
                                            onValueChange={(value) =>
                                                setDraft((line) => ({
                                                    ...line,
                                                    project_activity_id: value,
                                                }))
                                            }
                                            options={[
                                                {
                                                    value: '',
                                                    label: 'No specific Work item',
                                                },
                                                ...availableWorkItems,
                                            ]}
                                        />
                                    </Field>
                                </>
                            )}
                            <Field label="Narration">
                                <Textarea
                                    value={draft.description}
                                    onChange={(event) =>
                                        setDraft((line) => ({
                                            ...line,
                                            description: event.target.value,
                                        }))
                                    }
                                />
                            </Field>
                            <Button
                                type="button"
                                onClick={saveLine}
                                disabled={
                                    !draft.expense_item_id ||
                                    Number(draft.quantity) <= 0 ||
                                    Number(draft.unit_amount) <= 0
                                }
                            >
                                <Plus />
                                {editingIndex === null
                                    ? 'Add to cart'
                                    : 'Update line'}
                            </Button>
                            {editingIndex !== null && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setDraft(blankLine());
                                        setCategoryId('');
                                        setEditingIndex(null);
                                    }}
                                >
                                    Cancel edit
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                </div>
                <div className="flex justify-end">
                    <Button
                        type="submit"
                        size="lg"
                        disabled={
                            form.processing || form.data.lines.length === 0
                        }
                    >
                        {isEditing ? 'Update expense' : 'Save expense'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}

function Field({
    label,
    error,
    required,
    children,
}: {
    label: string;
    error?: string;
    required?: boolean;
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
