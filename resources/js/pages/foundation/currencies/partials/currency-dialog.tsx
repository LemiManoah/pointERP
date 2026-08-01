import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { CurrencyForm, type CurrencyFormData } from './currency-form';

type Props = {
    currency?: CurrencyFormData;
};

export function CurrencyDialog({ currency }: Props) {
    const [open, setOpen] = useState(false);
    const isEditing = Boolean(currency);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant={isEditing ? 'outline' : 'default'} size={isEditing ? 'sm' : 'default'}>
                    {isEditing ? <Pencil /> : <Plus />}
                    {isEditing ? 'Edit' : 'New currency'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? `Edit ${currency?.code}` : 'New currency'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update the currency details used by setup workflows.'
                            : 'Add a controlled ISO currency for tenant and branch setup.'}
                    </DialogDescription>
                </DialogHeader>
                <CurrencyForm
                    currency={currency}
                    onCancel={() => setOpen(false)}
                    onSuccess={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
