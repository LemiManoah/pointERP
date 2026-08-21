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
import { Textarea } from '@/components/ui/textarea';
import type { ConversionRegister, Item, PriceList, Unit } from '../types';

export function PriceListDialog({ priceList }: { priceList?: PriceList }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        code: priceList?.code ?? '',
        name: priceList?.name ?? '',
        description: priceList?.description ?? '',
        priority: String(priceList?.priority ?? 100),
        is_active: priceList?.is_active ?? true,
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };
        if (priceList) {
            form.put(`/inventory/price-lists/${priceList.id}`, options);
        } else {
            form.post('/inventory/price-lists', options);
        }
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={priceList ? 'outline' : 'default'}
                    size={priceList ? 'sm' : 'default'}
                >
                    {priceList ? <Pencil /> : <Plus />}
                    {priceList ? 'Edit' : 'New price list'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {priceList ? 'Edit price list' : 'New price list'}
                    </DialogTitle>
                    <DialogDescription>
                        Create a reusable list such as Retail, Wholesale or
                        Staff. Item prices are attached later.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Name" error={form.errors.name}>
                            <Input
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                        </Field>
                        <Field label="Code" error={form.errors.code}>
                            <Input
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData(
                                        'code',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <Field label="Description" error={form.errors.description}>
                        <Textarea
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                    </Field>
                    <Field
                        label="Display priority"
                        error={form.errors.priority}
                    >
                        <Input
                            type="number"
                            min="0"
                            value={form.data.priority}
                            onChange={(event) =>
                                form.setData('priority', event.target.value)
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
                        Save price list
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function GlobalConversionDialog({
    items,
    units,
    conversion,
}: {
    items: Item[];
    units: Unit[];
    conversion?: ConversionRegister;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        inventory_item_id: conversion?.inventory_item_id ?? '',
        from_unit_id: conversion?.from_unit_id ?? '',
        multiplier: conversion?.multiplier ?? '',
        effective_from: conversion?.effective_from ?? '',
        reason: conversion?.reason ?? '',
        is_active: conversion?.is_active ?? true,
        return_to: 'register',
    });
    const selectedItem = items.find(
        (item) => item.id === form.data.inventory_item_id,
    );
    const availableUnits = units.filter(
        (unit) => unit.id !== selectedItem?.stock_unit_id && unit.is_active,
    );
    function submit(event: FormEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };
        if (conversion) {
            form.put(
                `/inventory/items/${conversion.inventory_item_id}/conversions/${conversion.id}`,
                options,
            );
        } else {
            form.post(
                `/inventory/items/${form.data.inventory_item_id}/conversions`,
                options,
            );
        }
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={conversion ? 'outline' : 'default'}
                    size={conversion ? 'sm' : 'default'}
                >
                    {conversion ? <Pencil /> : <Plus />}
                    {conversion ? 'Edit' : 'New conversion'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {conversion
                            ? 'Edit unit conversion'
                            : 'New unit conversion'}
                    </DialogTitle>
                    <DialogDescription>
                        Add a transaction unit to an item by defining how much
                        of its stock unit it represents.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <Field
                        label="Inventory item"
                        error={form.errors.inventory_item_id}
                    >
                        {conversion ? (
                            <Input
                                value={`${conversion.item_name} (${conversion.item_code})`}
                                disabled
                            />
                        ) : (
                            <SearchableSelect
                                value={form.data.inventory_item_id}
                                options={items
                                    .filter((item) => item.is_active)
                                    .map((item) => ({
                                        value: item.id,
                                        label: item.name,
                                        description: item.code,
                                    }))}
                                onValueChange={(value) => {
                                    form.setData('inventory_item_id', value);
                                    form.setData('from_unit_id', '');
                                }}
                                placeholder="Select item"
                            />
                        )}
                    </Field>
                    <Field
                        label="Additional unit"
                        error={form.errors.from_unit_id}
                    >
                        <SearchableSelect
                            value={form.data.from_unit_id}
                            options={availableUnits.map((unit) => ({
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
                        label={`Stock-unit quantity for 1 additional unit${selectedItem?.stock_unit?.name ? ` (${selectedItem.stock_unit.name})` : ''}`}
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
                    <Button
                        type="submit"
                        disabled={
                            form.processing ||
                            form.data.inventory_item_id === ''
                        }
                    >
                        Save conversion
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
