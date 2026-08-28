import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Boxes,
    ClipboardList,
    Download,
    FileWarning,
    RotateCcw,
    Warehouse,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Option = {
    id: string;
    name: string;
    code?: string;
    branch_id?: string;
    branch_name?: string;
    reference?: string;
    inventory_category_id?: string;
};
type Filters = {
    branch_id: string | null;
    store_id: string | null;
    project_id: string | null;
    supplier_id: string | null;
    item_id: string | null;
    category_id: string | null;
    date_from: string | null;
    date_to: string | null;
};
type Metrics = {
    active_stores: number;
    stocked_locations: number;
    low_stock: number;
    requisitions_awaiting_review: number;
    requisitions_awaiting_issue: number;
    overdue_purchase_orders: number;
    rejected_receipt_lines: number;
    unreconciled_dsr_lines: number;
};
type StockRow = {
    id: string;
    item_id: string;
    item_code: string;
    item_name: string;
    store_name: string;
    branch_name: string;
    unit: string;
    available: string;
    minimum_stock: string | null;
};
type RequisitionRow = {
    id: string;
    reference: string;
    status: string;
    project: string | null;
    site: string | null;
    store: string;
    required_by_date: string;
    is_overdue: boolean;
    lines_count: number;
    outstanding_lines: number;
};
type PurchaseOrderRow = {
    id: string;
    order_number: string;
    supplier: string;
    store: string;
    status: string;
    expected_date: string | null;
    outstanding_lines: number;
};
type ReceiptRow = {
    id: string;
    receipt_id: string;
    receipt_reference: string;
    purchase_order_id: string;
    purchase_order: string;
    supplier: string;
    item_name: string;
    rejected_quantity: string;
    unit: string;
    rejection_reason: string | null;
};
type DsrRow = {
    id: string;
    report_id: string;
    report_reference: string;
    report_date: string;
    project: string;
    site: string;
    item: string;
    unit: string | null;
    status: string;
    reported_quantity: string;
    allocated_quantity: string;
    outstanding_quantity: string;
};

type Props = {
    filters: Filters;
    filterOptions: {
        branches: Option[];
        stores: Option[];
        projects: Option[];
        suppliers: Option[];
        items: Option[];
        categories: Option[];
    };
    metrics: Metrics;
    lowStock: StockRow[];
    unfulfilledRequisitions: RequisitionRow[];
    overduePurchaseOrders: PurchaseOrderRow[];
    rejectedReceipts: ReceiptRow[];
    unreconciledMaterials: DsrRow[];
    canExport: boolean;
    canExportDsr: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventory dashboard', href: '/inventory-dashboard' },
];

export default function InventoryDashboard(props: Props) {
    const [filters, setFilters] = useState(props.filters);
    const stores = props.filterOptions.stores.filter(
        (store) => !filters.branch_id || store.branch_id === filters.branch_id,
    );
    const projects = props.filterOptions.projects.filter(
        (project) =>
            !filters.branch_id || project.branch_id === filters.branch_id,
    );
    const items = props.filterOptions.items.filter(
        (item) =>
            !filters.category_id ||
            item.inventory_category_id === filters.category_id,
    );

    function applyFilters() {
        router.get('/inventory-dashboard', compactFilters(filters), {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function resetFilters() {
        setFilters({
            branch_id:
                props.filterOptions.branches.length === 1
                    ? props.filterOptions.branches[0].id
                    : null,
            store_id: null,
            project_id: null,
            supplier_id: null,
            item_id: null,
            category_id: null,
            date_from: null,
            date_to: null,
        });
        router.get('/inventory-dashboard');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inventory dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Inventory operations
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Stock warnings, fulfilment work, supplier delivery
                            exceptions and approved DSR material reconciliation.
                        </p>
                    </div>
                    {(props.canExport || props.canExportDsr) && (
                        <ExportMenu
                            filters={filters}
                            canExport={props.canExport}
                            canExportDsr={props.canExportDsr}
                        />
                    )}
                </div>

                <Card>
                    <CardContent className="grid gap-4 pt-6 sm:grid-cols-2 xl:grid-cols-4">
                        <Filter label="Branch">
                            <SearchableSelect
                                disabled={
                                    props.filterOptions.branches.length === 1
                                }
                                value={filters.branch_id ?? ''}
                                onValueChange={(value) =>
                                    setFilters({
                                        ...filters,
                                        branch_id: value || null,
                                        store_id: null,
                                        project_id: null,
                                    })
                                }
                                options={props.filterOptions.branches.map(
                                    option,
                                )}
                                placeholder="All accessible branches"
                            />
                        </Filter>
                        <Filter label="Store">
                            <SearchableSelect
                                value={filters.store_id ?? ''}
                                onValueChange={(value) =>
                                    setFilters({
                                        ...filters,
                                        store_id: value || null,
                                    })
                                }
                                options={stores.map(option)}
                                placeholder="All stores"
                            />
                        </Filter>
                        <Filter label="Project">
                            <SearchableSelect
                                value={filters.project_id ?? ''}
                                onValueChange={(value) =>
                                    setFilters({
                                        ...filters,
                                        project_id: value || null,
                                    })
                                }
                                options={projects.map(option)}
                                placeholder="All projects"
                            />
                        </Filter>
                        <Filter label="Supplier">
                            <SearchableSelect
                                value={filters.supplier_id ?? ''}
                                onValueChange={(value) =>
                                    setFilters({
                                        ...filters,
                                        supplier_id: value || null,
                                    })
                                }
                                options={props.filterOptions.suppliers.map(
                                    option,
                                )}
                                placeholder="All suppliers"
                            />
                        </Filter>
                        <Filter label="Category">
                            <SearchableSelect
                                value={filters.category_id ?? ''}
                                onValueChange={(value) =>
                                    setFilters({
                                        ...filters,
                                        category_id: value || null,
                                        item_id: null,
                                    })
                                }
                                options={props.filterOptions.categories.map(
                                    option,
                                )}
                                placeholder="All categories"
                            />
                        </Filter>
                        <Filter label="Item">
                            <SearchableSelect
                                value={filters.item_id ?? ''}
                                onValueChange={(value) =>
                                    setFilters({
                                        ...filters,
                                        item_id: value || null,
                                    })
                                }
                                options={items.map(option)}
                                placeholder="All items"
                            />
                        </Filter>
                        <Filter label="From">
                            <Input
                                type="date"
                                value={filters.date_from ?? ''}
                                onChange={(event) =>
                                    setFilters({
                                        ...filters,
                                        date_from: event.target.value || null,
                                    })
                                }
                            />
                        </Filter>
                        <Filter label="To">
                            <Input
                                type="date"
                                value={filters.date_to ?? ''}
                                onChange={(event) =>
                                    setFilters({
                                        ...filters,
                                        date_to: event.target.value || null,
                                    })
                                }
                            />
                        </Filter>
                        <div className="flex gap-2 sm:col-span-2 xl:col-span-4 xl:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={resetFilters}
                            >
                                <RotateCcw />
                                Reset
                            </Button>
                            <Button type="button" onClick={applyFilters}>
                                Apply filters
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric
                        icon={Warehouse}
                        label="Active stores"
                        value={props.metrics.active_stores}
                    />
                    <Metric
                        icon={Boxes}
                        label="Stocked locations"
                        value={props.metrics.stocked_locations}
                    />
                    <Metric
                        icon={AlertTriangle}
                        label="Low-stock items"
                        value={props.metrics.low_stock}
                        warning={props.metrics.low_stock > 0}
                    />
                    <Metric
                        icon={ClipboardList}
                        label="Requisitions awaiting review"
                        value={props.metrics.requisitions_awaiting_review}
                        warning={props.metrics.requisitions_awaiting_review > 0}
                    />
                    <Metric
                        icon={ClipboardList}
                        label="Requisitions awaiting issue"
                        value={props.metrics.requisitions_awaiting_issue}
                        warning={props.metrics.requisitions_awaiting_issue > 0}
                    />
                    <Metric
                        icon={FileWarning}
                        label="Overdue purchase orders"
                        value={props.metrics.overdue_purchase_orders}
                        warning={props.metrics.overdue_purchase_orders > 0}
                    />
                    <Metric
                        icon={FileWarning}
                        label="Rejected receipt lines"
                        value={props.metrics.rejected_receipt_lines}
                        warning={props.metrics.rejected_receipt_lines > 0}
                    />
                    <Metric
                        icon={AlertTriangle}
                        label="Unreconciled DSR materials"
                        value={props.metrics.unreconciled_dsr_lines}
                        warning={props.metrics.unreconciled_dsr_lines > 0}
                    />
                </div>

                <div className="grid min-w-0 gap-6 2xl:grid-cols-2">
                    <Section
                        title="Low stock"
                        href="/inventory/stock"
                        description="Available quantity is at or below the store warning level."
                    >
                        <Table
                            headers={['Item', 'Store', 'Available', 'Minimum']}
                            empty={props.lowStock.length === 0}
                        >
                            {props.lowStock.map((row) => (
                                <tr
                                    key={row.id}
                                    className="border-b last:border-0"
                                >
                                    <Td>
                                        <Link
                                            className="font-medium hover:underline"
                                            href={`/inventory/items/${row.item_id}?tab=stock`}
                                        >
                                            {row.item_name}
                                        </Link>
                                        <Sub>{row.item_code}</Sub>
                                    </Td>
                                    <Td>
                                        {row.store_name}
                                        <Sub>{row.branch_name}</Sub>
                                    </Td>
                                    <NumberCell
                                        value={row.available}
                                        unit={row.unit}
                                    />
                                    <NumberCell
                                        value={row.minimum_stock ?? '0'}
                                        unit={row.unit}
                                    />
                                </tr>
                            ))}
                        </Table>
                    </Section>
                    <Section
                        title="Unfulfilled requisitions"
                        href="/inventory/requisitions"
                        description="Requests waiting for review or further store issue."
                    >
                        <Table
                            headers={[
                                'Requisition',
                                'Project / site',
                                'Required',
                                'Outstanding',
                            ]}
                            empty={props.unfulfilledRequisitions.length === 0}
                        >
                            {props.unfulfilledRequisitions.map((row) => (
                                <tr
                                    key={row.id}
                                    className="border-b last:border-0"
                                >
                                    <Td>
                                        <Link
                                            className="font-medium hover:underline"
                                            href={`/inventory/requisitions/${row.id}`}
                                        >
                                            {row.reference}
                                        </Link>
                                        <Sub>{row.store}</Sub>
                                    </Td>
                                    <Td>
                                        {row.project ?? 'No project'}
                                        <Sub>{row.site ?? 'No site'}</Sub>
                                    </Td>
                                    <Td>
                                        {row.required_by_date}
                                        {row.is_overdue && (
                                            <Badge
                                                className="ml-2"
                                                variant="destructive"
                                            >
                                                Overdue
                                            </Badge>
                                        )}
                                    </Td>
                                    <Td>
                                        {formatNumber(row.outstanding_lines)} of{' '}
                                        {formatNumber(row.lines_count)} lines
                                    </Td>
                                </tr>
                            ))}
                        </Table>
                    </Section>
                    <Section
                        title="Overdue purchase orders"
                        href="/inventory/purchase-orders"
                        description="Approved orders past their expected date with open lines."
                    >
                        <Table
                            headers={[
                                'Purchase order',
                                'Supplier',
                                'Expected',
                                'Open lines',
                            ]}
                            empty={props.overduePurchaseOrders.length === 0}
                        >
                            {props.overduePurchaseOrders.map((row) => (
                                <tr
                                    key={row.id}
                                    className="border-b last:border-0"
                                >
                                    <Td>
                                        <Link
                                            className="font-medium hover:underline"
                                            href={`/inventory/purchase-orders/${row.id}`}
                                        >
                                            {row.order_number}
                                        </Link>
                                        <Sub>{row.store}</Sub>
                                    </Td>
                                    <Td>{row.supplier}</Td>
                                    <Td>{row.expected_date ?? 'Not set'}</Td>
                                    <Td>
                                        {formatNumber(row.outstanding_lines)}
                                    </Td>
                                </tr>
                            ))}
                        </Table>
                    </Section>
                    <Section
                        title="Rejected receipt quantities"
                        href="/inventory/receipts"
                        description="Damaged, spoilt or rejected delivery quantities requiring supplier follow-up."
                    >
                        <Table
                            headers={[
                                'Receipt / PO',
                                'Supplier',
                                'Item',
                                'Rejected',
                            ]}
                            empty={props.rejectedReceipts.length === 0}
                        >
                            {props.rejectedReceipts.map((row) => (
                                <tr
                                    key={row.id}
                                    className="border-b last:border-0"
                                >
                                    <Td>
                                        <Link
                                            className="font-medium hover:underline"
                                            href={`/inventory/receipts/${row.receipt_id}`}
                                        >
                                            {row.receipt_reference}
                                        </Link>
                                        <Sub>
                                            <Link
                                                className="hover:underline"
                                                href={`/inventory/purchase-orders/${row.purchase_order_id}`}
                                            >
                                                {row.purchase_order}
                                            </Link>
                                        </Sub>
                                    </Td>
                                    <Td>{row.supplier}</Td>
                                    <Td>
                                        {row.item_name}
                                        <Sub>
                                            {row.rejection_reason ??
                                                'No reason recorded'}
                                        </Sub>
                                    </Td>
                                    <NumberCell
                                        value={row.rejected_quantity}
                                        unit={row.unit}
                                    />
                                </tr>
                            ))}
                        </Table>
                    </Section>
                </div>

                <Section
                    title="DSR material reconciliation"
                    href="/daily-site-reports"
                    description="Approved site-reported material still awaiting complete stock evidence or external classification."
                >
                    <Table
                        headers={[
                            'Report',
                            'Project / site',
                            'Material',
                            'Reported',
                            'Allocated',
                            'Outstanding',
                            'Status',
                        ]}
                        empty={props.unreconciledMaterials.length === 0}
                    >
                        {props.unreconciledMaterials.map((row) => (
                            <tr key={row.id} className="border-b last:border-0">
                                <Td>
                                    <Link
                                        className="font-medium hover:underline"
                                        href={`/daily-site-reports/${row.report_id}`}
                                    >
                                        {row.report_reference}
                                    </Link>
                                    <Sub>{row.report_date}</Sub>
                                </Td>
                                <Td>
                                    {row.project}
                                    <Sub>{row.site}</Sub>
                                </Td>
                                <Td>{row.item}</Td>
                                <NumberCell
                                    value={row.reported_quantity}
                                    unit={row.unit ?? ''}
                                />
                                <NumberCell
                                    value={row.allocated_quantity}
                                    unit={row.unit ?? ''}
                                />
                                <NumberCell
                                    value={row.outstanding_quantity}
                                    unit={row.unit ?? ''}
                                />
                                <Td>
                                    <Badge
                                        variant={
                                            row.status === 'exception'
                                                ? 'destructive'
                                                : 'outline'
                                        }
                                    >
                                        {row.status.replaceAll('_', ' ')}
                                    </Badge>
                                </Td>
                            </tr>
                        ))}
                    </Table>
                </Section>
            </div>
        </AppLayout>
    );
}

function ExportMenu({
    filters,
    canExport,
    canExportDsr,
}: {
    filters: Filters;
    canExport: boolean;
    canExportDsr: boolean;
}) {
    const reports = [
        ...(canExport
            ? [
                  ['stock-balances', 'Stock balances'],
                  ['movements', 'Movement ledger'],
                  ['requisitions', 'Requisition fulfilment'],
                  ['purchase-orders', 'Purchase-order delivery'],
                  ['receipts', 'Receipt inspection'],
                  ['supplier-performance', 'Supplier performance'],
              ]
            : []),
        ...(canExportDsr
            ? [['dsr-materials', 'DSR material reconciliation']]
            : []),
    ];
    const query = new URLSearchParams(compactFilters(filters)).toString();
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline">
                    <Download />
                    Export report
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-72">
                {reports.flatMap(([key, label]) => [
                    <DropdownMenuItem key={`${key}-pdf`} asChild>
                        <a href={`/inventory/reports/${key}/pdf?${query}`}>
                            {label} (PDF)
                        </a>
                    </DropdownMenuItem>,
                    <DropdownMenuItem key={`${key}-csv`} asChild>
                        <a href={`/inventory/reports/${key}?${query}`}>
                            {label} (CSV)
                        </a>
                    </DropdownMenuItem>,
                ])}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function Metric({
    icon: Icon,
    label,
    value,
    warning = false,
}: {
    icon: typeof Warehouse;
    label: string;
    value: number;
    warning?: boolean;
}) {
    return (
        <Card>
            <CardContent className="flex items-center gap-3 pt-6">
                <div
                    className={`flex size-10 shrink-0 items-center justify-center rounded-md border ${warning ? 'border-destructive/30 bg-destructive/10 text-destructive' : 'bg-muted'}`}
                >
                    <Icon className="size-5" />
                </div>
                <div className="min-w-0">
                    <div className="text-2xl font-semibold tabular-nums">
                        {formatNumber(value)}
                    </div>
                    <div className="text-sm text-muted-foreground">{label}</div>
                </div>
            </CardContent>
        </Card>
    );
}
function Filter({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label>{label}</Label>
            {children}
        </div>
    );
}
function Section({
    title,
    description,
    href,
    children,
}: {
    title: string;
    description: string;
    href?: string;
    children: ReactNode;
}) {
    return (
        <Card className="min-w-0">
            <CardHeader className="flex-row items-start justify-between gap-4">
                <div>
                    <CardTitle className="text-base">{title}</CardTitle>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
                {href && (
                    <Button asChild size="sm" variant="outline">
                        <Link href={href}>View all</Link>
                    </Button>
                )}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}
function Table({
    headers,
    children,
    empty,
}: {
    headers: string[];
    children: ReactNode;
    empty: boolean;
}) {
    return (
        <div className="max-w-full overflow-x-auto">
            <table className="w-full min-w-[640px] text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        {headers.map((header) => (
                            <th key={header} className="py-3 pr-4 font-medium">
                                {header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {children}
                    {empty && (
                        <tr>
                            <td
                                colSpan={headers.length}
                                className="py-10 text-center text-muted-foreground"
                            >
                                Nothing requires attention for this scope.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
function Td({ children }: { children: ReactNode }) {
    return <td className="py-3 pr-4 align-top">{children}</td>;
}
function NumberCell({ value, unit }: { value: string; unit?: string }) {
    return (
        <td className="py-3 pr-4 align-top whitespace-nowrap tabular-nums">
            {formatNumber(value)}
            {unit ? ` ${unit}` : ''}
        </td>
    );
}
function Sub({ children }: { children: ReactNode }) {
    return (
        <div
            className="max-w-64 truncate text-muted-foreground"
            title={typeof children === 'string' ? children : undefined}
        >
            {children}
        </div>
    );
}
function option(value: Option) {
    return {
        value: value.id,
        label: value.name,
        description: value.code ?? value.reference ?? value.branch_name,
    };
}
function compactFilters(filters: Filters): Record<string, string> {
    return Object.fromEntries(
        Object.entries(filters).filter(
            (entry): entry is [string, string] =>
                typeof entry[1] === 'string' && entry[1] !== '',
        ),
    );
}
