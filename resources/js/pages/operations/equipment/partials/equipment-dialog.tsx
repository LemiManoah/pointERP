import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo, useState } from 'react';
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
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type {
    BranchOption,
    EquipmentCategory,
    EquipmentLocation,
    EquipmentRecord,
    Option,
    OwnerOption,
} from '../types';

type FormData = Record<string, string | boolean> & {
    branch_id: string; equipment_category_id: string; asset_code: string; name: string;
    make: string; model: string; model_year: string; serial_number: string;
    registration_number: string; chassis_number: string; ownership_type: string;
    owner_customer_id: string; owner_name: string; capacity_value: string; capacity_unit: string;
    acquired_on: string; acquisition_amount: string; acquisition_currency_code: string;
    hire_rate: string; hire_rate_basis: string; default_location_id: string; meter_type: string;
    starting_meter_reading: string; starting_meter_date: string; fuel_efficiency_basis: string;
    expected_fuel_efficiency: string; fuel_tolerance_percent: string; tank_capacity: string;
    current_status: string; condition_summary: string; is_active: boolean;
};

type Props = {
    equipment?: EquipmentRecord;
    branches: BranchOption[];
    categories: EquipmentCategory[];
    locations: EquipmentLocation[];
    owners: OwnerOption[];
    currencies: Option[];
    canViewCosts: boolean;
};

export function EquipmentDialog({ equipment, branches, categories, locations, owners, currencies, canViewCosts }: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm<FormData>({
        branch_id: equipment?.branch_id ?? (branches.length === 1 ? (branches[0]?.id ?? '') : ''),
        equipment_category_id: equipment?.equipment_category_id ?? '',
        asset_code: equipment?.asset_code ?? '', name: equipment?.name ?? '', make: equipment?.make ?? '',
        model: equipment?.model ?? '', model_year: equipment?.model_year?.toString() ?? '',
        serial_number: equipment?.serial_number ?? '', registration_number: equipment?.registration_number ?? '',
        chassis_number: equipment?.chassis_number ?? '', ownership_type: equipment?.ownership_type ?? 'owned',
        owner_customer_id: equipment?.owner_customer_id ?? '', owner_name: equipment?.owner_name ?? '',
        capacity_value: equipment?.capacity_value ?? '', capacity_unit: equipment?.capacity_unit ?? '',
        acquired_on: equipment?.acquired_on ?? '', acquisition_amount: equipment?.acquisition_amount ?? '',
        acquisition_currency_code: equipment?.acquisition_currency_code ?? '', hire_rate: equipment?.hire_rate ?? '',
        hire_rate_basis: equipment?.hire_rate_basis ?? '', default_location_id: equipment?.default_location_id ?? '',
        meter_type: equipment?.meter_type ?? 'engine_hours', starting_meter_reading: equipment?.starting_meter_reading ?? '',
        starting_meter_date: equipment?.starting_meter_date ?? '', fuel_efficiency_basis: equipment?.fuel_efficiency_basis ?? '',
        expected_fuel_efficiency: equipment?.expected_fuel_efficiency ?? '', fuel_tolerance_percent: equipment?.fuel_tolerance_percent ?? '',
        tank_capacity: equipment?.tank_capacity ?? '', current_status: equipment?.current_status ?? 'available',
        condition_summary: equipment?.condition_summary ?? '', is_active: equipment?.is_active ?? true,
    });
    const activeCategories = categories.filter((category) => category.is_active || category.id === equipment?.equipment_category_id);
    const locationOptions = useMemo(() => locations.filter((location) => location.branch_id === form.data.branch_id && (location.is_active || location.id === equipment?.default_location_id)), [equipment?.default_location_id, form.data.branch_id, locations]);
    const ownerOptions = owners.filter((owner) => !owner.branch_id || owner.branch_id === form.data.branch_id);

    function selectCategory(value: string) {
        form.setData('equipment_category_id', value);
        if (equipment) return;
        const category = categories.find((item) => item.id === value);
        if (!category) return;
        form.setData('meter_type', category.default_meter_type);
        form.setData('capacity_unit', category.default_capacity_unit ?? '');
        form.setData('fuel_efficiency_basis', category.fuel_efficiency_basis ?? '');
        form.setData('expected_fuel_efficiency', category.expected_fuel_efficiency ?? '');
        form.setData('fuel_tolerance_percent', category.fuel_tolerance_percent ?? '');
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { onSuccess: () => setOpen(false) };
        if (equipment) form.put(`/equipment/${equipment.id}`, options);
        else form.post('/equipment', options);
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant={equipment ? 'outline' : 'default'} size={equipment ? 'sm' : 'default'}>
                    {equipment ? <Pencil /> : <Plus />}{equipment ? 'Edit' : 'New asset'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-5xl">
                <DialogHeader>
                    <DialogTitle>{equipment ? `Edit ${equipment.asset_code}` : 'Register equipment'}</DialogTitle>
                    <DialogDescription>Create the controlled identity and verified opening state for an asset.</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-6">
                    <Section title="Identity">
                        <SelectField label="Branch" error={form.errors.branch_id} value={form.data.branch_id} onChange={(value) => { form.setData('branch_id', value); form.setData('default_location_id', ''); }} options={branches} />
                        <SelectField label="Category" error={form.errors.equipment_category_id} value={form.data.equipment_category_id} onChange={selectCategory} options={activeCategories} />
                        <Field label="Asset code" error={form.errors.asset_code}><Input value={form.data.asset_code} onChange={(e) => form.setData('asset_code', e.target.value.toUpperCase())} /></Field>
                        <Field label="Asset name" error={form.errors.name}><Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></Field>
                        <Field label="Make" error={form.errors.make}><Input value={form.data.make} onChange={(e) => form.setData('make', e.target.value)} /></Field>
                        <Field label="Model" error={form.errors.model}><Input value={form.data.model} onChange={(e) => form.setData('model', e.target.value)} /></Field>
                        <Field label="Model year" error={form.errors.model_year}><Input type="number" min="1900" max="2100" value={form.data.model_year} onChange={(e) => form.setData('model_year', e.target.value)} /></Field>
                        <Field label="Serial number" error={form.errors.serial_number}><Input value={form.data.serial_number} onChange={(e) => form.setData('serial_number', e.target.value)} /></Field>
                        <Field label="Registration number" error={form.errors.registration_number}><Input value={form.data.registration_number} onChange={(e) => form.setData('registration_number', e.target.value)} /></Field>
                        <Field label="Chassis / VIN" error={form.errors.chassis_number}><Input value={form.data.chassis_number} onChange={(e) => form.setData('chassis_number', e.target.value)} /></Field>
                    </Section>

                    <Section title="Ownership and capacity">
                        <Field label="Ownership" error={form.errors.ownership_type}><NativeSelect value={form.data.ownership_type} onChange={(e) => form.setData('ownership_type', e.target.value)}>{['owned', 'leased', 'hired', 'subcontractor'].map((value) => <NativeSelectOption key={value} value={value}>{title(value)}</NativeSelectOption>)}</NativeSelect></Field>
                        <SelectField label="Registered owner" error={form.errors.owner_customer_id} value={form.data.owner_customer_id} onChange={(value) => form.setData('owner_customer_id', value)} options={ownerOptions} optional />
                        <Field label="Owner snapshot" error={form.errors.owner_name}><Input value={form.data.owner_name} onChange={(e) => form.setData('owner_name', e.target.value)} /></Field>
                        <Field label="Capacity" error={form.errors.capacity_value}><Input type="number" min="0" step="0.0001" value={form.data.capacity_value} onChange={(e) => form.setData('capacity_value', e.target.value)} /></Field>
                        <Field label="Capacity unit" error={form.errors.capacity_unit}><Input placeholder="tonnes, litres, kVA" value={form.data.capacity_unit} onChange={(e) => form.setData('capacity_unit', e.target.value)} /></Field>
                        <SelectField label="Default location" error={form.errors.default_location_id} value={form.data.default_location_id} onChange={(value) => form.setData('default_location_id', value)} options={locationOptions} optional />
                    </Section>

                    <Section title="Meter and fuel baseline">
                        <Field label="Meter type" error={form.errors.meter_type}><NativeSelect value={form.data.meter_type} onChange={(e) => form.setData('meter_type', e.target.value)}><NativeSelectOption value="odometer_km">Odometer (km)</NativeSelectOption><NativeSelectOption value="engine_hours">Engine hours</NativeSelectOption><NativeSelectOption value="operating_hours">Operating hours</NativeSelectOption><NativeSelectOption value="none">No meter</NativeSelectOption></NativeSelect></Field>
                        {form.data.meter_type !== 'none' && <><Field label="Opening reading" error={form.errors.starting_meter_reading}><Input type="number" min="0" step="0.0001" value={form.data.starting_meter_reading} onChange={(e) => form.setData('starting_meter_reading', e.target.value)} /></Field><Field label="Reading date" error={form.errors.starting_meter_date}><Input type="date" value={form.data.starting_meter_date} onChange={(e) => form.setData('starting_meter_date', e.target.value)} /></Field></>}
                        <Field label="Fuel basis" error={form.errors.fuel_efficiency_basis}><NativeSelect value={form.data.fuel_efficiency_basis} onChange={(e) => form.setData('fuel_efficiency_basis', e.target.value)}><NativeSelectOption value="">Not set</NativeSelectOption><NativeSelectOption value="litres_per_hour">Litres per hour</NativeSelectOption><NativeSelectOption value="litres_per_100km">Litres per 100 km</NativeSelectOption></NativeSelect></Field>
                        <Field label="Expected consumption" error={form.errors.expected_fuel_efficiency}><Input type="number" min="0" step="0.0001" value={form.data.expected_fuel_efficiency} onChange={(e) => form.setData('expected_fuel_efficiency', e.target.value)} /></Field>
                        <Field label="Tolerance %" error={form.errors.fuel_tolerance_percent}><Input type="number" min="0" max="100" step="0.01" value={form.data.fuel_tolerance_percent} onChange={(e) => form.setData('fuel_tolerance_percent', e.target.value)} /></Field>
                        <Field label="Tank capacity (L)" error={form.errors.tank_capacity}><Input type="number" min="0" step="0.01" value={form.data.tank_capacity} onChange={(e) => form.setData('tank_capacity', e.target.value)} /></Field>
                    </Section>

                    {canViewCosts && <Section title="Commercial details">
                        <Field label="Acquisition date" error={form.errors.acquired_on}><Input type="date" value={form.data.acquired_on} onChange={(e) => form.setData('acquired_on', e.target.value)} /></Field>
                        <Field label="Acquisition amount" error={form.errors.acquisition_amount}><Input type="number" min="0" step="0.0001" value={form.data.acquisition_amount} onChange={(e) => form.setData('acquisition_amount', e.target.value)} /></Field>
                        <SelectField label="Currency" error={form.errors.acquisition_currency_code} value={form.data.acquisition_currency_code} onChange={(value) => form.setData('acquisition_currency_code', value)} options={currencies} optional />
                        <Field label="Hire rate" error={form.errors.hire_rate}><Input type="number" min="0" step="0.0001" value={form.data.hire_rate} onChange={(e) => form.setData('hire_rate', e.target.value)} /></Field>
                        <Field label="Rate basis" error={form.errors.hire_rate_basis}><NativeSelect value={form.data.hire_rate_basis} onChange={(e) => form.setData('hire_rate_basis', e.target.value)}><NativeSelectOption value="">Not set</NativeSelectOption>{['hour', 'day', 'week', 'month'].map((value) => <NativeSelectOption key={value} value={value}>{title(value)}</NativeSelectOption>)}</NativeSelect></Field>
                    </Section>}

                    <Field label="Condition summary" error={form.errors.condition_summary}><Textarea value={form.data.condition_summary} onChange={(e) => form.setData('condition_summary', e.target.value)} /></Field>
                    <div className="flex justify-end gap-3"><Button type="button" variant="outline" onClick={() => setOpen(false)}>Cancel</Button><Button type="submit" disabled={form.processing}>{form.processing && <Spinner />}Save asset</Button></div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Section({ title: heading, children }: { title: string; children: ReactNode }) { return <section className="grid gap-4 border-t pt-5 first:border-t-0 first:pt-0"><h3 className="text-sm font-semibold">{heading}</h3><div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{children}</div></section>; }
function SelectField({ label, error, value, onChange, options, optional = false }: { label: string; error?: string; value: string; onChange: (value: string) => void; options: Array<{ id: string; name: string }>; optional?: boolean }) { return <Field label={label} error={error}><SearchableSelect value={value} onValueChange={onChange} options={[...(optional ? [{ value: '', label: 'None' }] : []), ...options.map((option) => ({ value: option.id, label: option.name }))]} placeholder="Select option" /></Field>; }
function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) { return <div className="grid min-w-0 gap-2"><Label>{label}</Label>{children}<InputError message={error} /></div>; }
function title(value: string) { return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()); }
