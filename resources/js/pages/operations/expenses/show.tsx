import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, Pencil, Send, Trash2 } from 'lucide-react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import {
    formatCurrencyAmount,
    formatDate,
    formatDateTime,
    formatNumber,
} from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import {
    DocumentDialog,
    type DocumentTypeOption,
    type LinkOptions,
    type Option as DocumentOption,
} from '../documents/partials/document-dialog';
import {
    DocumentEvidenceTable,
    type LinkedDocumentRow,
} from '../documents/partials/document-evidence-table';
import {
    DecisionDialog,
    PaymentDialog,
    ReversePaymentButton,
    approveExpense,
    submitExpense,
} from './partials/expense-action-dialogs';
import type { Option, PaymentRow } from './types';

type Line = {
    id: string;
    category: string;
    item: string;
    description: string | null;
    quantity: string;
    unit_amount: string | null;
    amount: string | null;
    project: string | null;
    site: string | null;
    work_item: string | null;
};
type ExpenseDetail = {
    id: string;
    expense_number: string;
    expense_date: string;
    payee: string;
    branch: string;
    branch_id: string;
    reference: string | null;
    status: string;
    status_label: string;
    currency_code: string;
    base_currency_code: string;
    exchange_rate: string | null;
    total_amount: string | null;
    paid_amount: string | null;
    balance: string | null;
    payment_status: string;
    description: string | null;
    decision_reason: string | null;
    daily_site_report: { id: string; reference: string } | null;
    lines: Line[];
    payments: PaymentRow[];
};
type Props = {
    expense: ExpenseDetail;
    documents: LinkedDocumentRow[];
    paymentMethods: Option[];
    documentTypes: DocumentTypeOption[];
    documentBranches: DocumentOption[];
    documentLinkOptions: LinkOptions;
    can: {
        update: boolean;
        submit: boolean;
        approve: boolean;
        reject: boolean;
        cancel: boolean;
        delete: boolean;
        recordPayment: boolean;
        reversePayments: boolean;
        viewCosts: boolean;
        uploadDocuments: boolean;
    };
};

export default function ExpenseShow({
    expense,
    documents,
    paymentMethods,
    documentTypes,
    documentBranches,
    documentLinkOptions,
    can,
}: Props) {
    const confirm = useConfirmDialog();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Expenses', href: '/expenses' },
        { title: expense.expense_number, href: `/expenses/${expense.id}` },
    ];
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={expense.expense_number} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold">
                                {expense.expense_number}
                            </h1>
                            <StatusBadge
                                value={expense.status}
                                label={expense.status_label}
                            />
                            <Badge variant="outline">
                                {label(expense.payment_status)}
                            </Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {expense.payee} · {expense.branch} ·{' '}
                            {formatDate(expense.expense_date)}
                        </p>
                        {expense.daily_site_report && (
                            <Link
                                href={`/daily-site-reports/${expense.daily_site_report.id}`}
                                className="mt-2 inline-flex text-sm font-medium text-primary hover:underline"
                            >
                                From {expense.daily_site_report.reference}
                            </Link>
                        )}
                    </div>
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button asChild variant="outline">
                            <Link href="/expenses">
                                <ArrowLeft />
                                Back
                            </Link>
                        </Button>
                        {can.update && (
                            <Button asChild variant="outline">
                                <Link href={`/expenses/${expense.id}/edit`}>
                                    <Pencil />
                                    Edit
                                </Link>
                            </Button>
                        )}
                        {can.submit && (
                            <Button
                                onClick={() =>
                                    confirm({
                                        title: 'Submit expense for approval?',
                                        description:
                                            'The draft will become read-only while it awaits a decision.',
                                        confirmLabel: 'Submit',
                                        onConfirm: () =>
                                            submitExpense(expense.id),
                                    })
                                }
                            >
                                <Send />
                                Submit
                            </Button>
                        )}
                        {can.approve && (
                            <Button
                                onClick={() =>
                                    confirm({
                                        title: 'Approve this expense?',
                                        description:
                                            'The expense becomes an accepted project or branch cost. Recorded payments remain separate settlement evidence.',
                                        confirmLabel: 'Approve',
                                        onConfirm: () =>
                                            approveExpense(expense.id),
                                    })
                                }
                            >
                                <Check />
                                Approve
                            </Button>
                        )}
                        {can.reject && (
                            <DecisionDialog
                                expenseId={expense.id}
                                action="reject"
                                label="Reject"
                                description="Return the expense for correction and record why."
                            />
                        )}
                        {can.cancel && (
                            <DecisionDialog
                                expenseId={expense.id}
                                action="cancel"
                                label="Cancel"
                                description="Cancel this expense and preserve its history."
                            />
                        )}
                        {can.delete && (
                            <Button
                                variant="destructive"
                                onClick={() =>
                                    confirm({
                                        title: 'Delete this draft?',
                                        description:
                                            'Only an unused draft can be permanently removed.',
                                        confirmLabel: 'Delete draft',
                                        variant: 'destructive',
                                        onConfirm: () =>
                                            router.delete(
                                                `/expenses/${expense.id}`,
                                            ),
                                    })
                                }
                            >
                                <Trash2 />
                                Delete
                            </Button>
                        )}
                    </div>
                </div>

                {expense.decision_reason && (
                    <div className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                        <span className="font-medium">Decision reason:</span>{' '}
                        {expense.decision_reason}
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Metric
                        label="Expense total"
                        value={
                            can.viewCosts
                                ? formatCurrencyAmount(
                                      expense.currency_code,
                                      expense.total_amount,
                                  )
                                : 'Restricted'
                        }
                    />
                    <Metric
                        label="Amount paid"
                        value={
                            can.viewCosts
                                ? formatCurrencyAmount(
                                      expense.currency_code,
                                      expense.paid_amount,
                                  )
                                : 'Restricted'
                        }
                    />
                    <Metric
                        label="Outstanding"
                        value={
                            can.viewCosts
                                ? formatCurrencyAmount(
                                      expense.currency_code,
                                      expense.balance,
                                  )
                                : 'Restricted'
                        }
                    />
                    <Metric
                        label="Reference"
                        value={expense.reference ?? 'Not provided'}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Expense items</CardTitle>
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
                                    {can.viewCosts && (
                                        <>
                                            <TableHead className="text-right">
                                                Unit amount
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Total
                                            </TableHead>
                                        </>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {expense.lines.map((line) => (
                                    <TableRow key={line.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {line.item}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {line.category}
                                                {line.description
                                                    ? ` · ${line.description}`
                                                    : ''}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div>
                                                {line.project ??
                                                    'Branch overhead'}
                                            </div>
                                            {line.site && (
                                                <div className="text-xs text-muted-foreground">
                                                    {line.site}
                                                    {line.work_item
                                                        ? ` · ${line.work_item}`
                                                        : ''}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatNumber(line.quantity)}
                                        </TableCell>
                                        {can.viewCosts && (
                                            <>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatCurrencyAmount(
                                                        expense.currency_code,
                                                        line.unit_amount,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right font-medium tabular-nums">
                                                    {formatCurrencyAmount(
                                                        expense.currency_code,
                                                        line.amount,
                                                    )}
                                                </TableCell>
                                            </>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {can.viewCosts && (
                    <Card>
                        <CardHeader>
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <CardTitle>Payments</CardTitle>
                                {can.recordPayment &&
                                    Number(expense.balance) > 0 && (
                                        <PaymentDialog
                                            expenseId={expense.id}
                                            balance={expense.balance ?? '0'}
                                            currencyCode={expense.currency_code}
                                            methods={paymentMethods}
                                        />
                                    )}
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Payment</TableHead>
                                        <TableHead>Recorded</TableHead>
                                        <TableHead>Method</TableHead>
                                        <TableHead>Reference</TableHead>
                                        <TableHead className="text-right">
                                            Amount
                                        </TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {expense.payments.map((payment) => (
                                        <TableRow key={payment.id}>
                                            <TableCell className="font-medium">
                                                {payment.payment_number}
                                            </TableCell>
                                            <TableCell>
                                                {formatDateTime(
                                                    payment.paid_at,
                                                )}
                                                <div className="text-xs text-muted-foreground">
                                                    {payment.recorded_by}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {payment.payment_method}
                                            </TableCell>
                                            <TableCell>
                                                {payment.reference ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatCurrencyAmount(
                                                    payment.currency_code,
                                                    payment.amount,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    value={payment.status}
                                                    label={label(
                                                        payment.status,
                                                    )}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {can.reversePayments &&
                                                    payment.status ===
                                                        'recorded' && (
                                                        <ReversePaymentButton
                                                            paymentId={
                                                                payment.id
                                                            }
                                                        />
                                                    )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {expense.payments.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
                                                className="h-20 text-center text-muted-foreground"
                                            >
                                                No payments recorded.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                <DocumentEvidenceTable
                    documents={documents}
                    emptyText="No receipts or supporting documents have been attached."
                    title="Receipts and evidence"
                    actions={
                        can.uploadDocuments ? (
                            <DocumentDialog
                                documentTypes={documentTypes}
                                branches={documentBranches}
                                linkOptions={documentLinkOptions}
                                defaultLink={{
                                    type: 'expense',
                                    id: expense.id,
                                }}
                                defaultBranchId={expense.branch_id}
                                buttonLabel="Add evidence"
                            />
                        ) : undefined
                    }
                />
            </div>
        </AppLayout>
    );
}

function Metric({ label: text, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0 rounded-md border bg-card p-4">
            <div className="text-sm text-muted-foreground">{text}</div>
            <div className="mt-2 truncate text-xl font-semibold" title={value}>
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
function label(value: string) {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
