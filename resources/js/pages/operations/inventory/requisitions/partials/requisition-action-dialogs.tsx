import { router, useForm } from '@inertiajs/react';
import {
    ArrowDownToLine,
    CornerUpLeft,
    ShieldCheck,
    XCircle,
} from 'lucide-react';
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
import { Textarea } from '@/components/ui/textarea';
import { formatNumber } from '@/lib/utils';
import { createUuid } from '@/lib/uuid';

type Line = {
    id: string;
    inventory_item_id: string | null;
    item_name: string;
    tracking_type: string | null;
    unit_name: string;
    stock_unit_name: string | null;
    stock_quantity: string;
    approved_quantity: string;
    issued_quantity: string;
    returned_quantity: string;
    outstanding_quantity: string;
    outstanding_request_unit_quantity: string;
    available_stock: string | null;
};
type Batch = {
    id: string;
    inventory_item_id: string;
    batch_number: string;
    expires_on: string | null;
    available_quantity: string;
};

export function ReviewRequisitionDialog({
    requisitionId,
    lines,
}: {
    requisitionId: string;
    lines: Line[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        decision: 'approve',
        reason: '',
        lines: lines.map((line) => ({
            id: line.id,
            approved_quantity: String(
                Math.min(
                    Number(line.stock_quantity),
                    Number(line.available_stock ?? 0),
                ),
            ),
        })),
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/inventory/requisitions/${requisitionId}/review`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <ShieldCheck /> Review
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Review material requisition</DialogTitle>
                    <DialogDescription>
                        Approve quantities or return/reject the request with a
                        reason.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid grid-cols-3 gap-2">
                        {['approve', 'return', 'reject'].map((decision) => (
                            <Button
                                key={decision}
                                type="button"
                                variant={
                                    form.data.decision === decision
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() =>
                                    form.setData('decision', decision)
                                }
                            >
                                {decision === 'return'
                                    ? 'Return for revision'
                                    : title(decision)}
                            </Button>
                        ))}
                    </div>
                    {form.data.decision === 'approve' && (
                        <div className="grid gap-3">
                            <p className="text-sm font-medium">
                                Approved quantities in stock units
                            </p>
                            {lines.map((line, index) => (
                                <div
                                    key={line.id}
                                    className="grid items-end gap-3 sm:grid-cols-[1fr_12rem]"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {line.item_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Requested{' '}
                                            {formatNumber(line.stock_quantity)}{' '}
                                            {line.stock_unit_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatNumber(
                                                line.available_stock ?? 0,
                                            )}{' '}
                                            {line.stock_unit_name} available
                                        </p>
                                    </div>
                                    <Input
                                        type="number"
                                        min="0"
                                        max={Math.min(
                                            Number(line.stock_quantity),
                                            Number(line.available_stock ?? 0),
                                        )}
                                        step="0.0001"
                                        value={
                                            form.data.lines[index]
                                                .approved_quantity
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'lines',
                                                form.data.lines.map(
                                                    (row, current) =>
                                                        current === index
                                                            ? {
                                                                  ...row,
                                                                  approved_quantity:
                                                                      event
                                                                          .target
                                                                          .value,
                                                              }
                                                            : row,
                                                ),
                                            )
                                        }
                                    />
                                </div>
                            ))}
                            <InputError message={form.errors.lines} />
                        </div>
                    )}
                    <Field
                        label={
                            form.data.decision === 'approve'
                                ? 'Approval note'
                                : 'Decision reason'
                        }
                        required={form.data.decision !== 'approve'}
                        error={form.errors.reason}
                    >
                        <Textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    </Field>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            {form.data.decision === 'approve'
                                ? 'Approve requisition'
                                : form.data.decision === 'return'
                                  ? 'Return for revision'
                                  : 'Reject requisition'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function IssueLineDialog({
    requisitionId,
    line,
    batches,
}: {
    requisitionId: string;
    line: Line;
    batches: Batch[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        quantity: line.outstanding_request_unit_quantity,
        inventory_batch_id: '',
        reason: `Issue for requisition ${requisitionId}`,
        source_key: createUuid(),
    });
    const itemBatches = batches.filter(
        (batch) =>
            batch.inventory_item_id === line.inventory_item_id &&
            Number(batch.available_quantity) > 0,
    );
    const selectedBatch = itemBatches.find(
        (batch) => batch.id === form.data.inventory_batch_id,
    );
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(
            `/inventory/requisitions/${requisitionId}/lines/${line.id}/issue`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    form.setData('source_key', createUuid());
                },
            },
        );
    };
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    disabled={
                        Number(line.outstanding_quantity) <= 0 ||
                        line.inventory_item_id === null
                    }
                >
                    <ArrowDownToLine /> Issue
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Issue {line.item_name}</DialogTitle>
                    <DialogDescription>
                        Outstanding:{' '}
                        {formatNumber(line.outstanding_request_unit_quantity)}{' '}
                        {line.unit_name} (
                        {formatNumber(line.outstanding_quantity)}{' '}
                        {line.stock_unit_name}).
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <Field
                        label={`Quantity (${line.unit_name})`}
                        required
                        error={form.errors.quantity}
                    >
                        <Input
                            type="number"
                            min="0.0001"
                            step="0.0001"
                            max={Math.min(
                                Number(line.outstanding_request_unit_quantity),
                                Number(
                                    selectedBatch?.available_quantity ??
                                        line.outstanding_request_unit_quantity,
                                ),
                            )}
                            value={form.data.quantity}
                            onChange={(event) =>
                                form.setData('quantity', event.target.value)
                            }
                        />
                    </Field>
                    {line.tracking_type === 'batch' && (
                        <Field
                            label="Batch"
                            required
                            error={form.errors.inventory_batch_id}
                        >
                            <SearchableSelect
                                value={form.data.inventory_batch_id}
                                options={itemBatches.map((batch) => ({
                                    value: batch.id,
                                    label: batch.batch_number,
                                    description: batch.expires_on
                                        ? `${formatNumber(batch.available_quantity)} available - expires ${batch.expires_on}`
                                        : `${formatNumber(batch.available_quantity)} available`,
                                }))}
                                onValueChange={(value) =>
                                    form.setData('inventory_batch_id', value)
                                }
                            />
                        </Field>
                    )}
                    <Field
                        label="Issue reason"
                        required
                        error={form.errors.reason}
                    >
                        <Textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    </Field>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Record issue
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function ReturnLineDialog({
    requisitionId,
    line,
    batches,
}: {
    requisitionId: string;
    line: Line;
    batches: Batch[];
}) {
    const [open, setOpen] = useState(false);
    const returnableStock = Math.max(
        0,
        Number(line.issued_quantity) - Number(line.returned_quantity),
    );
    const form = useForm({
        quantity: '',
        inventory_batch_id: '',
        reason: '',
        source_key: createUuid(),
    });
    const itemBatches = batches.filter(
        (batch) => batch.inventory_item_id === line.inventory_item_id,
    );
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(
            `/inventory/requisitions/${requisitionId}/lines/${line.id}/return`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    form.reset('quantity', 'reason', 'inventory_batch_id');
                    form.setData('source_key', createUuid());
                },
            },
        );
    };
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant="outline"
                    disabled={returnableStock <= 0}
                >
                    <CornerUpLeft /> Return
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Return {line.item_name}</DialogTitle>
                    <DialogDescription>
                        Return unused material to the same store. Net issued:{' '}
                        {formatNumber(returnableStock)} {line.stock_unit_name}.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <Field
                        label={`Quantity (${line.unit_name})`}
                        required
                        error={form.errors.quantity}
                    >
                        <Input
                            type="number"
                            min="0.0001"
                            step="0.0001"
                            value={form.data.quantity}
                            onChange={(event) =>
                                form.setData('quantity', event.target.value)
                            }
                        />
                    </Field>
                    {line.tracking_type === 'batch' && (
                        <Field
                            label="Batch"
                            required
                            error={form.errors.inventory_batch_id}
                        >
                            <SearchableSelect
                                value={form.data.inventory_batch_id}
                                options={itemBatches.map((batch) => ({
                                    value: batch.id,
                                    label: batch.batch_number,
                                }))}
                                onValueChange={(value) =>
                                    form.setData('inventory_batch_id', value)
                                }
                            />
                        </Field>
                    )}
                    <Field
                        label="Return reason"
                        required
                        error={form.errors.reason}
                    >
                        <Textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    </Field>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Record return
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function CancelRequisitionDialog({
    requisitionId,
}: {
    requisitionId: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.delete(`/inventory/requisitions/${requisitionId}`, {
            data: form.data,
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="destructive">
                    <XCircle /> Cancel requisition
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Cancel material requisition?</DialogTitle>
                    <DialogDescription>
                        Open reservations will be released. Issued stock
                        movements remain in the audit history.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <Field
                        label="Cancellation reason"
                        required
                        error={form.errors.reason}
                    >
                        <Textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    </Field>
                    <DialogFooter>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            Cancel requisition
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
        <div className="grid gap-2">
            <Label required={required}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
function title(value: string) {
    return value.charAt(0).toUpperCase() + value.slice(1);
}
