import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { CountryDialog } from './partials/country-dialog';
import type {
    CountryFormData,
    CurrencyOption,
} from './partials/country-form';

type Country = {
    code: string;
    name: string;
    iso3_code: string;
    default_currency_code: string;
    default_currency_name: string;
    is_active: boolean;
};

type Props = {
    countries: Country[];
    currencies: CurrencyOption[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Countries', href: '/foundation/countries' },
];

export default function CountriesIndex({ countries, currencies }: Props) {
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');
    const debouncedSearch = useDebouncedValue(search);

    const filteredCountries = useMemo(() => {
        const term = debouncedSearch.trim().toLowerCase();

        return countries.filter((country) => {
            const matchesStatus =
                status === 'all' ||
                (status === 'active' && country.is_active) ||
                (status === 'inactive' && !country.is_active);
            const matchesSearch =
                !term ||
                [
                    country.code,
                    country.name,
                    country.iso3_code,
                    country.default_currency_code,
                    country.default_currency_name,
                ]
                    .join(' ')
                    .toLowerCase()
                    .includes(term);

            return matchesStatus && matchesSearch;
        });
    }, [countries, debouncedSearch, status]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Countries" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Countries
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Global ISO country references seeded for the Point
                            ERP pilot.
                        </p>
                    </div>
                    <CountryDialog currencies={currencies} />
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <CardTitle>Reference countries</CardTitle>
                                <CardDescription>
                                    Country currencies are defaults for setup,
                                    not branch-level restrictions.
                                </CardDescription>
                            </div>
                            <div className="flex flex-col gap-3 sm:flex-row">
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Search countries"
                                        className="w-full pl-9 sm:w-64"
                                    />
                                </div>
                                <Select
                                    value={status}
                                    onValueChange={setStatus}
                                >
                                    <SelectTrigger className="w-full sm:w-36">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All</SelectItem>
                                        <SelectItem value="active">
                                            Active
                                        </SelectItem>
                                        <SelectItem value="inactive">
                                            Inactive
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-3 pr-4 font-medium">
                                            Code
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Country
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            ISO3
                                        </th>
                                        <th className="py-3 pr-4 font-medium">
                                            Default currency
                                        </th>
                                        <th className="py-3 font-medium">
                                            Status
                                        </th>
                                        <th className="py-3 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredCountries.map((country) => (
                                        <tr
                                            key={country.code}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4 font-medium">
                                                {country.code}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {country.name}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {country.iso3_code}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {country.default_currency_code}{' '}
                                                -{' '}
                                                {country.default_currency_name}
                                            </td>
                                            <td className="py-3">
                                                <Badge
                                                    variant={
                                                        country.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {country.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    <CountryDialog
                                                        country={
                                                            country as CountryFormData
                                                        }
                                                        currencies={currencies}
                                                    />
                                                    <Button
                                                        variant={
                                                            country.is_active
                                                                ? 'destructive'
                                                                : 'secondary'
                                                        }
                                                        size="sm"
                                                        onClick={() =>
                                                            router.delete(
                                                                `/foundation/countries/${country.code}`,
                                                                {
                                                                    preserveScroll:
                                                                        true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {country.is_active
                                                            ? 'Deactivate'
                                                            : 'Activate'}
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {filteredCountries.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No countries match the current
                                                filters.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
