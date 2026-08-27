import { useForm } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
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
import { Textarea } from '@/components/ui/textarea';

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
                        opposite movement will be recorded.
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
                        Record reversal
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
