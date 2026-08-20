import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import type { Category, Item, Option, Store, Unit } from '../types';

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return <div className="grid gap-2"><Label>{label}</Label>{children}<InputError message={error} /></div>;
}

function useModalForm<T extends Record<string, string | boolean>>(data: T, url: string, method: 'post' | 'put', onClose: () => void) {
    const form = useForm<T>(data);
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        method === 'put' ? form.put(url, options) : form.post(url, options);
    }
    return { form, submit };
}

export function CategoryDialog({ category }: { category?: Category }) {
    const [open, setOpen] = useState(false);
    const { form, submit } = useModalForm({ code: category?.code ?? '', name: category?.name ?? '', description: category?.description ?? '', is_active: category?.is_active ?? true }, category ? `/inventory/categories/${category.id}` : '/inventory/categories', category ? 'put' : 'post', () => setOpen(false));
    return <Dialog open={open} onOpenChange={setOpen}><DialogTrigger asChild><Button variant={category ? 'outline' : 'default'} size={category ? 'sm' : 'default'}>{category ? <Pencil /> : <Plus />}{category ? 'Edit' : 'New category'}</Button></DialogTrigger><DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl"><DialogHeader><DialogTitle>{category ? 'Edit inventory category' : 'New inventory category'}</DialogTitle><DialogDescription>Group materials for search, reporting and stock controls.</DialogDescription></DialogHeader><form onSubmit={submit} className="grid gap-5"><div className="grid gap-4 sm:grid-cols-2"><Field label="Code" error={form.errors.code}><Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} /></Field><Field label="Name" error={form.errors.name}><Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></Field></div><Field label="Description" error={form.errors.description}><Textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></Field><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} /> Active</label><Button type="submit" disabled={form.processing}>{category ? 'Save changes' : 'Create category'}</Button></form></DialogContent></Dialog>;
}

export function UnitDialog({ unit }: { unit?: Unit }) {
    const [open, setOpen] = useState(false);
    const { form, submit } = useModalForm({ code: unit?.code ?? '', name: unit?.name ?? '', symbol: unit?.symbol ?? '', quantity_dimension: unit?.quantity_dimension ?? 'count', is_base_unit: unit?.is_base_unit ?? false, is_active: unit?.is_active ?? true }, unit ? `/inventory/units/${unit.id}` : '/inventory/units', unit ? 'put' : 'post', () => setOpen(false));
    return <Dialog open={open} onOpenChange={setOpen}><DialogTrigger asChild><Button variant={unit ? 'outline' : 'default'} size={unit ? 'sm' : 'default'}>{unit ? <Pencil /> : <Plus />}{unit ? 'Edit' : 'New unit'}</Button></DialogTrigger><DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl"><DialogHeader><DialogTitle>{unit ? 'Edit unit of measure' : 'New unit of measure'}</DialogTitle><DialogDescription>Define a measurement that inventory items can use as their stock unit.</DialogDescription></DialogHeader><form onSubmit={submit} className="grid gap-5"><div className="grid gap-4 sm:grid-cols-2"><Field label="Code" error={form.errors.code}><Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} /></Field><Field label="Name" error={form.errors.name}><Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></Field></div><div className="grid gap-4 sm:grid-cols-2"><Field label="Symbol" error={form.errors.symbol}><Input value={form.data.symbol} onChange={(e) => form.setData('symbol', e.target.value)} /></Field><Field label="Dimension" error={form.errors.quantity_dimension}><NativeSelect value={form.data.quantity_dimension} onChange={(e) => form.setData('quantity_dimension', e.target.value)}><NativeSelectOption value="mass">Mass</NativeSelectOption><NativeSelectOption value="volume">Volume</NativeSelectOption><NativeSelectOption value="length">Length</NativeSelectOption><NativeSelectOption value="area">Area</NativeSelectOption><NativeSelectOption value="count">Count</NativeSelectOption><NativeSelectOption value="time">Time</NativeSelectOption></NativeSelect></Field></div><div className="flex flex-wrap gap-5 text-sm"><label className="flex items-center gap-2"><input type="checkbox" checked={form.data.is_base_unit} onChange={(e) => form.setData('is_base_unit', e.target.checked)} /> Base unit</label><label className="flex items-center gap-2"><input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} /> Active</label></div><Button type="submit" disabled={form.processing}>{unit ? 'Save changes' : 'Create unit'}</Button></form></DialogContent></Dialog>;
}

export function ItemDialog({
    item,
    categories,
    units,
    suppliers,
    canViewCosts,
}: {
    item?: Item;
    categories: Category[];
    units: Unit[];
    suppliers: Option[];
    canViewCosts: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [codeEdited, setCodeEdited] = useState(item !== undefined);
    const { form, submit } = useModalForm(
        {
            inventory_category_id: item?.inventory_category_id ?? '',
            stock_unit_id: item?.stock_unit_id ?? '',
            preferred_supplier_id: item?.preferred_supplier?.id ?? '',
            code: item?.code ?? '',
            name: item?.name ?? '',
            description: item?.description ?? '',
            material_class: item?.material_class ?? 'construction_material',
            tracking_type: item?.tracking_type ?? 'none',
            batch_number: item?.batch_number ?? '',
            is_expires: item?.is_expires ?? false,
            is_for_sale: item?.is_for_sale ?? false,
            reorder_level: item?.reorder_level ?? '0',
            reorder_quantity: item?.reorder_quantity ?? '',
            default_unit_cost: item?.default_unit_cost ?? '',
            default_selling_price: item?.default_selling_price ?? '',
            is_active: item?.is_active ?? true,
        },
        item ? `/inventory/items/${item.id}` : '/inventory/items',
        item ? 'put' : 'post',
        () => setOpen(false),
    );
    const categoryOptions = categories
        .filter(
            (row) =>
                row.is_active || row.id === form.data.inventory_category_id,
        )
        .map((row) => ({
            value: row.id,
            label: row.name,
            description: row.code,
        }));
    const unitOptions = units
        .filter((row) => row.is_active || row.id === form.data.stock_unit_id)
        .map((row) => ({
            value: row.id,
            label: row.name,
            description: row.symbol ?? row.code,
        }));
    const supplierOptions = [
        { value: '', label: 'No preferred supplier' },
        ...suppliers.map((row) => ({
            value: row.id,
            label: row.name,
            description: row.code,
        })),
    ];

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={item ? 'outline' : 'default'}
                    size={item ? 'sm' : 'default'}
                >
                    {item ? <Pencil /> : <Plus />}
                    {item ? 'Edit' : 'New item'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>
                        {item ? 'Edit inventory item' : 'New inventory item'}
                    </DialogTitle>
                    <DialogDescription>
                        Define how the item is stocked, tracked and optionally
                        priced.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Name" error={form.errors.name}>
                            <Input
                                value={form.data.name}
                                onChange={(event) => {
                                    const name = event.target.value;
                                    form.setData('name', name);
                                    if (!codeEdited) {
                                        form.setData(
                                            'code',
                                            generateItemCode(name),
                                        );
                                    }
                                }}
                            />
                        </Field>
                        <Field label="Code" error={form.errors.code}>
                            <Input
                                value={form.data.code}
                                onChange={(event) => {
                                    setCodeEdited(true);
                                    form.setData(
                                        'code',
                                        event.target.value.toUpperCase(),
                                    );
                                }}
                            />
                        </Field>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Category"
                            error={form.errors.inventory_category_id}
                        >
                            <SearchableSelect
                                value={form.data.inventory_category_id}
                                options={categoryOptions}
                                onValueChange={(value) =>
                                    form.setData('inventory_category_id', value)
                                }
                                placeholder="Select category"
                            />
                        </Field>
                        <Field
                            label="Stock unit"
                            error={form.errors.stock_unit_id}
                        >
                            <SearchableSelect
                                value={form.data.stock_unit_id}
                                options={unitOptions}
                                onValueChange={(value) =>
                                    form.setData('stock_unit_id', value)
                                }
                                placeholder="Select stock unit"
                            />
                        </Field>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Material class"
                            error={form.errors.material_class}
                        >
                            <NativeSelect
                                value={form.data.material_class}
                                onChange={(event) =>
                                    form.setData(
                                        'material_class',
                                        event.target.value,
                                    )
                                }
                            >
                                <NativeSelectOption value="construction_material">Construction material</NativeSelectOption>
                                <NativeSelectOption value="consumable">Consumable</NativeSelectOption>
                                <NativeSelectOption value="spare_part">Spare part</NativeSelectOption>
                                <NativeSelectOption value="fuel_related">Fuel related</NativeSelectOption>
                                <NativeSelectOption value="other">Other</NativeSelectOption>
                            </NativeSelect>
                        </Field>
                        <Field
                            label="Tracking"
                            error={form.errors.tracking_type}
                        >
                            <NativeSelect
                                value={form.data.tracking_type}
                                onChange={(event) => {
                                    const trackingType = event.target.value;
                                    form.setData('tracking_type', trackingType);
                                    if (trackingType === 'batch') {
                                        form.setData('is_expires', true);
                                    } else {
                                        form.setData('batch_number', '');
                                    }
                                }}
                            >
                                <NativeSelectOption value="none">None</NativeSelectOption>
                                <NativeSelectOption value="serial">Serial</NativeSelectOption>
                                <NativeSelectOption value="batch">Batch</NativeSelectOption>
                                <NativeSelectOption value="other">Other</NativeSelectOption>
                            </NativeSelect>
                        </Field>
                        <Field
                            label="Preferred supplier"
                            error={form.errors.preferred_supplier_id}
                        >
                            <SearchableSelect
                                value={form.data.preferred_supplier_id}
                                options={supplierOptions}
                                onValueChange={(value) =>
                                    form.setData('preferred_supplier_id', value)
                                }
                                placeholder="Select supplier"
                            />
                        </Field>
                    </div>
                    {form.data.tracking_type === 'batch' && (
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
                                placeholder="Enter the initial batch number"
                            />
                        </Field>
                    )}
                    <Field label="Description" error={form.errors.description}>
                        <Textarea
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field
                            label="Reorder level"
                            error={form.errors.reorder_level}
                        >
                            <Input
                                type="number"
                                min="0"
                                step="0.0001"
                                value={form.data.reorder_level}
                                onChange={(event) =>
                                    form.setData(
                                        'reorder_level',
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
                    <div className="flex flex-wrap gap-5 text-sm">
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={form.data.is_expires}
                                disabled={form.data.tracking_type === 'batch'}
                                onChange={(event) =>
                                    form.setData(
                                        'is_expires',
                                        event.target.checked,
                                    )
                                }
                            />
                            Track expiry
                        </label>
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={form.data.is_for_sale}
                                onChange={(event) => {
                                    form.setData(
                                        'is_for_sale',
                                        event.target.checked,
                                    );
                                    if (!event.target.checked) {
                                        form.setData(
                                            'default_selling_price',
                                            '',
                                        );
                                    }
                                }}
                            />
                            Available for sale
                        </label>
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(event) =>
                                    form.setData(
                                        'is_active',
                                        event.target.checked,
                                    )
                                }
                            />
                            Active
                        </label>
                    </div>
                    {canViewCosts && (
                        <div className="grid gap-4 rounded-md border p-4 sm:grid-cols-2">
                            <Field
                                label="Default unit cost (branch currency)"
                                error={form.errors.default_unit_cost}
                            >
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.0001"
                                    value={form.data.default_unit_cost}
                                    onChange={(event) =>
                                        form.setData(
                                            'default_unit_cost',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            {form.data.is_for_sale && (
                                <Field
                                    label="Default selling price (branch currency)"
                                    error={form.errors.default_selling_price}
                                >
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.0001"
                                        value={form.data.default_selling_price}
                                        onChange={(event) =>
                                            form.setData(
                                                'default_selling_price',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            )}
                        </div>
                    )}
                    <Button type="submit" disabled={form.processing}>
                        {item ? 'Save changes' : 'Create item'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function StoreDialog({ store, branches, projects, sites, locations }: { store?: Store; branches: Option[]; projects: Option[]; sites: Option[]; locations: Option[] }) {
    const [open, setOpen] = useState(false);
    const { form, submit } = useModalForm({ branch_id: store?.branch_id ?? (branches.length === 1 ? branches[0].id : ''), equipment_location_id: store?.equipment_location_id ?? '', project_id: store?.project_id ?? '', site_id: store?.site_id ?? '', code: store?.code ?? '', name: store?.name ?? '', type: store?.type ?? 'depot', address: store?.address ?? '', is_active: store?.is_active ?? true }, store ? `/inventory/stores/${store.id}` : '/inventory/stores', store ? 'put' : 'post', () => setOpen(false));
    const projectOptions = projects.filter((row) => !form.data.branch_id || row.branch_id === form.data.branch_id).map((row) => ({ value: row.id, label: row.name, description: row.reference }));
    const siteOptions = sites.filter((row) => (!form.data.branch_id || row.branch_id === form.data.branch_id) && (!form.data.project_id || row.project_id === form.data.project_id)).map((row) => ({ value: row.id, label: row.name, description: row.reference }));
    const locationOptions = locations.filter((row) => !form.data.branch_id || row.branch_id === form.data.branch_id).map((row) => ({ value: row.id, label: row.name, description: row.code }));
    return <Dialog open={open} onOpenChange={setOpen}><DialogTrigger asChild><Button variant={store ? 'outline' : 'default'} size={store ? 'sm' : 'default'}>{store ? <Pencil /> : <Plus />}{store ? 'Edit' : 'New store'}</Button></DialogTrigger><DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-3xl"><DialogHeader><DialogTitle>{store ? 'Edit store' : 'New store'}</DialogTitle><DialogDescription>A store can serve several sites. The project and site fields describe its primary context only.</DialogDescription></DialogHeader><form onSubmit={submit} className="grid gap-5"><div className="grid gap-4 sm:grid-cols-2"><Field label="Branch" error={form.errors.branch_id}><SearchableSelect value={form.data.branch_id} options={branches.map((row) => ({ value: row.id, label: row.name, description: row.code }))} onValueChange={(value) => { form.setData('branch_id', value); form.setData('project_id', ''); form.setData('site_id', ''); form.setData('equipment_location_id', ''); }} placeholder="Select branch" /></Field><Field label="Store type" error={form.errors.type}><NativeSelect value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}><NativeSelectOption value="warehouse">Warehouse</NativeSelectOption><NativeSelectOption value="depot">Depot</NativeSelectOption><NativeSelectOption value="site_store">Site store</NativeSelectOption><NativeSelectOption value="temporary">Temporary</NativeSelectOption><NativeSelectOption value="other">Other</NativeSelectOption></NativeSelect></Field></div><div className="grid gap-4 sm:grid-cols-2"><Field label="Code" error={form.errors.code}><Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} /></Field><Field label="Name" error={form.errors.name}><Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></Field></div><div className="grid gap-4 sm:grid-cols-2"><Field label="Project context" error={form.errors.project_id}><SearchableSelect value={form.data.project_id} options={[{ value: '', label: 'No project context' }, ...projectOptions]} onValueChange={(value) => { form.setData('project_id', value); form.setData('site_id', ''); }} placeholder="Select project" /></Field><Field label="Site context" error={form.errors.site_id}><SearchableSelect value={form.data.site_id} options={[{ value: '', label: 'No site context' }, ...siteOptions]} onValueChange={(value) => form.setData('site_id', value)} placeholder="Select site" /></Field></div><Field label="Equipment location link" error={form.errors.equipment_location_id}><SearchableSelect value={form.data.equipment_location_id} options={[{ value: '', label: 'No linked location' }, ...locationOptions]} onValueChange={(value) => form.setData('equipment_location_id', value)} placeholder="Select location" /></Field><Field label="Address" error={form.errors.address}><Textarea value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} /></Field><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} /> Active</label><Button type="submit" disabled={form.processing}>{store ? 'Save changes' : 'Create store'}</Button></form></DialogContent></Dialog>;
}

function generateItemCode(name: string): string {
    return name
        .trim()
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 60);
}
