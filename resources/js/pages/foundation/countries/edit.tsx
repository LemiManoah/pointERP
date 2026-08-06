import { Head } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {
    CountryForm,
    type CountryFormData,
    type CurrencyOption,
} from './partials/country-form';

type Props = {
    country: CountryFormData;
    currencies: CurrencyOption[];
};

export default function CountriesEdit({ country, currencies }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Countries', href: '/countries' },
        {
            title: country.code,
            href: `/countries/${country.code}/edit`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${country.code}`} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Edit country
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Update the reference record used by setup workflows.
                    </p>
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>{country.name}</CardTitle>
                        <CardDescription>
                            Country codes should stay aligned with ISO
                            standards.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <CountryForm
                            country={country}
                            currencies={currencies}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
