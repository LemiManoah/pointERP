import { useForm } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { EquipmentMeterReading } from '../types';

export function MeterCorrectionReviewDialog({
    reading,
    action,
}: {
    reading: EquipmentMeterReading;
    action: 'approve' | 'reject';
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ decision_note: '' });
    const approving = action === 'approve';

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/equipment-meter-readings/${reading.id}/${action}`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant={approving ? 'default' : 'destructive'}
                >
                    {approving ? <Check /> : <X />}
                    {approving ? 'Approve' : 'Reject'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {approving ? 'Approve' : 'Reject'} meter correction
                    </DialogTitle>
                    <DialogDescription>
                        {reading.corrected_value ?? 'Original'} →{' '}
                        {reading.reading_value}. Reason: {reading.reason}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-2">
                        <Label>
                            Decision note {approving ? '(optional)' : ''}
                        </Label>
                        <Textarea
                            value={form.data.decision_note}
                            onChange={(event) =>
                                form.setData(
                                    'decision_note',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.decision_note} />
                    </div>
                    <div className="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant={approving ? 'default' : 'destructive'}
                            disabled={form.processing}
                        >
                            {form.processing && <Spinner />}
                            {approving
                                ? 'Approve correction'
                                : 'Reject correction'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
