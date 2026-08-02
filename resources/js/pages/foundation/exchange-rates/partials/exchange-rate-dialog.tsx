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
    ExchangeRateForm,
    type BranchOption,
    type CurrencyOption,
    type ExchangeRate,
} from './exchange-rate-form';

type Props = {
    exchangeRate?: ExchangeRate;
    branches: BranchOption[];
    currencies: CurrencyOption[];
};

export function ExchangeRateDialog({
    exchangeRate,
    branches,
    currencies,
}: Props) {
    const [open, setOpen] = useState(false);
    const isEditing = Boolean(exchangeRate);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={isEditing ? 'outline' : 'default'}
                    size={isEditing ? 'sm' : 'default'}
                    disabled={isEditing && exchangeRate?.status !== 'draft'}
                >
                    {isEditing ? <Pencil /> : <Plus />}
                    {isEditing ? 'Edit' : 'New rate'}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit draft rate' : 'New exchange rate'}
                    </DialogTitle>
                    <DialogDescription>
                        Store manual rates as dated drafts before approval.
                    </DialogDescription>
                </DialogHeader>
                <ExchangeRateForm
                    exchangeRate={exchangeRate}
                    branches={branches}
                    currencies={currencies}
                    onCancel={() => setOpen(false)}
                    onSuccess={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
