import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';

export type CountryFormData = Record<string, string | boolean> & {
    code: string;
    name: string;
    iso3_code: string;
    default_currency_code: string;
    is_active: boolean;
};

export type CurrencyOption = {
    code: string;
    name: string;
};

type Props = {
    country?: CountryFormData;
    currencies: CurrencyOption[];
    onCancel?: () => void;
    onSuccess?: () => void;
};

export function CountryForm({ country, currencies, onCancel, onSuccess }: Props) {
    const form = useForm<CountryFormData>({
        code: country?.code ?? '',
        name: country?.name ?? '',
        iso3_code: country?.iso3_code ?? '',
        default_currency_code:
            country?.default_currency_code ?? currencies[0]?.code ?? '',
        is_active: country?.is_active ?? true,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (country) {
            form.put(`/foundation/countries/${country.code}`, {
                onSuccess,
            });

            return;
        }

        form.post('/foundation/countries', {
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
                    maxLength={2}
                    disabled={Boolean(country)}
                    onChange={(event) =>
                        form.setData(
                            'code',
                            event.target.value.toUpperCase(),
                        )
                    }
                    placeholder="UG"
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
                    placeholder="Uganda"
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="iso3_code">ISO3 code</Label>
                <Input
                    id="iso3_code"
                    value={form.data.iso3_code}
                    maxLength={3}
                    onChange={(event) =>
                        form.setData(
                            'iso3_code',
                            event.target.value.toUpperCase(),
                        )
                    }
                    placeholder="UGA"
                />
                <InputError message={form.errors.iso3_code} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="default_currency_code">
                    Default currency
                </Label>
                <NativeSelect
                    id="default_currency_code"
                    value={form.data.default_currency_code}
                    onChange={(event) =>
                        form.setData(
                            'default_currency_code',
                            event.target.value,
                        )
                    }
                    className="w-full"
                >
                    {currencies.map((currency) => (
                        <NativeSelectOption
                            key={currency.code}
                            value={currency.code}
                        >
                            {currency.code} - {currency.name}
                        </NativeSelectOption>
                    ))}
                </NativeSelect>
                <InputError message={form.errors.default_currency_code} />
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
                    Save country
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => {
                        if (onCancel) {
                            onCancel();

                            return;
                        }

                        window.location.href = '/foundation/countries';
                    }}
                >
                    Cancel
                </Button>
            </div>
        </form>
    );
}
