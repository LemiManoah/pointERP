import { useForm } from '@inertiajs/react';
import { MapPinCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { EquipmentLocation, EquipmentRecord } from '../types';

function localNow(): string {
    const now = new Date();
    return new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 16);
}

export function EquipmentLocationConfirmationDialog({ equipment, locations }: { equipment: EquipmentRecord; locations: EquipmentLocation[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ equipment_location_id: '', observed_at: localNow(), latitude: '', longitude: '', observed_status: equipment.current_status, condition_observation: equipment.condition_summary ?? '', note: '' });
    const options = locations.filter((location) => location.is_active && location.branch_id === equipment.branch_id);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/equipment/${equipment.id}/location-confirmations`, { preserveScroll: true, onSuccess: () => setOpen(false) });
    }

    return <Dialog open={open} onOpenChange={setOpen}>
        <DialogTrigger asChild><Button variant="outline"><MapPinCheck /> Confirm location</Button></DialogTrigger>
        <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
            <DialogHeader><DialogTitle>Confirm last known location</DialogTitle><DialogDescription>Record a physical observation without creating an assignment or transfer.</DialogDescription></DialogHeader>
            <form onSubmit={submit} className="grid gap-5">
                <div className="grid gap-2"><Label>Observed location</Label><SearchableSelect value={form.data.equipment_location_id} onValueChange={(value) => form.setData('equipment_location_id', value)} options={options.map((location) => ({ value: location.id, label: location.name, description: location.code }))} /><InputError message={form.errors.equipment_location_id} /></div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2"><Label>Observed at</Label><Input type="datetime-local" value={form.data.observed_at} onChange={(event) => form.setData('observed_at', event.target.value)} /><InputError message={form.errors.observed_at} /></div>
                    <div className="grid gap-2"><Label>Observed status</Label><NativeSelect value={form.data.observed_status} onChange={(event) => form.setData('observed_status', event.target.value)}>{['available', 'assigned', 'idle', 'under_maintenance', 'out_of_service'].map((value) => <NativeSelectOption key={value} value={value}>{value.replaceAll('_', ' ')}</NativeSelectOption>)}</NativeSelect><InputError message={form.errors.observed_status} /></div>
                    <div className="grid gap-2"><Label>Latitude</Label><Input type="number" step="0.0000001" value={form.data.latitude} onChange={(event) => form.setData('latitude', event.target.value)} /><InputError message={form.errors.latitude} /></div>
                    <div className="grid gap-2"><Label>Longitude</Label><Input type="number" step="0.0000001" value={form.data.longitude} onChange={(event) => form.setData('longitude', event.target.value)} /><InputError message={form.errors.longitude} /></div>
                </div>
                <div className="grid gap-2"><Label>Condition observed</Label><Textarea value={form.data.condition_observation} onChange={(event) => form.setData('condition_observation', event.target.value)} /><InputError message={form.errors.condition_observation} /></div>
                <div className="grid gap-2"><Label>Note</Label><Textarea value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} /><InputError message={form.errors.note} /></div>
                <div className="flex justify-end gap-3"><Button type="button" variant="outline" onClick={() => setOpen(false)}>Cancel</Button><Button type="submit" disabled={form.processing}>{form.processing && <Spinner />}Save confirmation</Button></div>
            </form>
        </DialogContent>
    </Dialog>;
}
