import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { EquipmentCategoryDialog } from './partials/equipment-category-dialog';
import { EquipmentDialog } from './partials/equipment-dialog';
import { EquipmentLocationDialog } from './partials/equipment-location-dialog';
import type {
    BranchOption,
    EquipmentCategory,
    EquipmentLocation,
    EquipmentRecord,
    Option,
    OwnerOption,
    ProjectOption,
    SiteOption,
    StaffOption,
} from './types';

type Props = {
    activeTab: string;
    equipment: EquipmentRecord[];
    categories: EquipmentCategory[];
    locations: EquipmentLocation[];
    branches: BranchOption[];
    projects: ProjectOption[];
    sites: SiteOption[];
    staff: StaffOption[];
    owners: OwnerOption[];
    currencies: Option[];
    can: {
        create: boolean;
        update: boolean;
        retire: boolean;
        manageCategories: boolean;
        manageLocations: boolean;
        viewCosts: boolean;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Equipment', href: '/equipment' },
];

export default function EquipmentIndex(props: Props) {
    const {
        equipment,
        categories,
        locations,
        branches,
        projects,
        sites,
        owners,
        currencies,
        can,
    } = props;
    const confirm = useConfirmDialog();
    const [tab, setTab] = useState(
        ['register', 'categories', 'locations'].includes(props.activeTab)
            ? props.activeTab
            : 'register',
    );
    const [status, setStatus] = useState('active');
    const [search, setSearch] = useState('');
    const debouncedSearch = useDebouncedValue(search);
    const term = debouncedSearch.trim().toLowerCase();
    const rows = useMemo(() => {
        const source =
            tab === 'register'
                ? equipment
                : tab === 'categories'
                  ? categories
                  : locations;
        return source.filter(
            (record) =>
                record.is_active === (status === 'active') &&
                (!term ||
                    Object.values(record)
                        .join(' ')
                        .toLowerCase()
                        .includes(term)),
        );
    }, [categories, equipment, locations, status, tab, term]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Equipment" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="grid gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold">
                                Equipment
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Controlled fleet identities, classifications and
                                operational locations.
                            </p>
                        </div>
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder={`Search ${tab}`}
                                className="w-full pl-9 sm:w-80"
                            />
                        </div>
                    </div>
                    {tab === 'register' && can.create && (
                        <EquipmentDialog
                            branches={branches}
                            categories={categories}
                            locations={locations}
                            owners={owners}
                            currencies={currencies}
                            canViewCosts={can.viewCosts}
                        />
                    )}
                    {tab === 'categories' && can.manageCategories && (
                        <EquipmentCategoryDialog />
                    )}
                    {tab === 'locations' && can.manageLocations && (
                        <EquipmentLocationDialog
                            branches={branches}
                            projects={projects}
                            sites={sites}
                        />
                    )}
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Tabs value={tab} onValueChange={setTab}>
                        <TabsList>
                            <TabsTrigger value="register">Register</TabsTrigger>
                            <TabsTrigger value="categories">
                                Categories
                            </TabsTrigger>
                            <TabsTrigger value="locations">
                                Locations
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                    <Tabs value={status} onValueChange={setStatus}>
                        <TabsList>
                            <TabsTrigger value="active">Active</TabsTrigger>
                            <TabsTrigger value="inactive">Inactive</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {tab === 'register'
                                ? 'Asset register'
                                : tab === 'categories'
                                  ? 'Equipment categories'
                                  : 'Operational locations'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {tab === 'register' && (
                            <RegisterTable
                                rows={rows as EquipmentRecord[]}
                                {...{
                                    branches,
                                    categories,
                                    locations,
                                    owners,
                                    currencies,
                                    can,
                                    confirm,
                                }}
                            />
                        )}
                        {tab === 'categories' && (
                            <CategoryTable
                                rows={rows as EquipmentCategory[]}
                                canManage={can.manageCategories}
                                confirm={confirm}
                            />
                        )}
                        {tab === 'locations' && (
                            <LocationTable
                                rows={rows as EquipmentLocation[]}
                                canManage={can.manageLocations}
                                branches={branches}
                                projects={projects}
                                sites={sites}
                                confirm={confirm}
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function RegisterTable({
    rows,
    branches,
    categories,
    locations,
    owners,
    currencies,
    can,
    confirm,
}: {
    rows: EquipmentRecord[];
    branches: BranchOption[];
    categories: EquipmentCategory[];
    locations: EquipmentLocation[];
    owners: OwnerOption[];
    currencies: Option[];
    can: Props['can'];
    confirm: ReturnType<typeof useConfirmDialog>;
}) {
    return (
        <Table
            headers={[
                'Asset',
                'Category',
                'Ownership',
                'Location / custodian',
                'Meter',
                'Status',
                '',
            ]}
        >
            {rows.map((asset) => (
                <tr key={asset.id} className="border-b last:border-0">
                    <td className="py-3 pr-4">
                        <Link
                            href={`/equipment/${asset.id}`}
                            className="font-medium hover:underline"
                        >
                            {asset.asset_code}
                        </Link>
                        <div className="text-muted-foreground">
                            {asset.name}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        {asset.category_name}
                        <div className="text-muted-foreground">
                            {asset.branch_name}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        <Badge variant="outline">
                            {title(asset.ownership_type)}
                        </Badge>
                        <div className="mt-1 text-muted-foreground">
                            {asset.owner_name ?? 'Tenant asset'}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        {asset.current_location_name ??
                            asset.default_location_name ??
                            'Not set'}
                        <div className="text-muted-foreground">
                            {asset.current_custodian_name ?? 'No custodian'}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        {asset.current_meter_reading
                            ? formatNumber(asset.current_meter_reading)
                            : 'None'}
                        <div className="text-muted-foreground">
                            {title(asset.meter_type)}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        <Badge
                            variant={
                                asset.current_status === 'out_of_service' ||
                                asset.current_status === 'retired'
                                    ? 'destructive'
                                    : 'secondary'
                            }
                        >
                            {title(asset.current_status)}
                        </Badge>
                    </td>
                    <td className="py-3">
                        <div className="flex justify-end gap-2">
                            {can.update && (
                                <EquipmentDialog
                                    equipment={asset}
                                    branches={branches}
                                    categories={categories}
                                    locations={locations}
                                    owners={owners}
                                    currencies={currencies}
                                    canViewCosts={can.viewCosts}
                                />
                            )}
                            {can.retire && (
                                <Button
                                    size="sm"
                                    variant={
                                        asset.is_active
                                            ? 'destructive'
                                            : 'secondary'
                                    }
                                    onClick={() =>
                                        confirm({
                                            title: asset.is_active
                                                ? 'Retire equipment?'
                                                : 'Restore equipment?',
                                            description: `${asset.asset_code} will move to the ${asset.is_active ? 'inactive' : 'active'} register.`,
                                            confirmLabel: asset.is_active
                                                ? 'Retire'
                                                : 'Restore',
                                            variant: asset.is_active
                                                ? 'destructive'
                                                : 'default',
                                            onConfirm: () =>
                                                router.delete(
                                                    `/equipment/${asset.id}`,
                                                    { preserveScroll: true },
                                                ),
                                        })
                                    }
                                >
                                    {asset.is_active ? 'Retire' : 'Restore'}
                                </Button>
                            )}
                        </div>
                    </td>
                </tr>
            ))}
            {rows.length === 0 && <Empty colSpan={7} />}
        </Table>
    );
}

function CategoryTable({
    rows,
    canManage,
    confirm,
}: {
    rows: EquipmentCategory[];
    canManage: boolean;
    confirm: ReturnType<typeof useConfirmDialog>;
}) {
    return (
        <Table headers={['Category', 'Meter', 'Capacity', 'Fuel control', '']}>
            {rows.map((category) => (
                <tr key={category.id} className="border-b last:border-0">
                    <td className="py-3 pr-4">
                        <div className="font-medium">{category.name}</div>
                        <div className="text-muted-foreground">
                            {category.code}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        {title(category.default_meter_type)}
                    </td>
                    <td className="py-3 pr-4">
                        {category.default_capacity_unit ?? 'Not set'}
                    </td>
                    <td className="py-3 pr-4">
                        {category.fuel_efficiency_basis
                            ? `${formatNumber(category.expected_fuel_efficiency)} ${title(category.fuel_efficiency_basis)}`
                            : 'Not set'}
                    </td>
                    <td className="py-3">
                        <div className="flex justify-end gap-2">
                            {canManage && (
                                <>
                                    <EquipmentCategoryDialog
                                        category={category}
                                    />
                                    <Button
                                        size="sm"
                                        variant={
                                            category.is_active
                                                ? 'destructive'
                                                : 'secondary'
                                        }
                                        onClick={() =>
                                            confirm({
                                                title: category.is_active
                                                    ? 'Deactivate category?'
                                                    : 'Activate category?',
                                                description: `${category.name} will move to the ${category.is_active ? 'inactive' : 'active'} list.`,
                                                confirmLabel: category.is_active
                                                    ? 'Deactivate'
                                                    : 'Activate',
                                                variant: category.is_active
                                                    ? 'destructive'
                                                    : 'default',
                                                onConfirm: () =>
                                                    router.delete(
                                                        `/equipment-categories/${category.id}`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    ),
                                            })
                                        }
                                    >
                                        {category.is_active
                                            ? 'Deactivate'
                                            : 'Activate'}
                                    </Button>
                                </>
                            )}
                        </div>
                    </td>
                </tr>
            ))}
            {rows.length === 0 && <Empty colSpan={5} />}
        </Table>
    );
}

function LocationTable({
    rows,
    canManage,
    branches,
    projects,
    sites,
    confirm,
}: {
    rows: EquipmentLocation[];
    canManage: boolean;
    branches: BranchOption[];
    projects: ProjectOption[];
    sites: SiteOption[];
    confirm: ReturnType<typeof useConfirmDialog>;
}) {
    return (
        <Table headers={['Location', 'Type', 'Branch', 'Project / site', '']}>
            {rows.map((location) => (
                <tr key={location.id} className="border-b last:border-0">
                    <td className="py-3 pr-4">
                        <div className="font-medium">{location.name}</div>
                        <div className="text-muted-foreground">
                            {location.code}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        <Badge variant="outline">{title(location.type)}</Badge>
                    </td>
                    <td className="py-3 pr-4">{location.branch_name}</td>
                    <td className="py-3 pr-4">
                        {location.project_name ?? 'Not project-linked'}
                        <div className="text-muted-foreground">
                            {location.site_name ?? location.address ?? ''}
                        </div>
                    </td>
                    <td className="py-3">
                        <div className="flex justify-end gap-2">
                            {canManage && (
                                <>
                                    <EquipmentLocationDialog
                                        location={location}
                                        branches={branches}
                                        projects={projects}
                                        sites={sites}
                                    />
                                    <Button
                                        size="sm"
                                        variant={
                                            location.is_active
                                                ? 'destructive'
                                                : 'secondary'
                                        }
                                        onClick={() =>
                                            confirm({
                                                title: location.is_active
                                                    ? 'Deactivate location?'
                                                    : 'Activate location?',
                                                description: `${location.name} will move to the ${location.is_active ? 'inactive' : 'active'} list.`,
                                                confirmLabel: location.is_active
                                                    ? 'Deactivate'
                                                    : 'Activate',
                                                variant: location.is_active
                                                    ? 'destructive'
                                                    : 'default',
                                                onConfirm: () =>
                                                    router.delete(
                                                        `/equipment-locations/${location.id}`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    ),
                                            })
                                        }
                                    >
                                        {location.is_active
                                            ? 'Deactivate'
                                            : 'Activate'}
                                    </Button>
                                </>
                            )}
                        </div>
                    </td>
                </tr>
            ))}
            {rows.length === 0 && <Empty colSpan={5} />}
        </Table>
    );
}

function Table({
    headers,
    children,
}: {
    headers: string[];
    children: React.ReactNode;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        {headers.map((header, index) => (
                            <th
                                key={index}
                                className={`py-3 font-medium ${index === headers.length - 1 ? 'text-right' : 'pr-4'}`}
                            >
                                {header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>{children}</tbody>
            </table>
        </div>
    );
}
function Empty({ colSpan }: { colSpan: number }) {
    return (
        <tr>
            <td
                colSpan={colSpan}
                className="py-10 text-center text-muted-foreground"
            >
                No records match the current tab and search.
            </td>
        </tr>
    );
}
function title(value: string) {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
