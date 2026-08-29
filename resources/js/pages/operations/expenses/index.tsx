import { Head, Link, router } from '@inertiajs/react';
import { Download, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import { formatCurrencyAmount, formatDate, formatDateTime } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import {
    ExpenseCategoryDialog,
    ExpenseItemDialog,
} from './partials/expense-reference-dialogs';
import type {
    ExpenseCategory,
    ExpenseItem,
    ExpenseRow,
    Option,
    PaymentRow,
} from './types';

type Props = {
    expenses: ExpenseRow[];
    payments: PaymentRow[];
    categories: ExpenseCategory[];
    expenseItems: ExpenseItem[];
    units: Option[];
    can: {
        create: boolean;
        manageCategories: boolean;
        manageItems: boolean;
        viewCosts: boolean;
        viewPayments: boolean;
        export: boolean;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Expenses', href: '/expenses' },
];

export default function ExpenseIndex(props: Props) {
    const confirm = useConfirmDialog();
    const initialTab =
        typeof window === 'undefined'
            ? 'expenses'
            : (new URLSearchParams(window.location.search).get('tab') ??
              'expenses');
    const [tab, setTab] = useState(initialTab);
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');
    const [recordState, setRecordState] = useState<'active' | 'inactive'>(
        'active',
    );
    const term = useDebouncedValue(search).trim().toLowerCase();
    const expenses = useMemo(
        () =>
            props.expenses.filter(
                (row) =>
                    (status === 'all' || row.status === status) &&
                    [
                        row.expense_number,
                        row.payee,
                        row.reference ?? '',
                        row.projects,
                    ].some((value) => value.toLowerCase().includes(term)),
            ),
        [props.expenses, status, term],
    );
    const categories = props.categories.filter(
        (row) =>
            row.is_active === (recordState === 'active') &&
            `${row.name} ${row.code}`
                .toLowerCase()
                .includes(term),
    );
    const items = props.expenseItems.filter(
        (row) =>
            row.is_active === (recordState === 'active') &&
            `${row.name} ${row.code}`.toLowerCase().includes(term),
    );
    const payments = props.payments.filter((row) =>
        `${row.payment_number} ${row.expense_number} ${row.reference ?? ''}`
            .toLowerCase()
            .includes(term),
    );

    function changeTab(value: string) {
        setTab(value);
        const url = new URL(window.location.href);
        url.searchParams.set('tab', value);
        window.history.replaceState({}, '', url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Expenses" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Expenses</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Record, approve and settle non-stock operational costs.
                    </p>
                </div>

                <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div className="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row">
                        <div className="relative min-w-0 flex-1 sm:max-w-sm">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                className="pl-9"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={`Search ${tab.replace('-', ' ')}...`}
                            />
                        </div>
                        {tab === 'expenses' && (
                            <div className="w-full sm:w-56">
                                <SearchableSelect
                                    value={status}
                                    onValueChange={setStatus}
                                    options={[
                                        { value: 'all', label: 'All statuses' },
                                        { value: 'draft', label: 'Draft' },
                                        {
                                            value: 'submitted',
                                            label: 'Submitted',
                                        },
                                        {
                                            value: 'approved',
                                            label: 'Approved',
                                        },
                                        {
                                            value: 'rejected',
                                            label: 'Rejected',
                                        },
                                        {
                                            value: 'cancelled',
                                            label: 'Cancelled',
                                        },
                                    ]}
                                />
                            </div>
                        )}
                    </div>
                    <div className="flex justify-end gap-2">
                        {tab === 'expenses' && props.can.create && (
                            <Button asChild>
                                <Link href="/expenses/create">
                                    <Plus />
                                    New expense
                                </Link>
                            </Button>
                        )}
                        {tab === 'categories' && props.can.manageCategories && (
                            <ExpenseCategoryDialog />
                        )}
                        {tab === 'items' && props.can.manageItems && (
                            <ExpenseItemDialog
                                categories={props.categories}
                                units={props.units}
                            />
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Tabs value={tab} onValueChange={changeTab}>
                        <TabsList className="h-auto flex-wrap justify-start">
                            <TabsTrigger value="expenses">Expenses</TabsTrigger>
                            {props.can.viewPayments && (
                                <TabsTrigger value="payments">
                                    Payments
                                </TabsTrigger>
                            )}
                            <TabsTrigger value="categories">
                                Expense types
                            </TabsTrigger>
                            <TabsTrigger value="items">
                                Expense items
                            </TabsTrigger>
                            <TabsTrigger value="reports">Reports</TabsTrigger>
                        </TabsList>
                    </Tabs>
                    {(tab === 'categories' || tab === 'items') && (
                        <Tabs
                            value={recordState}
                            onValueChange={(value) =>
                                setRecordState(value as 'active' | 'inactive')
                            }
                        >
                            <TabsList>
                                <TabsTrigger value="active">Active</TabsTrigger>
                                <TabsTrigger value="inactive">
                                    Inactive
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>
                    )}
                </div>

                <Card>
                    <CardContent className="pt-6">
                        {tab === 'expenses' && (
                            <ExpenseTable
                                rows={expenses}
                                canViewCosts={props.can.viewCosts}
                            />
                        )}
                        {tab === 'payments' && (
                            <PaymentTable
                                rows={payments}
                                canViewCosts={props.can.viewCosts}
                            />
                        )}
                        {tab === 'categories' && (
                            <CategoryTable
                                rows={categories}
                                props={props}
                                confirm={confirm}
                            />
                        )}
                        {tab === 'items' && (
                            <ItemTable
                                rows={items}
                                props={props}
                                confirm={confirm}
                            />
                        )}
                        {tab === 'reports' && (
                            <Reports
                                expenses={props.expenses}
                                canViewCosts={props.can.viewCosts}
                                canExport={props.can.export}
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function ExpenseTable({
    rows,
    canViewCosts,
}: {
    rows: ExpenseRow[];
    canViewCosts: boolean;
}) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Expense</TableHead>
                    <TableHead>Payee</TableHead>
                    {canViewCosts && (
                        <>
                            <TableHead className="text-right">Total</TableHead>
                            <TableHead className="text-right">Paid</TableHead>
                            <TableHead className="text-right">
                                Balance
                            </TableHead>
                        </>
                    )}
                    <TableHead>Status</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={row.id}>
                        <TableCell>
                            <Link
                                href={`/expenses/${row.id}`}
                                className="font-medium text-primary hover:underline"
                            >
                                {row.expense_number}
                            </Link>
                            <div className="text-xs text-muted-foreground">
                                {formatDate(row.expense_date)}
                            </div>
                        </TableCell>
                        <TableCell>
                            <div
                                className="max-w-56 truncate font-medium"
                                title={row.payee}
                            >
                                {row.payee}
                            </div>
                            <div
                                className="max-w-56 truncate text-xs text-muted-foreground"
                                title={row.projects || 'Branch overhead'}
                            >
                                {row.projects || 'Branch overhead'}
                            </div>
                        </TableCell>
                        {canViewCosts && (
                            <>
                                <TableCell className="text-right tabular-nums">
                                    {formatCurrencyAmount(
                                        row.currency_code,
                                        row.total_amount,
                                    )}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {formatCurrencyAmount(
                                        row.currency_code,
                                        row.paid_amount,
                                    )}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {formatCurrencyAmount(
                                        row.currency_code,
                                        row.balance,
                                    )}
                                </TableCell>
                            </>
                        )}
                        <TableCell>
                            <div className="flex flex-wrap gap-1">
                                <StatusBadge
                                    value={row.status}
                                    label={row.status_label}
                                />
                                <Badge variant="outline">
                                    {label(row.payment_status)}
                                </Badge>
                            </div>
                        </TableCell>
                    </TableRow>
                ))}
                {rows.length === 0 && <Empty colSpan={canViewCosts ? 6 : 3} />}
            </TableBody>
        </Table>
    );
}
function PaymentTable({
    rows,
    canViewCosts,
}: {
    rows: PaymentRow[];
    canViewCosts: boolean;
}) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Payment</TableHead>
                    <TableHead>Expense</TableHead>
                    <TableHead>Recorded</TableHead>
                    <TableHead>Method</TableHead>
                    <TableHead>Reference</TableHead>
                    {canViewCosts && (
                        <TableHead className="text-right">Amount</TableHead>
                    )}
                    <TableHead>Status</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={row.id}>
                        <TableCell className="font-medium">
                            {row.payment_number}
                        </TableCell>
                        <TableCell>
                            <Link
                                href={`/expenses/${row.expense_id}`}
                                className="text-primary hover:underline"
                            >
                                {row.expense_number}
                            </Link>
                        </TableCell>
                        <TableCell>{formatDateTime(row.paid_at)}</TableCell>
                        <TableCell>{row.payment_method}</TableCell>
                        <TableCell>{row.reference ?? '—'}</TableCell>
                        {canViewCosts && (
                            <TableCell className="text-right tabular-nums">
                                {formatCurrencyAmount(
                                    row.currency_code,
                                    row.amount,
                                )}
                            </TableCell>
                        )}
                        <TableCell>
                            <StatusBadge
                                value={row.status}
                                label={label(row.status)}
                            />
                        </TableCell>
                    </TableRow>
                ))}
                {rows.length === 0 && <Empty colSpan={canViewCosts ? 7 : 6} />}
            </TableBody>
        </Table>
    );
}
function CategoryTable({
    rows,
    props,
    confirm,
}: {
    rows: ExpenseCategory[];
    props: Props;
    confirm: ReturnType<typeof useConfirmDialog>;
}) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Expense type</TableHead>
                    <TableHead>Evidence</TableHead>
                    {props.can.manageCategories && (
                        <TableHead className="text-right">Actions</TableHead>
                    )}
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={row.id}>
                        <TableCell>
                            <div className="font-medium">{row.name}</div>
                            <div className="text-xs text-muted-foreground">
                                {row.code}
                            </div>
                        </TableCell>
                        <TableCell>
                            {row.requires_evidence ? 'Required' : 'Optional'}
                        </TableCell>
                        {props.can.manageCategories && (
                            <TableCell>
                                <div className="flex justify-end gap-2">
                                    <ExpenseCategoryDialog category={row} />
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            confirm({
                                                title: row.is_active
                                                    ? 'Deactivate expense type?'
                                                    : 'Restore expense type?',
                                                description: row.is_active
                                                    ? 'It will remain on historical expenses but cannot be selected for new items.'
                                                    : 'It will become available again.',
                                                confirmLabel: row.is_active
                                                    ? 'Deactivate'
                                                    : 'Restore',
                                                onConfirm: () =>
                                                    router.delete(
                                                        `/expense-categories/${row.id}`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    ),
                                            })
                                        }
                                    >
                                        {row.is_active
                                            ? 'Deactivate'
                                            : 'Restore'}
                                    </Button>
                                    {!row.is_active && (
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() =>
                                                confirm({
                                                    title: 'Permanently delete expense type?',
                                                    description:
                                                        'This only succeeds when no expense items depend on the expense type.',
                                                    confirmLabel:
                                                        'Delete permanently',
                                                    variant: 'destructive',
                                                    onConfirm: () =>
                                                        router.delete(
                                                            `/expense-categories/${row.id}/permanently`,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        ),
                                                })
                                            }
                                        >
                                            Delete
                                        </Button>
                                    )}
                                </div>
                            </TableCell>
                        )}
                    </TableRow>
                ))}
                {rows.length === 0 && (
                    <Empty colSpan={props.can.manageCategories ? 3 : 2} />
                )}
            </TableBody>
        </Table>
    );
}
function ItemTable({
    rows,
    props,
    confirm,
}: {
    rows: ExpenseItem[];
    props: Props;
    confirm: ReturnType<typeof useConfirmDialog>;
}) {
    const categoryName = (id: string) =>
        props.categories.find((row) => row.id === id)?.name ?? 'Unknown';
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Expense item</TableHead>
                    <TableHead>Expense type</TableHead>
                    <TableHead>Default unit</TableHead>
                    <TableHead>Evidence</TableHead>
                    {props.can.manageItems && (
                        <TableHead className="text-right">Actions</TableHead>
                    )}
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={row.id}>
                        <TableCell>
                            <div className="font-medium">{row.name}</div>
                            <div className="text-xs text-muted-foreground">
                                {row.code}
                            </div>
                        </TableCell>
                        <TableCell>
                            {categoryName(row.expense_category_id)}
                        </TableCell>
                        <TableCell>{row.unit ?? 'Not specified'}</TableCell>
                        <TableCell>
                            {row.requires_evidence ? 'Required' : 'Optional'}
                        </TableCell>
                        {props.can.manageItems && (
                            <TableCell>
                                <div className="flex justify-end gap-2">
                                    <ExpenseItemDialog
                                        item={row}
                                        categories={props.categories}
                                        units={props.units}
                                    />
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            confirm({
                                                title: row.is_active
                                                    ? 'Deactivate expense item?'
                                                    : 'Restore expense item?',
                                                description: row.is_active
                                                    ? 'It will remain visible on historical expenses.'
                                                    : 'It will become selectable again.',
                                                confirmLabel: row.is_active
                                                    ? 'Deactivate'
                                                    : 'Restore',
                                                onConfirm: () =>
                                                    router.delete(
                                                        `/expense-items/${row.id}`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    ),
                                            })
                                        }
                                    >
                                        {row.is_active
                                            ? 'Deactivate'
                                            : 'Restore'}
                                    </Button>
                                    {!row.is_active && (
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() =>
                                                confirm({
                                                    title: 'Permanently delete expense item?',
                                                    description:
                                                        'This only succeeds when the item has never been used on an expense.',
                                                    confirmLabel:
                                                        'Delete permanently',
                                                    variant: 'destructive',
                                                    onConfirm: () =>
                                                        router.delete(
                                                            `/expense-items/${row.id}/permanently`,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        ),
                                                })
                                            }
                                        >
                                            Delete
                                        </Button>
                                    )}
                                </div>
                            </TableCell>
                        )}
                    </TableRow>
                ))}
                {rows.length === 0 && <Empty colSpan={5} />}
            </TableBody>
        </Table>
    );
}
function Reports({
    expenses,
    canViewCosts,
    canExport,
}: {
    expenses: ExpenseRow[];
    canViewCosts: boolean;
    canExport: boolean;
}) {
    const approved = expenses.filter((row) => row.status === 'approved');
    const outstanding = approved.filter((row) => Number(row.balance ?? 0) > 0);
    const totals = approved.reduce<Record<string, number>>(
        (amounts, row) => ({
            ...amounts,
            [row.currency_code]:
                (amounts[row.currency_code] ?? 0) +
                Number(row.total_amount ?? 0),
        }),
        {},
    );
    const approvedCost =
        Object.entries(totals)
            .map(([currency, amount]) => formatCurrencyAmount(currency, amount))
            .join(' / ') || 'No approved cost';
    return (
        <div className="grid gap-5">
            <div className="flex flex-wrap justify-end gap-2">
                {canExport && (
                    <>
                        <Button asChild variant="outline">
                            <a href="/expenses-export.csv">
                                <Download />
                                CSV
                            </a>
                        </Button>
                        <Button asChild>
                            <a href="/expenses-export.pdf">
                                <Download />
                                PDF
                            </a>
                        </Button>
                    </>
                )}
            </div>
            <div className="grid gap-4 md:grid-cols-3">
                <Metric
                    label="Approved expenses"
                    value={String(approved.length)}
                />
                <Metric
                    label="Outstanding expenses"
                    value={String(outstanding.length)}
                />
                <Metric
                    label="Approved cost"
                    value={canViewCosts ? approvedCost : 'Restricted'}
                />
            </div>
        </div>
    );
}
function Metric({ label: title, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border p-4">
            <div className="text-sm text-muted-foreground">{title}</div>
            <div className="mt-2 text-2xl font-semibold tabular-nums">
                {value}
            </div>
        </div>
    );
}
function StatusBadge({ value, label: text }: { value: string; label: string }) {
    return (
        <Badge
            variant={
                value === 'approved' || value === 'recorded'
                    ? 'default'
                    : value === 'rejected' ||
                        value === 'cancelled' ||
                        value === 'reversed'
                      ? 'destructive'
                      : 'secondary'
            }
        >
            {text}
        </Badge>
    );
}
function Empty({ colSpan }: { colSpan: number }) {
    return (
        <TableRow>
            <TableCell
                colSpan={colSpan}
                className="h-24 text-center text-muted-foreground"
            >
                No records found.
            </TableCell>
        </TableRow>
    );
}
function label(value: string) {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
