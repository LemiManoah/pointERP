import { useForm } from '@inertiajs/react';
import { RotateCcw, Warehouse } from 'lucide-react';
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
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';

type Option = {
    id: string;
    name: string;
    symbol?: string;
    branch_name?: string;
};
type BatchOption = {
    id: string;
    batch_number: string;
    inventory_store_id: string | null;
};
type Authority = {
    adjustStock: boolean;
    issueStock: boolean;
    returnStock: boolean;
};

export function StockMovementDialog({
    itemId,
    stores,
    units,
    batches,
    can,
}: {
    itemId: string;
    stores: Option[];
    units: Option[];
    batches: BatchOption[];
    can: Authority;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        inventory_store_id: stores.length === 1 ? stores[0].id : '',
        movement_type: can.issueStock
            ? 'issue'
            : can.returnStock
              ? 'return'
              : 'opening_balance',
        adjustment_direction: 'increase',
        original_quantity: '',
        original_unit_id: '',
        inventory_batch_id: '',
        reason: '',
        source_key: '',
    });
    const movementOptions = [
        ...(can.adjustStock
            ? [
                  { value: 'opening_balance', label: 'Opening balance' },
                  { value: 'adjustment', label: 'Stock adjustment' },
              ]
            : []),
        ...(can.issueStock ? [{ value: 'issue', label: 'Issue stock' }] : []),
        ...(can.returnStock
            ? [{ value: 'return', label: 'Return to store' }]
            : []),
    ];
    const batchOptions = batches.filter(
        (batch) =>
            batch.inventory_store_id === null ||
            batch.inventory_store_id === form.data.inventory_store_id,
    );
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(
            `/inventory/stores/${form.data.inventory_store_id}/items/${itemId}/movements`,
            { preserveScroll: true, onSuccess: () => setOpen(false) },
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Warehouse />
                    Record movement
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Record stock movement</DialogTitle>
                    <DialogDescription>
                        Posted movements change stock immediately and cannot be
                        edited. Corrections use a reversal.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Store"
                            error={form.errors.inventory_store_id}
                        >
                            <SearchableSelect
                                value={form.data.inventory_store_id}
                                options={stores.map((store) => ({
                                    value: store.id,
                                    label: store.name,
                                    description: store.branch_name,
                                }))}
                                onValueChange={(value) =>
                                    form.setData('inventory_store_id', value)
                                }
                                placeholder="Select store"
                            />
                        </Field>
                        <Field
                            label="Movement"
                            error={form.errors.movement_type}
                        >
                            <NativeSelect
                                value={form.data.movement_type}
                                onChange={(event) =>
                                    form.setData(
                                        'movement_type',
                                        event.target.value,
                                    )
                                }
                            >
                                {movementOptions.map((option) => (
                                    <NativeSelectOption
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                        </Field>
                    </div>
                    {form.data.movement_type === 'adjustment' && (
                        <Field
                            label="Adjustment direction"
                            error={form.errors.adjustment_direction}
                        >
                            <NativeSelect
                                value={form.data.adjustment_direction}
                                onChange={(event) =>
                                    form.setData(
                                        'adjustment_direction',
                                        event.target.value,
                                    )
                                }
                            >
                                <NativeSelectOption value="increase">
                                    Increase stock
                                </NativeSelectOption>
                                <NativeSelectOption value="decrease">
                                    Decrease stock
                                </NativeSelectOption>
                            </NativeSelect>
                        </Field>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Quantity"
                            error={form.errors.original_quantity}
                        >
                            <Input
                                type="number"
                                min="0"
                                step="0.0001"
                                value={form.data.original_quantity}
                                onChange={(event) =>
                                    form.setData(
                                        'original_quantity',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Unit"
                            error={form.errors.original_unit_id}
                        >
                            <SearchableSelect
                                value={form.data.original_unit_id}
                                options={units.map((unit) => ({
                                    value: unit.id,
                                    label: unit.name,
                                    description: unit.symbol,
                                }))}
                                onValueChange={(value) =>
                                    form.setData('original_unit_id', value)
                                }
                                placeholder="Select unit"
                            />
                        </Field>
                    </div>
                    {batches.length > 0 && (
                        <Field
                            label="Batch"
                            error={form.errors.inventory_batch_id}
                        >
                            <SearchableSelect
                                value={form.data.inventory_batch_id}
                                options={batchOptions.map((batch) => ({
                                    value: batch.id,
                                    label: batch.batch_number,
                                }))}
                                onValueChange={(value) =>
                                    form.setData('inventory_batch_id', value)
                                }
                                placeholder="Select batch"
                            />
                        </Field>
                    )}
                    <Field
                        label="Reason / supporting reference"
                        error={form.errors.reason}
                    >
                        <Textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    </Field>
                    <Button
                        type="submit"
                        disabled={
                            form.processing ||
                            form.data.inventory_store_id === ''
                        }
                    >
                        Record movement
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function StockMovementReversalDialog({
    movementId,
}: {
    movementId: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/inventory/stock-movements/${movementId}/reverse`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="icon" title="Reverse movement">
                    <RotateCcw />
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Reverse stock movement?</DialogTitle>
                    <DialogDescription>
                        The original entry remains in the ledger and an equal
                        opposite movement will be posted.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <Field label="Reason" error={form.errors.reason}>
                        <Textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    </Field>
                    <Button
                        type="submit"
                        variant="destructive"
                        disabled={form.processing}
                    >
                        Post reversal
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
