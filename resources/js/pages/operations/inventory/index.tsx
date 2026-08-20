import { Head, Link, router } from '@inertiajs/react';
import { Eye, Power, Search, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import AppLayout from '@/layouts/app-layout';
import { formatCurrencyAmount, formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import {
    CategoryDialog,
    ItemDialog,
    StoreDialog,
    UnitDialog,
} from './partials/inventory-dialogs';
import type { Category, Item, Option, Store, Unit } from './types';

type InventoryTab = 'items' | 'categories' | 'units' | 'stores';

type Props = {
    activeTab: string;
    activeStatus: string;
    categories: Category[];
    units: Unit[];
    items: Item[];
    stores: Store[];
    branches: Option[];
    projects: Option[];
    sites: Option[];
    locations: Option[];
    suppliers: Option[];
    priceCurrency: string;
    can: {
        manageItems: boolean;
        manageCategories: boolean;
        manageUnits: boolean;
        manageStores: boolean;
        viewCosts: boolean;
        permanentlyDeleteItems: boolean;
        permanentlyDeleteStores: boolean;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Materials & stores', href: '/inventory' },
];
const tabs: InventoryTab[] = ['items', 'categories', 'units', 'stores'];

export default function InventoryIndex(props: Props) {
    const [tab, setTab] = useState<InventoryTab>(
        tabs.includes(props.activeTab as InventoryTab)
            ? (props.activeTab as InventoryTab)
            : 'items',
    );
    const [status, setStatus] = useState<'active' | 'inactive'>(
        props.activeStatus === 'inactive' ? 'inactive' : 'active',
    );
    const confirm = useConfirmDialog();
    const [search, setSearch] = useState('');
    const debouncedSearch = useDebouncedValue(search);
    const term = debouncedSearch.trim().toLowerCase();
    const source =
        tab === 'items'
            ? props.items
            : tab === 'categories'
              ? props.categories
              : tab === 'units'
                ? props.units
                : props.stores;
    const rows = useMemo(
        () =>
            source.filter(
                (row) =>
                    row.is_active === (status === 'active') &&
                    (!term ||
                        Object.values(row)
                            .join(' ')
                            .toLowerCase()
                            .includes(term)),
            ),
        [source, status, term],
    );
    const action =
        tab === 'items' && props.can.manageItems ? (
            <ItemDialog
                categories={props.categories}
                units={props.units}
                suppliers={props.suppliers}
                canViewCosts={props.can.viewCosts}
            />
        ) : tab === 'categories' && props.can.manageCategories ? (
            <CategoryDialog />
        ) : tab === 'units' && props.can.manageUnits ? (
            <UnitDialog />
        ) : tab === 'stores' && props.can.manageStores ? (
            <StoreDialog
                branches={props.branches}
                projects={props.projects}
                sites={props.sites}
                locations={props.locations}
            />
        ) : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Materials & stores" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Materials & stores
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Manage material definitions, units and operational
                        stores.
                    </p>
                    <div className="mt-5 flex flex-wrap items-end justify-between gap-3">
                        <div className="relative w-full max-w-sm">
                            <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search the current register"
                                className="pl-9"
                            />
                        </div>
                        <div className="shrink-0">{action}</div>
                    </div>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Tabs
                        value={tab}
                        onValueChange={(value) => {
                            if (tabs.includes(value as InventoryTab)) {
                                setTab(value as InventoryTab);
                            }
                        }}
                    >
                        <TabsList>
                            <TabsTrigger value="items">Items</TabsTrigger>
                            <TabsTrigger value="categories">
                                Categories
                            </TabsTrigger>
                            <TabsTrigger value="units">Units</TabsTrigger>
                            <TabsTrigger value="stores">Stores</TabsTrigger>
                        </TabsList>
                    </Tabs>
                    <Tabs
                        value={status}
                        onValueChange={(value) =>
                            setStatus(value as 'active' | 'inactive')
                        }
                    >
                        <TabsList>
                            <TabsTrigger value="active">Active</TabsTrigger>
                            <TabsTrigger value="inactive">Inactive</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{tableTitle(tab)}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {tab === 'items' && (
                            <ItemTable
                                rows={rows as Item[]}
                                props={props}
                                confirm={confirm}
                            />
                        )}
                        {tab === 'categories' && (
                            <CategoryTable
                                rows={rows as Category[]}
                                canManage={props.can.manageCategories}
                                canPermanentlyDelete={
                                    props.can.permanentlyDeleteItems
                                }
                                confirm={confirm}
                            />
                        )}
                        {tab === 'units' && (
                            <UnitTable
                                rows={rows as Unit[]}
                                canManage={props.can.manageUnits}
                                canPermanentlyDelete={
                                    props.can.permanentlyDeleteItems
                                }
                                confirm={confirm}
                            />
                        )}
                        {tab === 'stores' && (
                            <StoreTable
                                rows={rows as Store[]}
                                props={props}
                                confirm={confirm}
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function ItemTable({
    rows,
    props,
    confirm,
}: {
    rows: Item[];
    props: Props;
    confirm: ReturnType<typeof useConfirmDialog>;
}) {
    const headers = [
        'Item',
        'Category',
        'Stock unit',
        'Tracking',
        'Stock controls',
        ...(props.can.viewCosts ? ['Default cost'] : []),
        '',
    ];
    return (
        <Table headers={headers}>
            {rows.map((item) => (
                <tr key={item.id} className="border-b last:border-0">
                    <td className="py-3 pr-4">
                        <Link
                            href={`/inventory/items/${item.id}`}
                            className="font-medium hover:underline"
                        >
                            {item.name}
                        </Link>
                        <div className="text-muted-foreground">{item.code}</div>
                    </td>
                    <td className="py-3 pr-4">
                        {item.category?.name ?? 'Category unavailable'}
                        <div className="text-muted-foreground">
                            {title(item.material_class)}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        {item.stock_unit?.name ?? 'Unit unavailable'}
                        <div className="text-muted-foreground">
                            {item.stock_unit?.symbol ?? item.stock_unit?.code}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        <Badge variant="outline">
                            {title(item.tracking_type)}
                        </Badge>
                        <div className="mt-1 text-muted-foreground">
                            {item.tracking_type === 'batch'
                                ? `Batch ${item.batch_number ?? 'not set'}`
                                : item.is_expires
                                  ? 'Expiry tracked'
                                  : 'No expiry'}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        {item.minimum_stock === null
                            ? 'No warning set'
                            : `Warn at ${formatNumber(item.minimum_stock)}`}
                        <div className="text-muted-foreground">
                            {item.reorder_quantity === null
                                ? 'No reorder quantity'
                                : `Reorder ${formatNumber(item.reorder_quantity)}`}
                        </div>
                    </td>
                    {props.can.viewCosts && (
                        <td className="py-3 pr-4">
                            {formatCurrencyAmount(
                                props.priceCurrency,
                                item.default_unit_cost,
                            )}
                        </td>
                    )}
                    <td className="py-3 text-right">
                        <div className="flex justify-end gap-2">
                            <Button
                                asChild
                                variant="outline"
                                size="icon"
                                title="View item"
                            >
                                <Link href={`/inventory/items/${item.id}`}>
                                    <Eye />
                                </Link>
                            </Button>
                            {props.can.manageItems && (
                                <ItemDialog
                                    item={item}
                                    categories={props.categories}
                                    units={props.units}
                                    suppliers={props.suppliers}
                                    canViewCosts={props.can.viewCosts}
                                />
                            )}
                            {props.can.manageItems && (
                                <Button
                                    variant="outline"
                                    size="icon"
                                    title={
                                        item.is_active
                                            ? 'Deactivate item'
                                            : 'Restore item'
                                    }
                                    onClick={() =>
                                        confirm({
                                            title: item.is_active
                                                ? 'Deactivate item?'
                                                : 'Restore item?',
                                            description: `${item.name} will move to the ${item.is_active ? 'inactive' : 'active'} register.`,
                                            confirmLabel: item.is_active
                                                ? 'Deactivate'
                                                : 'Restore',
                                            variant: item.is_active
                                                ? 'destructive'
                                                : 'default',
                                            onConfirm: () =>
                                                router.delete(
                                                    `/inventory/items/${item.id}`,
                                                ),
                                        })
                                    }
                                >
                                    <Power />
                                </Button>
                            )}
                            {!item.is_active &&
                                props.can.permanentlyDeleteItems && (
                                    <Button
                                        variant="destructive"
                                        size="icon"
                                        title="Delete permanently"
                                        onClick={() =>
                                            confirm({
                                                title: 'Permanently delete item?',
                                                description:
                                                    'This cannot be undone. Items with related records will be protected.',
                                                confirmLabel:
                                                    'Delete permanently',
                                                variant: 'destructive',
                                                onConfirm: () =>
                                                    router.delete(
                                                        `/inventory/items/${item.id}/permanent`,
                                                    ),
                                            })
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                        </div>
                    </td>
                </tr>
            ))}
            {rows.length === 0 && <Empty colSpan={headers.length} />}
        </Table>
    );
}

function CategoryTable({
    rows,
    canManage,
    canPermanentlyDelete,
    confirm,
}: {
    rows: Category[];
    canManage: boolean;
    canPermanentlyDelete: boolean;
    confirm: ReturnType<typeof useConfirmDialog>;
}) {
    return (
        <Table headers={['Category', 'Description', 'Status', '']}>
            {rows.map((category) => (
                <tr key={category.id} className="border-b last:border-0">
                    <td className="py-3 pr-4">
                        <div className="font-medium">{category.name}</div>
                        <div className="text-muted-foreground">
                            {category.code}
                        </div>
                    </td>
                    <td className="max-w-md py-3 pr-4 text-muted-foreground">
                        {category.description ?? 'No description'}
                    </td>
                    <td className="py-3 pr-4">
                        <StatusBadge active={category.is_active} />
                    </td>
                    <td className="py-3 text-right">
                        <div className="flex justify-end gap-2">
                            {canManage && (
                                <CategoryDialog category={category} />
                            )}
                            {canManage && (
                                <Button
                                    variant="outline"
                                    size="icon"
                                    title={
                                        category.is_active
                                            ? 'Deactivate category'
                                            : 'Restore category'
                                    }
                                    onClick={() =>
                                        confirm({
                                            title: category.is_active
                                                ? 'Deactivate category?'
                                                : 'Restore category?',
                                            confirmLabel: category.is_active
                                                ? 'Deactivate'
                                                : 'Restore',
                                            variant: category.is_active
                                                ? 'destructive'
                                                : 'default',
                                            onConfirm: () =>
                                                router.delete(
                                                    `/inventory/categories/${category.id}`,
                                                ),
                                        })
                                    }
                                >
                                    <Power />
                                </Button>
                            )}
                            {!category.is_active && canPermanentlyDelete && (
                                <Button
                                    variant="destructive"
                                    size="icon"
                                    title="Delete permanently"
                                    onClick={() =>
                                        confirm({
                                            title: 'Permanently delete category?',
                                            description:
                                                'Categories used by inventory items cannot be deleted.',
                                            confirmLabel: 'Delete permanently',
                                            variant: 'destructive',
                                            onConfirm: () =>
                                                router.delete(
                                                    `/inventory/categories/${category.id}/permanent`,
                                                ),
                                        })
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </div>
                    </td>
                </tr>
            ))}
            {rows.length === 0 && <Empty colSpan={4} />}
        </Table>
    );
}

function UnitTable({
    rows,
    canManage,
    canPermanentlyDelete,
    confirm,
}: {
    rows: Unit[];
    canManage: boolean;
    canPermanentlyDelete: boolean;
    confirm: ReturnType<typeof useConfirmDialog>;
}) {
    return (
        <Table headers={['Unit', 'Dimension', 'Base unit', 'Status', '']}>
            {rows.map((unit) => (
                <tr key={unit.id} className="border-b last:border-0">
                    <td className="py-3 pr-4">
                        <div className="font-medium">{unit.name}</div>
                        <div className="text-muted-foreground">
                            {unit.code}
                            {unit.symbol ? ` · ${unit.symbol}` : ''}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        {title(unit.quantity_dimension)}
                    </td>
                    <td className="py-3 pr-4">
                        {unit.is_base_unit ? 'Yes' : 'No'}
                    </td>
                    <td className="py-3 pr-4">
                        <StatusBadge active={unit.is_active} />
                    </td>
                    <td className="py-3 text-right">
                        <div className="flex justify-end gap-2">
                            {canManage && unit.tenant_id !== null && (
                                <UnitDialog unit={unit} />
                            )}
                            {canManage && unit.tenant_id !== null && (
                                <Button
                                    variant="outline"
                                    size="icon"
                                    title={
                                        unit.is_active
                                            ? 'Deactivate unit'
                                            : 'Restore unit'
                                    }
                                    onClick={() =>
                                        confirm({
                                            title: unit.is_active
                                                ? 'Deactivate unit?'
                                                : 'Restore unit?',
                                            confirmLabel: unit.is_active
                                                ? 'Deactivate'
                                                : 'Restore',
                                            variant: unit.is_active
                                                ? 'destructive'
                                                : 'default',
                                            onConfirm: () =>
                                                router.delete(
                                                    `/inventory/units/${unit.id}`,
                                                ),
                                        })
                                    }
                                >
                                    <Power />
                                </Button>
                            )}
                            {!unit.is_active &&
                                unit.tenant_id !== null &&
                                canPermanentlyDelete && (
                                    <Button
                                        variant="destructive"
                                        size="icon"
                                        title="Delete permanently"
                                        onClick={() =>
                                            confirm({
                                                title: 'Permanently delete unit?',
                                                description:
                                                    'Units used by items or conversions cannot be deleted.',
                                                confirmLabel:
                                                    'Delete permanently',
                                                variant: 'destructive',
                                                onConfirm: () =>
                                                    router.delete(
                                                        `/inventory/units/${unit.id}/permanent`,
                                                    ),
                                            })
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                        </div>
                    </td>
                </tr>
            ))}
            {rows.length === 0 && <Empty colSpan={5} />}
        </Table>
    );
}

function StoreTable({
    rows,
    props,
    confirm,
}: {
    rows: Store[];
    props: Props;
    confirm: ReturnType<typeof useConfirmDialog>;
}) {
    return (
        <Table
            headers={[
                'Store',
                'Type',
                'Branch',
                'Project / site',
                'Status',
                '',
            ]}
        >
            {rows.map((store) => (
                <tr key={store.id} className="border-b last:border-0">
                    <td className="py-3 pr-4">
                        <div className="font-medium">{store.name}</div>
                        <div className="text-muted-foreground">
                            {store.code}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        <Badge variant="outline">{title(store.type)}</Badge>
                    </td>
                    <td className="py-3 pr-4">
                        {store.branch?.name ?? 'Branch unavailable'}
                    </td>
                    <td className="py-3 pr-4">
                        {store.project?.name ?? 'Not project-linked'}
                        <div className="text-muted-foreground">
                            {store.site?.name ?? store.address ?? ''}
                        </div>
                    </td>
                    <td className="py-3 pr-4">
                        <StatusBadge active={store.is_active} />
                    </td>
                    <td className="py-3 text-right">
                        <div className="flex justify-end gap-2">
                            {props.can.manageStores && (
                                <StoreDialog
                                    store={store}
                                    branches={props.branches}
                                    projects={props.projects}
                                    sites={props.sites}
                                    locations={props.locations}
                                />
                            )}
                            {props.can.manageStores && (
                                <Button
                                    variant="outline"
                                    size="icon"
                                    title={
                                        store.is_active
                                            ? 'Deactivate store'
                                            : 'Restore store'
                                    }
                                    onClick={() =>
                                        confirm({
                                            title: store.is_active
                                                ? 'Deactivate store?'
                                                : 'Restore store?',
                                            confirmLabel: store.is_active
                                                ? 'Deactivate'
                                                : 'Restore',
                                            variant: store.is_active
                                                ? 'destructive'
                                                : 'default',
                                            onConfirm: () =>
                                                router.delete(
                                                    `/inventory/stores/${store.id}`,
                                                ),
                                        })
                                    }
                                >
                                    <Power />
                                </Button>
                            )}
                            {!store.is_active &&
                                props.can.permanentlyDeleteStores && (
                                    <Button
                                        variant="destructive"
                                        size="icon"
                                        title="Delete permanently"
                                        onClick={() =>
                                            confirm({
                                                title: 'Permanently delete store?',
                                                description:
                                                    'Stores with inventory or operational records cannot be deleted.',
                                                confirmLabel:
                                                    'Delete permanently',
                                                variant: 'destructive',
                                                onConfirm: () =>
                                                    router.delete(
                                                        `/inventory/stores/${store.id}/permanent`,
                                                    ),
                                            })
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                )}
                        </div>
                    </td>
                </tr>
            ))}
            {rows.length === 0 && <Empty colSpan={6} />}
        </Table>
    );
}

function Table({
    headers,
    children,
}: {
    headers: string[];
    children: ReactNode;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        {headers.map((header, index) => (
                            <th
                                key={`${header}-${index}`}
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

function StatusBadge({ active }: { active: boolean }) {
    return (
        <Badge variant={active ? 'secondary' : 'outline'}>
            {active ? 'Active' : 'Inactive'}
        </Badge>
    );
}

function tableTitle(tab: InventoryTab) {
    return tab === 'items'
        ? 'Item register'
        : tab === 'categories'
          ? 'Inventory categories'
          : tab === 'units'
            ? 'Units of measure'
            : 'Operational stores';
}

function title(value: string) {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
