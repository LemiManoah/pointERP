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
import { CountryForm, type CurrencyOption } from './partials/country-form';

type Props = {
    currencies: CurrencyOption[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Countries', href: '/foundation/countries' },
    { title: 'New country', href: '/foundation/countries/create' },
];

export default function CountriesCreate({ currencies }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New country" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        New country
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Add a country reference for tenant and branch setup.
                    </p>
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Country details</CardTitle>
                        <CardDescription>
                            Country defaults help seed currency settings.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <CountryForm currencies={currencies} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
