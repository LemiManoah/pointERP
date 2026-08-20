import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
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
    code?: string;
    symbol?: string;
    branch_name?: string;
};
export type Conversion = {
    id: string;
    from_unit_id: string;
    multiplier: string;
    effective_from: string | null;
    reason: string | null;
    is_active: boolean;
};
export type ItemPrice = {
    id: string;
    tier_code: string;
    tier_name: string;
    branch_id: string | null;
    unit_of_measure_id: string;
    amount: string;
    minimum_quantity: string | null;
    effective_from: string | null;
    effective_until: string | null;
    is_active: boolean;
};
export type Batch = {
    id: string;
    inventory_store_id: string | null;
    batch_number: string;
    manufactured_on: string | null;
    expires_on: string | null;
    status: string;
    notes: string | null;
    is_active: boolean;
};
export type StoreSetting = {
    id: string;
    inventory_store_id: string;
    minimum_stock: string | null;
    reorder_quantity: string | null;
    storage_location: string | null;
    is_active: boolean;
};

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

function Trigger({ editing }: { editing: boolean }) {
    return (
        <Button
            variant={editing ? 'outline' : 'default'}
            size={editing ? 'sm' : 'default'}
        >
            {editing ? <Pencil /> : <Plus />}
            {editing ? 'Edit' : 'Add new'}
        </Button>
    );
}

export function ConversionDialog({
    itemId,
    units,
    conversion,
}: {
    itemId: string;
    units: Option[];
    conversion?: Conversion;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        from_unit_id: conversion?.from_unit_id ?? '',
        multiplier: conversion?.multiplier ?? '',
        effective_from: conversion?.effective_from ?? '',
        reason: conversion?.reason ?? '',
        is_active: conversion?.is_active ?? true,
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };
        conversion
            ? form.put(
                  `/inventory/items/${itemId}/conversions/${conversion.id}`,
                  options,
              )
            : form.post(`/inventory/items/${itemId}/conversions`, options);
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Trigger editing={Boolean(conversion)} />
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {conversion
                            ? 'Edit unit conversion'
                            : 'Add unit conversion'}
                    </DialogTitle>
                    <DialogDescription>
                        Define how one transaction unit converts into the item
                        stock unit.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <Field
                        label="Transaction unit"
                        error={form.errors.from_unit_id}
                    >
                        <SearchableSelect
                            value={form.data.from_unit_id}
                            options={units.map((unit) => ({
                                value: unit.id,
                                label: unit.name,
                                description: unit.symbol ?? unit.code,
                            }))}
                            onValueChange={(value) =>
                                form.setData('from_unit_id', value)
                            }
                            placeholder="Select unit"
                        />
                    </Field>
                    <Field
                        label="Stock-unit quantity for 1 selected unit"
                        error={form.errors.multiplier}
                    >
                        <Input
                            type="number"
                            min="0"
                            step="0.0000000001"
                            value={form.data.multiplier}
                            onChange={(event) =>
                                form.setData('multiplier', event.target.value)
                            }
                        />
                    </Field>
                    <Field
                        label="Effective from"
                        error={form.errors.effective_from}
                    >
                        <Input
                            type="date"
                            value={form.data.effective_from}
                            onChange={(event) =>
                                form.setData(
                                    'effective_from',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <Field label="Reason" error={form.errors.reason}>
                        <Textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    </Field>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(event) =>
                                form.setData('is_active', event.target.checked)
                            }
                        />{' '}
                        Active
                    </label>
                    <Button type="submit" disabled={form.processing}>
                        Save conversion
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function PriceDialog({
    itemId,
    units,
    branches,
    price,
}: {
    itemId: string;
    units: Option[];
    branches: Option[];
    price?: ItemPrice;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        tier_code: price?.tier_code ?? '',
        tier_name: price?.tier_name ?? '',
        branch_id:
            price?.branch_id ?? (branches.length === 1 ? branches[0].id : ''),
        unit_of_measure_id: price?.unit_of_measure_id ?? '',
        amount: price?.amount ?? '',
        minimum_quantity: price?.minimum_quantity ?? '',
        effective_from: price?.effective_from ?? '',
        effective_until: price?.effective_until ?? '',
        is_active: price?.is_active ?? true,
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };
        price
            ? form.put(`/inventory/items/${itemId}/prices/${price.id}`, options)
            : form.post(`/inventory/items/${itemId}/prices`, options);
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Trigger editing={Boolean(price)} />
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {price
                            ? 'Edit price list entry'
                            : 'Add price list entry'}
                    </DialogTitle>
                    <DialogDescription>
                        Use names such as Retail, Wholesale or Staff. The
                        selected branch supplies the currency.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Price list code"
                            error={form.errors.tier_code}
                        >
                            <Input
                                value={form.data.tier_code}
                                onChange={(event) =>
                                    form.setData(
                                        'tier_code',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Price list name"
                            error={form.errors.tier_name}
                        >
                            <Input
                                value={form.data.tier_name}
                                onChange={(event) =>
                                    form.setData(
                                        'tier_name',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field
                            label="Branch / facility"
                            error={form.errors.branch_id}
                        >
                            <SearchableSelect
                                value={form.data.branch_id}
                                options={branches.map((branch) => ({
                                    value: branch.id,
                                    label: branch.name,
                                    description: branch.code,
                                }))}
                                onValueChange={(value) =>
                                    form.setData('branch_id', value)
                                }
                                placeholder="Use current facility"
                            />
                        </Field>
                        <Field
                            label="Selling unit"
                            error={form.errors.unit_of_measure_id}
                        >
                            <SearchableSelect
                                value={form.data.unit_of_measure_id}
                                options={units.map((unit) => ({
                                    value: unit.id,
                                    label: unit.name,
                                    description: unit.symbol ?? unit.code,
                                }))}
                                onValueChange={(value) =>
                                    form.setData('unit_of_measure_id', value)
                                }
                                placeholder="Select unit"
                            />
                        </Field>
                        <Field label="Price" error={form.errors.amount}>
                            <Input
                                type="number"
                                min="0"
                                step="0.0001"
                                value={form.data.amount}
                                onChange={(event) =>
                                    form.setData('amount', event.target.value)
                                }
                            />
                        </Field>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field
                            label="Minimum quantity"
                            error={form.errors.minimum_quantity}
                        >
                            <Input
                                type="number"
                                min="0"
                                step="0.0001"
                                value={form.data.minimum_quantity}
                                onChange={(event) =>
                                    form.setData(
                                        'minimum_quantity',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Effective from"
                            error={form.errors.effective_from}
                        >
                            <Input
                                type="date"
                                value={form.data.effective_from}
                                onChange={(event) =>
                                    form.setData(
                                        'effective_from',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Effective until"
                            error={form.errors.effective_until}
                        >
                            <Input
                                type="date"
                                value={form.data.effective_until}
                                onChange={(event) =>
                                    form.setData(
                                        'effective_until',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(event) =>
                                form.setData('is_active', event.target.checked)
                            }
                        />{' '}
                        Active
                    </label>
                    <Button type="submit" disabled={form.processing}>
                        Save price
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function BatchDialog({
    itemId,
    stores,
    batch,
}: {
    itemId: string;
    stores: Option[];
    batch?: Batch;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        inventory_store_id: batch?.inventory_store_id ?? '',
        batch_number: batch?.batch_number ?? '',
        manufactured_on: batch?.manufactured_on ?? '',
        expires_on: batch?.expires_on ?? '',
        status: batch?.status ?? 'available',
        notes: batch?.notes ?? '',
        is_active: batch?.is_active ?? true,
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };
        batch
            ? form.put(
                  `/inventory/items/${itemId}/batches/${batch.id}`,
                  options,
              )
            : form.post(`/inventory/items/${itemId}/batches`, options);
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Trigger editing={Boolean(batch)} />
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {batch ? 'Edit batch' : 'Add batch'}
                    </DialogTitle>
                    <DialogDescription>
                        Record the batch identity, expiry and current
                        operational state.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Batch number"
                            error={form.errors.batch_number}
                        >
                            <Input
                                value={form.data.batch_number}
                                onChange={(event) =>
                                    form.setData(
                                        'batch_number',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Store"
                            error={form.errors.inventory_store_id}
                        >
                            <SearchableSelect
                                value={form.data.inventory_store_id}
                                options={[
                                    {
                                        value: '',
                                        label: 'Not assigned to a store',
                                    },
                                    ...stores.map((store) => ({
                                        value: store.id,
                                        label: store.name,
                                        description: store.branch_name,
                                    })),
                                ]}
                                onValueChange={(value) =>
                                    form.setData('inventory_store_id', value)
                                }
                                placeholder="Select store"
                            />
                        </Field>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field
                            label="Manufactured on"
                            error={form.errors.manufactured_on}
                        >
                            <Input
                                type="date"
                                value={form.data.manufactured_on}
                                onChange={(event) =>
                                    form.setData(
                                        'manufactured_on',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Expires on"
                            error={form.errors.expires_on}
                        >
                            <Input
                                type="date"
                                value={form.data.expires_on}
                                onChange={(event) =>
                                    form.setData(
                                        'expires_on',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Status" error={form.errors.status}>
                            <NativeSelect
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData('status', event.target.value)
                                }
                            >
                                <NativeSelectOption value="available">
                                    Available
                                </NativeSelectOption>
                                <NativeSelectOption value="quarantined">
                                    Quarantined
                                </NativeSelectOption>
                                <NativeSelectOption value="exhausted">
                                    Exhausted
                                </NativeSelectOption>
                                <NativeSelectOption value="expired">
                                    Expired
                                </NativeSelectOption>
                            </NativeSelect>
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
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(event) =>
                                form.setData('is_active', event.target.checked)
                            }
                        />{' '}
                        Active
                    </label>
                    <Button type="submit" disabled={form.processing}>
                        Save batch
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function StoreSettingDialog({
    itemId,
    stores,
    setting,
}: {
    itemId: string;
    stores: Option[];
    setting?: StoreSetting;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        inventory_store_id: setting?.inventory_store_id ?? '',
        minimum_stock: setting?.minimum_stock ?? '',
        reorder_quantity: setting?.reorder_quantity ?? '',
        storage_location: setting?.storage_location ?? '',
        is_active: setting?.is_active ?? true,
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };
        setting
            ? form.put(
                  `/inventory/items/${itemId}/store-settings/${setting.id}`,
                  options,
              )
            : form.post(`/inventory/items/${itemId}/store-settings`, options);
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Trigger editing={Boolean(setting)} />
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {setting
                            ? 'Edit store settings'
                            : 'Enable item in store'}
                    </DialogTitle>
                    <DialogDescription>
                        Override the item defaults for a specific operational
                        store.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <Field label="Store" error={form.errors.inventory_store_id}>
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
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Minimum stock warning"
                            error={form.errors.minimum_stock}
                        >
                            <Input
                                type="number"
                                min="0"
                                step="0.0001"
                                value={form.data.minimum_stock}
                                onChange={(event) =>
                                    form.setData(
                                        'minimum_stock',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Reorder quantity"
                            error={form.errors.reorder_quantity}
                        >
                            <Input
                                type="number"
                                min="0"
                                step="0.0001"
                                value={form.data.reorder_quantity}
                                onChange={(event) =>
                                    form.setData(
                                        'reorder_quantity',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <Field
                        label="Storage location"
                        error={form.errors.storage_location}
                    >
                        <Input
                            value={form.data.storage_location}
                            onChange={(event) =>
                                form.setData(
                                    'storage_location',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(event) =>
                                form.setData('is_active', event.target.checked)
                            }
                        />{' '}
                        Active
                    </label>
                    <Button type="submit" disabled={form.processing}>
                        Save store settings
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
