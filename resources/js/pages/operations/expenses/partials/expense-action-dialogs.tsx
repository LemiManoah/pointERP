import { router, useForm } from '@inertiajs/react';
import { CreditCard, RotateCcw, XCircle } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
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
import { Textarea } from '@/components/ui/textarea';
import { formatCurrencyAmount } from '@/lib/utils';
import type { Option } from '../types';

export function DecisionDialog({
    expenseId,
    action,
    label,
    description,
}: {
    expenseId: string;
    action: 'reject' | 'cancel';
    label: string;
    description: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/expenses/${expenseId}/${action}`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <XCircle />
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{label} expense?</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label required>Reason</Label>
                        <Textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Keep expense
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            {label}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function PaymentDialog({
    expenseId,
    balance,
    currencyCode,
    methods,
}: {
    expenseId: string;
    balance: string;
    currencyCode: string;
    methods: Option[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        paid_at: new Date().toISOString().slice(0, 16),
        amount: balance,
        payment_method: '',
        reference: '',
        notes: '',
    });
    const resulting = Math.max(
        Number(balance) - Number(form.data.amount || 0),
        0,
    );
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/expenses/${expenseId}/payments`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <CreditCard />
                    Record payment
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Record expense payment</DialogTitle>
                    <DialogDescription>
                        The payment is permanent. Incorrect records must be
                        reversed with a reason.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Paid at"
                            required
                            error={form.errors.paid_at}
                        >
                            <Input
                                type="datetime-local"
                                value={form.data.paid_at}
                                onChange={(event) =>
                                    form.setData('paid_at', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Amount"
                            required
                            error={form.errors.amount}
                        >
                            <Input
                                type="number"
                                min="0.01"
                                max={balance}
                                step="0.01"
                                value={form.data.amount}
                                onChange={(event) =>
                                    form.setData('amount', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Payment method"
                            required
                            error={form.errors.payment_method}
                        >
                            <SearchableSelect
                                value={form.data.payment_method}
                                onValueChange={(value) =>
                                    form.setData('payment_method', value)
                                }
                                options={methods}
                            />
                        </Field>
                        <Field label="Reference" error={form.errors.reference}>
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
                    </div>
                    <Field label="Notes" error={form.errors.notes}>
                        <Textarea
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                        />
                    </Field>
                    <div className="grid grid-cols-1 gap-3 rounded-md border bg-muted/30 p-4 text-sm sm:grid-cols-3">
                        <Summary
                            label="Current balance"
                            value={formatCurrencyAmount(currencyCode, balance)}
                        />
                        <Summary
                            label="This payment"
                            value={formatCurrencyAmount(
                                currencyCode,
                                form.data.amount,
                            )}
                        />
                        <Summary
                            label="Resulting balance"
                            value={formatCurrencyAmount(
                                currencyCode,
                                resulting,
                            )}
                        />
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            Record payment
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function ReversePaymentButton({ paymentId }: { paymentId: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/expense-payments/${paymentId}/reverse`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <RotateCcw />
                    Reverse
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Reverse payment?</DialogTitle>
                    <DialogDescription>
                        The original payment remains in the audit history and
                        stops reducing the expense balance.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <Field label="Reason" required error={form.errors.reason}>
                        <Textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    </Field>
                    <div className="flex justify-end">
                        <Button type="submit" variant="destructive">
                            Reverse payment
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function approveExpense(expenseId: string) {
    router.post(`/expenses/${expenseId}/approve`);
}
export function submitExpense(expenseId: string) {
    router.post(`/expenses/${expenseId}/submit`);
}
function Field({
    label,
    error,
    required = false,
    children,
}: {
    label: string;
    error?: string;
    required?: boolean;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label required={required}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
function Summary({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-muted-foreground">{label}</div>
            <div className="mt-1 font-semibold tabular-nums">{value}</div>
        </div>
    );
}
