import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import type {
    ComponentPropsWithoutRef,
    ComponentRef,
    FormEvent,
    ReactNode,
} from 'react';
import { forwardRef, useState } from 'react';
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
    inventory_price_tier_id: string;
    tier_code: string;
    tier_name: string;
    branch_id: string | null;
    amount: string;
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

const Trigger = forwardRef<
    ComponentRef<typeof Button>,
    ComponentPropsWithoutRef<typeof Button> & { editing: boolean }
>(function Trigger({ editing, ...props }, ref) {
    return (
        <Button
            ref={ref}
            variant={editing ? 'outline' : 'default'}
            size={editing ? 'sm' : 'default'}
            {...props}
        >
            {editing ? <Pencil /> : <Plus />}
            {editing ? 'Edit' : 'Add new'}
        </Button>
    );
});

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
        if (conversion) {
            form.put(
                `/inventory/items/${itemId}/conversions/${conversion.id}`,
                options,
            );
        } else {
            form.post(`/inventory/items/${itemId}/conversions`, options);
        }
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
    branches,
    defaultBranchId,
    canChangeBranch,
    priceLists,
    price,
}: {
    itemId: string;
    branches: Option[];
    defaultBranchId: string;
    canChangeBranch: boolean;
    priceLists: Option[];
    price?: ItemPrice;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        inventory_price_tier_id: price?.inventory_price_tier_id ?? '',
        branch_id: price?.branch_id ?? defaultBranchId,
        amount: price?.amount ?? '',
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };
        if (price) {
            form.put(`/inventory/items/${itemId}/prices/${price.id}`, options);
        } else {
            form.post(`/inventory/items/${itemId}/prices`, options);
        }
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
                        Select a price list and enter the price. Currency,
                        branch and selling unit are resolved automatically.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <Field
                        label="Price list"
                        error={form.errors.inventory_price_tier_id}
                    >
                        {price ? (
                            <Input value={price.tier_name} disabled />
                        ) : (
                            <SearchableSelect
                                value={form.data.inventory_price_tier_id}
                                options={priceLists.map((list) => ({
                                    value: list.id,
                                    label: list.name,
                                    description: list.code,
                                }))}
                                onValueChange={(value) =>
                                    form.setData(
                                        'inventory_price_tier_id',
                                        value,
                                    )
                                }
                                placeholder="Select price list"
                            />
                        )}
                    </Field>
                    {canChangeBranch && !price && (
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
                    )}
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
        if (batch) {
            form.put(`/inventory/items/${itemId}/batches/${batch.id}`, options);
        } else {
            form.post(`/inventory/items/${itemId}/batches`, options);
        }
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
        if (setting) {
            form.put(
                `/inventory/items/${itemId}/store-settings/${setting.id}`,
                options,
            );
        } else {
            form.post(`/inventory/items/${itemId}/store-settings`, options);
        }
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
                        Enable this item in a store and optionally override its
                        warning level, reorder quantity and storage location.
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
