import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { EquipmentCategory } from '../types';

type FormData = Record<string, string | boolean> & {
    code: string;
    name: string;
    description: string;
    default_meter_type: string;
    default_capacity_unit: string;
    fuel_efficiency_basis: string;
    expected_fuel_efficiency: string;
    fuel_tolerance_percent: string;
    is_active: boolean;
};

export function EquipmentCategoryDialog({
    category,
}: {
    category?: EquipmentCategory;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<FormData>({
        code: category?.code ?? '',
        name: category?.name ?? '',
        description: category?.description ?? '',
        default_meter_type: category?.default_meter_type ?? 'engine_hours',
        default_capacity_unit: category?.default_capacity_unit ?? '',
        fuel_efficiency_basis: category?.fuel_efficiency_basis ?? '',
        expected_fuel_efficiency: category?.expected_fuel_efficiency ?? '',
        fuel_tolerance_percent: category?.fuel_tolerance_percent ?? '',
        is_active: category?.is_active ?? true,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { onSuccess: () => setOpen(false), preserveScroll: true };
        if (category) {
            form.put(`/equipment-categories/${category.id}`, options);
        } else {
            form.post('/equipment-categories', options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant={category ? 'outline' : 'default'} size={category ? 'sm' : 'default'}>
                    {category ? <Pencil /> : <Plus />}
                    {category ? 'Edit' : 'New category'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{category ? `Edit ${category.name}` : 'New equipment category'}</DialogTitle>
                    <DialogDescription>Set shared meter, capacity and fuel-control defaults for similar assets.</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Code" error={form.errors.code}><Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} /></Field>
                        <Field label="Name" error={form.errors.name}><Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></Field>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Default meter" error={form.errors.default_meter_type}>
                            <NativeSelect value={form.data.default_meter_type} onChange={(e) => form.setData('default_meter_type', e.target.value)}>
                                <NativeSelectOption value="odometer_km">Odometer (km)</NativeSelectOption>
                                <NativeSelectOption value="engine_hours">Engine hours</NativeSelectOption>
                                <NativeSelectOption value="operating_hours">Operating hours</NativeSelectOption>
                                <NativeSelectOption value="none">No meter</NativeSelectOption>
                            </NativeSelect>
                        </Field>
                        <Field label="Default capacity unit" error={form.errors.default_capacity_unit}><Input placeholder="tonnes, litres, kVA" value={form.data.default_capacity_unit} onChange={(e) => form.setData('default_capacity_unit', e.target.value)} /></Field>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label="Fuel basis" error={form.errors.fuel_efficiency_basis}>
                            <NativeSelect value={form.data.fuel_efficiency_basis} onChange={(e) => form.setData('fuel_efficiency_basis', e.target.value)}>
                                <NativeSelectOption value="">Not set</NativeSelectOption>
                                <NativeSelectOption value="litres_per_hour">Litres per hour</NativeSelectOption>
                                <NativeSelectOption value="litres_per_100km">Litres per 100 km</NativeSelectOption>
                            </NativeSelect>
                        </Field>
                        <Field label="Expected consumption" error={form.errors.expected_fuel_efficiency}><Input type="number" min="0" step="0.0001" value={form.data.expected_fuel_efficiency} onChange={(e) => form.setData('expected_fuel_efficiency', e.target.value)} /></Field>
                        <Field label="Tolerance %" error={form.errors.fuel_tolerance_percent}><Input type="number" min="0" max="100" step="0.01" value={form.data.fuel_tolerance_percent} onChange={(e) => form.setData('fuel_tolerance_percent', e.target.value)} /></Field>
                    </div>
                    <Field label="Description" error={form.errors.description}><Textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} /></Field>
                    <div className="flex justify-end gap-3">
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>{form.processing && <Spinner />}Save category</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return <div className="grid gap-2"><Label>{label}</Label>{children}<InputError message={error} /></div>;
}
