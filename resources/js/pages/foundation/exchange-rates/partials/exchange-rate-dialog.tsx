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
    isMultiBranch: boolean;
    defaultBranchId: string | null;
    canManageFacilityWide: boolean;
};

export function ExchangeRateDialog({
    exchangeRate,
    branches,
    currencies,
    isMultiBranch,
    defaultBranchId,
    canManageFacilityWide,
}: Props) {
    const [open, setOpen] = useState(false);
    const isEditing = Boolean(exchangeRate);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={isEditing ? 'outline' : 'default'}
                    size={isEditing ? 'sm' : 'default'}
                    disabled={
                        isEditing &&
                        (exchangeRate?.status !== 'draft' ||
                            (exchangeRate.branch_id === null &&
                                isMultiBranch &&
                                !canManageFacilityWide))
                    }
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
                    isMultiBranch={isMultiBranch}
                    defaultBranchId={defaultBranchId}
                    canManageFacilityWide={canManageFacilityWide}
                    onCancel={() => setOpen(false)}
                    onSuccess={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
