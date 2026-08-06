import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export type ExchangeRate = {
    id: string;
    branch_id: string | null;
    branch_name: string | null;
    from_currency_code: string;
    to_currency_code: string;
    rate: string;
    effective_date: string;
    expires_at: string | null;
    status: 'draft' | 'approved' | 'superseded';
};

export type BranchOption = {
    id: string;
    name: string;
    code: string;
};

export type CurrencyOption = {
    code: string;
    name: string;
};

type ExchangeRateFormData = Record<string, string> & {
    branch_id: string;
    from_currency_code: string;
    to_currency_code: string;
    rate: string;
    effective_date: string;
    expires_at: string;
};

type Props = {
    exchangeRate?: ExchangeRate;
    branches: BranchOption[];
    currencies: CurrencyOption[];
    onCancel?: () => void;
    onSuccess?: () => void;
};

export function ExchangeRateForm({
    exchangeRate,
    branches,
    currencies,
    onCancel,
    onSuccess,
}: Props) {
    const form = useForm<ExchangeRateFormData>({
        branch_id: exchangeRate?.branch_id ?? '__tenant__',
        from_currency_code:
            exchangeRate?.from_currency_code ?? currencies[0]?.code ?? '',
        to_currency_code:
            exchangeRate?.to_currency_code ?? currencies[1]?.code ?? '',
        rate: exchangeRate?.rate ?? '',
        effective_date:
            exchangeRate?.effective_date ??
            new Date().toISOString().slice(0, 10),
        expires_at: exchangeRate?.expires_at ?? '',
    });

    const currencyOptions = currencies.map((currency) => ({
        value: currency.code,
        label: currency.code,
        description: currency.name,
    }));

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (exchangeRate) {
            form.put(`/exchange-rates/${exchangeRate.id}`, {
                onSuccess,
            });

            return;
        }

        form.post('/exchange-rates', {
            onSuccess: () => {
                form.reset();
                onSuccess?.();
            },
        });
    }

    return (
        <form onSubmit={submit} className="grid gap-5">
            <div className="grid gap-2">
                <Label>Scope</Label>
                <SearchableSelect
                    value={form.data.branch_id}
                    onValueChange={(value) => form.setData('branch_id', value)}
                    options={[
                        {
                            value: '__tenant__',
                            label: 'Tenant-wide',
                            description: 'Applies unless a branch rate exists',
                        },
                        ...branches.map((branch) => ({
                            value: branch.id,
                            label: branch.name,
                            description: branch.code,
                        })),
                    ]}
                    placeholder="Select scope"
                />
                <InputError message={form.errors.branch_id} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label>From currency</Label>
                    <SearchableSelect
                        value={form.data.from_currency_code}
                        onValueChange={(value) =>
                            form.setData('from_currency_code', value)
                        }
                        options={currencyOptions}
                        placeholder="From"
                    />
                    <InputError message={form.errors.from_currency_code} />
                </div>
                <div className="grid gap-2">
                    <Label>To currency</Label>
                    <SearchableSelect
                        value={form.data.to_currency_code}
                        onValueChange={(value) =>
                            form.setData('to_currency_code', value)
                        }
                        options={currencyOptions}
                        placeholder="To"
                    />
                    <InputError message={form.errors.to_currency_code} />
                </div>
            </div>

            <div className="rounded-md border bg-muted/30 p-3 text-sm">
                1 {form.data.from_currency_code || 'FROM'} ={' '}
                {form.data.rate || 'rate'} {form.data.to_currency_code || 'TO'}
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="rate">Rate</Label>
                    <Input
                        id="rate"
                        type="number"
                        min="0"
                        step="0.0000000001"
                        value={form.data.rate}
                        onChange={(event) =>
                            form.setData('rate', event.target.value)
                        }
                    />
                    <InputError message={form.errors.rate} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="effective_date">Effective date</Label>
                    <Input
                        id="effective_date"
                        type="date"
                        value={form.data.effective_date}
                        onChange={(event) =>
                            form.setData('effective_date', event.target.value)
                        }
                    />
                    <InputError message={form.errors.effective_date} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="expires_at">Expires at</Label>
                <Input
                    id="expires_at"
                    type="datetime-local"
                    value={form.data.expires_at}
                    onChange={(event) =>
                        form.setData('expires_at', event.target.value)
                    }
                />
                <InputError message={form.errors.expires_at} />
            </div>

            <div className="flex justify-end gap-3">
                <Button type="button" variant="outline" onClick={onCancel}>
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Save draft
                </Button>
            </div>
        </form>
    );
}
