import { useForm } from '@inertiajs/react';
import { Gauge, PencilLine } from 'lucide-react';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { EquipmentMeterReading, EquipmentRecord } from '../types';

function localNow(): string {
    const now = new Date();
    return new Date(now.getTime() - now.getTimezoneOffset() * 60_000)
        .toISOString()
        .slice(0, 16);
}

export function MeterReadingDialog({
    equipment,
}: {
    equipment: EquipmentRecord;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        reading_value: '',
        read_at: localNow(),
        evidence_note: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/equipment/${equipment.id}/meter-readings`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('reading_value', 'evidence_note');
                setOpen(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Gauge />
                    Record reading
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Record meter reading</DialogTitle>
                    <DialogDescription>
                        {equipment.asset_code} ·{' '}
                        {meterLabel(equipment.meter_type)} · current{' '}
                        {equipment.current_meter_reading ?? 'none'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <Field label="Reading" error={form.errors.reading_value}>
                        <Input
                            type="number"
                            min="0"
                            step="0.0001"
                            value={form.data.reading_value}
                            onChange={(event) =>
                                form.setData(
                                    'reading_value',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <Field label="Observed at" error={form.errors.read_at}>
                        <Input
                            type="datetime-local"
                            value={form.data.read_at}
                            onChange={(event) =>
                                form.setData('read_at', event.target.value)
                            }
                        />
                    </Field>
                    <Field
                        label="Evidence note"
                        error={form.errors.evidence_note}
                    >
                        <Textarea
                            value={form.data.evidence_note}
                            onChange={(event) =>
                                form.setData(
                                    'evidence_note',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <div className="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}Save reading
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function MeterCorrectionDialog({
    reading,
}: {
    reading: EquipmentMeterReading;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        reading_value: reading.reading_value,
        reason: '',
        evidence_note: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/equipment-meter-readings/${reading.id}/corrections`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <PencilLine />
                    Correct
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Request meter correction</DialogTitle>
                    <DialogDescription>
                        The accepted value {reading.reading_value} remains in
                        history until another authorised user approves this
                        request.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <Field
                        label="Corrected reading"
                        error={form.errors.reading_value}
                    >
                        <Input
                            type="number"
                            min="0"
                            step="0.0001"
                            value={form.data.reading_value}
                            onChange={(event) =>
                                form.setData(
                                    'reading_value',
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
                    <Field
                        label="Evidence note"
                        error={form.errors.evidence_note}
                    >
                        <Textarea
                            value={form.data.evidence_note}
                            onChange={(event) =>
                                form.setData(
                                    'evidence_note',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <div className="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}Submit correction
                        </Button>
                    </div>
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
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
function meterLabel(value: string): string {
    return value.replaceAll('_', ' ');
}
