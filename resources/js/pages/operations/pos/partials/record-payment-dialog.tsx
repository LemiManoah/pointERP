import { useForm } from '@inertiajs/react';
import { Banknote } from 'lucide-react';
import type { FormEvent } from 'react';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrencyAmount } from '@/lib/utils';

type Option = { value: string; label: string };

export function RecordPaymentDialog({
    saleId,
    currencyCode,
    balanceDue,
    paymentMethods,
}: {
    saleId: string;
    currencyCode: string;
    balanceDue: string;
    paymentMethods: Option[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        method: 'cash',
        amount: balanceDue,
        reference: '',
        notes: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/pos/${saleId}/payments`, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    onClick={() => {
                        form.setData({
                            method: 'cash',
                            amount: balanceDue,
                            reference: '',
                            notes: '',
                        });
                        form.clearErrors();
                    }}
                >
                    <Banknote />
                    Record payment
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Record customer payment</DialogTitle>
                        <DialogDescription>
                            Outstanding balance:{' '}
                            {formatCurrencyAmount(currencyCode, balanceDue)}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-5">
                        <div className="grid gap-1.5">
                            <Label>
                                Payment method{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SearchableSelect
                                value={form.data.method}
                                onValueChange={(value) =>
                                    form.setData('method', value)
                                }
                                options={paymentMethods}
                            />
                            <InputError message={form.errors.method} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="payment-amount">
                                Amount{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="payment-amount"
                                type="number"
                                min="0.01"
                                max={balanceDue}
                                step="0.01"
                                value={form.data.amount}
                                onChange={(event) =>
                                    form.setData('amount', event.target.value)
                                }
                            />
                            <InputError message={form.errors.amount} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="payment-reference">
                                Reference
                                {form.data.method !== 'cash' && (
                                    <span className="text-destructive"> *</span>
                                )}
                            </Label>
                            <Input
                                id="payment-reference"
                                value={form.data.reference}
                                onChange={(event) =>
                                    form.setData(
                                        'reference',
                                        event.target.value,
                                    )
                                }
                                placeholder={
                                    form.data.method === 'cash'
                                        ? 'Optional'
                                        : 'Required'
                                }
                            />
                            <InputError message={form.errors.reference} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="payment-notes">Notes</Label>
                            <Textarea
                                id="payment-notes"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                            <InputError message={form.errors.notes} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Record payment
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
