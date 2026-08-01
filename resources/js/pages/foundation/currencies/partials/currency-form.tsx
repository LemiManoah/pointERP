import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export type CurrencyFormData = Record<string, string | number | boolean> & {
    code: string;
    name: string;
    symbol: string;
    decimal_places: number;
    is_active: boolean;
};

type Props = {
    currency?: CurrencyFormData;
    onCancel?: () => void;
    onSuccess?: () => void;
};

export function CurrencyForm({ currency, onCancel, onSuccess }: Props) {
    const form = useForm<CurrencyFormData>({
        code: currency?.code ?? '',
        name: currency?.name ?? '',
        symbol: currency?.symbol ?? '',
        decimal_places: currency?.decimal_places ?? 2,
        is_active: currency?.is_active ?? true,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (currency) {
            form.put(`/foundation/currencies/${currency.code}`, {
                onSuccess,
            });

            return;
        }

        form.post('/foundation/currencies', {
            onSuccess: () => {
                form.reset();
                onSuccess?.();
            },
        });
    }

    return (
        <form onSubmit={submit} className="grid gap-5">
            <div className="grid gap-2">
                <Label htmlFor="code">Code</Label>
                <Input
                    id="code"
                    value={form.data.code}
                    maxLength={3}
                    disabled={Boolean(currency)}
                    onChange={(event) =>
                        form.setData(
                            'code',
                            event.target.value.toUpperCase(),
                        )
                    }
                    placeholder="UGX"
                />
                <InputError message={form.errors.code} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    placeholder="Ugandan Shilling"
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="symbol">Symbol</Label>
                <Input
                    id="symbol"
                    value={form.data.symbol}
                    onChange={(event) =>
                        form.setData('symbol', event.target.value)
                    }
                    placeholder="UGX"
                />
                <InputError message={form.errors.symbol} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="decimal_places">Decimal places</Label>
                <Input
                    id="decimal_places"
                    type="number"
                    min={0}
                    max={4}
                    value={form.data.decimal_places}
                    onChange={(event) =>
                        form.setData(
                            'decimal_places',
                            Number(event.target.value),
                        )
                    }
                />
                <InputError message={form.errors.decimal_places} />
            </div>

            <label className="flex items-center gap-3 text-sm">
                <Checkbox
                    checked={form.data.is_active}
                    onCheckedChange={(checked) =>
                        form.setData('is_active', checked === true)
                    }
                />
                Active
            </label>

            <div className="flex items-center gap-3">
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Save currency
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => {
                        if (onCancel) {
                            onCancel();

                            return;
                        }

                        window.location.href = '/foundation/currencies';
                    }}
                >
                    Cancel
                </Button>
            </div>
        </form>
    );
}
