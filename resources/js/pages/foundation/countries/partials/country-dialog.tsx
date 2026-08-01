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
import {
    CountryForm,
    type CountryFormData,
    type CurrencyOption,
} from './country-form';

type Props = {
    country?: CountryFormData;
    currencies: CurrencyOption[];
};

export function CountryDialog({ country, currencies }: Props) {
    const [open, setOpen] = useState(false);
    const isEditing = Boolean(country);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant={isEditing ? 'outline' : 'default'} size={isEditing ? 'sm' : 'default'}>
                    {isEditing ? <Pencil /> : <Plus />}
                    {isEditing ? 'Edit' : 'New country'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? `Edit ${country?.code}` : 'New country'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update the country reference used by setup workflows.'
                            : 'Add a country reference and default currency.'}
                    </DialogDescription>
                </DialogHeader>
                <CountryForm
                    country={country}
                    currencies={currencies}
                    onCancel={() => setOpen(false)}
                    onSuccess={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
