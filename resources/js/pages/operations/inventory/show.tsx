import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, Power, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import {
    formatCurrencyAmount,
    formatDateTime,
    formatNumber,
} from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { DocumentDialog } from '../documents/partials/document-dialog';
import type {
    DocumentTypeOption,
    LinkOptions,
} from '../documents/partials/document-dialog';
import {
    ConversionDialog,
    PriceDialog,
    StoreSettingDialog,
} from './partials/item-detail-dialogs';
import type {
    Batch,
    Conversion,
    ItemPrice,
    StoreSetting,
} from './partials/item-detail-dialogs';
import { StockMovementReversalDialog } from './partials/stock-movement-dialogs';

type Option = {
    id: string;
    name: string;
    code?: string;
    symbol?: string;
    branch_name?: string;
};
type Item = {
    id: string;
    code: string;
    name: string;
    description: string | null;
    material_class: string;
    tracking_type: string;
    batch_number: string | null;
    is_expires: boolean;
    is_for_sale: boolean;
    minimum_stock: string | null;
    reorder_quantity: string | null;
    default_unit_cost: string | null;
    default_selling_price: string | null;
    is_active: boolean;
    category: { id: string; name: string } | null;
    stock_unit: { id: string; name: string; symbol: string | null } | null;
    preferred_supplier: { id: string; name: string } | null;
};
type ConversionRow = Conversion & {
    from_unit: Option | null;
    to_unit: Option | null;
};
type PriceRow = ItemPrice & {
    unit: Option;
    branch_name: string;
    currency: string;
};
type BatchRow = Batch & { store_name: string | null };
type StoreSettingRow = StoreSetting & {
    store_name: string;
    branch_name: string;
};
type DocumentRow = {
    id: string;
    title: string;
    reference: string | null;
    type_name: string | null;
    status: string;
    expires_on: string | null;
};
type StockBalance = {
    store_id: string;
    store_name: string;
    branch_name: string;
    minimum_stock: string | null;
    on_hand: string;
    reserved: string;
    available: string;
};
type StockMovement = {
    id: string;
    store_name: string;
    movement_type: string;
    status: string;
    quantity: string;
    original_quantity: string;
    original_unit: string;
    batch_number: string | null;
    reason: string;
    posted_by: string;
    posted_at: string;
    reversed_at: string | null;
};

type Props = {
    item: Item;
    conversions: ConversionRow[];
    prices: PriceRow[];
    batches: BatchRow[];
    storeSettings: StoreSettingRow[];
    stockBalances: StockBalance[];
    stockMovements: StockMovement[];
    units: Option[];
    stores: Option[];
    branches: Option[];
    defaultPriceBranchId: string;
    canChangePriceBranch: boolean;
    priceLists: Option[];
    documents: DocumentRow[];
    documentTypes: DocumentTypeOption[];
    documentBranches: Option[];
    documentLinkOptions: LinkOptions;
    activeTab: string;
    can: {
        manage: boolean;
        permanentlyDelete: boolean;
        viewCosts: boolean;
        uploadDocuments: boolean;
        viewStock: boolean;
        postStock: boolean;
        adjustStock: boolean;
        issueStock: boolean;
        returnStock: boolean;
        reverseStock: boolean;
    };
};

export default function InventoryItemShow(props: Props) {
    const { item } = props;
    const confirm = useConfirmDialog();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Materials & stores', href: '/inventory' },
        { title: item.name, href: `/inventory/items/${item.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={item.name} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="mb-2 -ml-3"
                        >
                            <Link href="/inventory">
                                <ArrowLeft />
                                Back to items
                            </Link>
                        </Button>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-semibold">
                                {item.name}
                            </h1>
                            <Badge
                                variant={
                                    item.is_active ? 'secondary' : 'outline'
                                }
                            >
                                {item.is_active ? 'Active' : 'Inactive'}
                            </Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {item.code} · {title(item.material_class)}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {props.can.manage && (
                            <Button
                                variant={
                                    item.is_active ? 'destructive' : 'secondary'
                                }
                                onClick={() =>
                                    confirm({
                                        title: item.is_active
                                            ? 'Deactivate item?'
                                            : 'Restore item?',
                                        description: item.is_active
                                            ? 'The item will move to the inactive register.'
                                            : 'The item will return to the active register.',
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
                                {item.is_active ? 'Deactivate' : 'Restore'}
                            </Button>
                        )}
                        {!item.is_active && props.can.permanentlyDelete && (
                            <Button
                                variant="destructive"
                                onClick={() =>
                                    confirm({
                                        title: 'Permanently delete item?',
                                        description:
                                            'This cannot be undone. Deletion will be refused when related operational records exist.',
                                        confirmLabel: 'Delete permanently',
                                        variant: 'destructive',
                                        onConfirm: () =>
                                            router.delete(
                                                `/inventory/items/${item.id}/permanent`,
                                            ),
                                    })
                                }
                            >
                                <Trash2 />
                                Delete permanently
                            </Button>
                        )}
                    </div>
                </div>

                <Tabs defaultValue={props.activeTab}>
                    <TabsList className="flex h-auto flex-wrap justify-start">
                        <TabsTrigger value="overview">Overview</TabsTrigger>
                        <TabsTrigger value="conversions">
                            Unit conversions
                        </TabsTrigger>
                        {props.can.viewCosts && (
                            <TabsTrigger value="prices">
                                Price lists
                            </TabsTrigger>
                        )}
                        {item.tracking_type === 'batch' && (
                            <TabsTrigger value="batches">Batches</TabsTrigger>
                        )}
                        <TabsTrigger value="stores">
                            Stocked in stores
                        </TabsTrigger>
                        {props.can.viewStock && (
                            <TabsTrigger value="stock">
                                Stock ledger
                            </TabsTrigger>
                        )}
                        <TabsTrigger value="documents">Documents</TabsTrigger>
                    </TabsList>
                    <TabsContent value="overview" className="mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Item setup</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                <Value
                                    label="Category"
                                    value={item.category?.name ?? 'Not set'}
                                />
                                <Value
                                    label="Stock unit"
                                    value={`${item.stock_unit?.name ?? 'Not set'}${item.stock_unit?.symbol ? ` (${item.stock_unit.symbol})` : ''}`}
                                />
                                <Value
                                    label="Tracking"
                                    value={title(item.tracking_type)}
                                />
                                <Value
                                    label="Preferred supplier"
                                    value={
                                        item.preferred_supplier?.name ??
                                        'Not set'
                                    }
                                />
                                <Value
                                    label="Minimum stock warning"
                                    value={formatOptionalNumber(
                                        item.minimum_stock,
                                    )}
                                />
                                <Value
                                    label="Reorder quantity"
                                    value={formatOptionalNumber(
                                        item.reorder_quantity,
                                    )}
                                />
                                <Value
                                    label="Expiry tracking"
                                    value={
                                        item.is_expires
                                            ? 'Enabled'
                                            : 'Not required'
                                    }
                                />
                                {props.can.viewCosts && (
                                    <>
                                        <Value
                                            label="Default unit cost"
                                            value={formatNumber(
                                                item.default_unit_cost,
                                            )}
                                        />
                                        <Value
                                            label="Default selling price"
                                            value={formatNumber(
                                                item.default_selling_price,
                                            )}
                                        />
                                    </>
                                )}
                                <div className="sm:col-span-2 lg:col-span-4">
                                    <Value
                                        label="Description"
                                        value={
                                            item.description ?? 'No description'
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="conversions" className="mt-6">
                        <Section
                            title="Unit conversions"
                            action={
                                props.can.manage ? (
                                    <ConversionDialog
                                        itemId={item.id}
                                        units={props.units.filter(
                                            (unit) =>
                                                unit.id !== item.stock_unit?.id,
                                        )}
                                    />
                                ) : null
                            }
                        >
                            <Table
                                headers={[
                                    'Conversion',
                                    'Effective from',
                                    'Reason',
                                    'Status',
                                    '',
                                ]}
                                rows={props.conversions.map((row) => [
                                    <span key={`${row.id}-conversion`}>
                                        1{' '}
                                        {row.from_unit?.symbol ??
                                            row.from_unit?.name}{' '}
                                        = {formatNumber(row.multiplier)}{' '}
                                        {row.to_unit?.symbol ??
                                            row.to_unit?.name}
                                    </span>,
                                    row.effective_from ?? 'Immediately',
                                    row.reason ?? 'No reason recorded',
                                    <Status
                                        key={`${row.id}-status`}
                                        active={row.is_active}
                                    />,
                                    props.can.manage ? (
                                        <ConversionDialog
                                            key={`${row.id}-action`}
                                            itemId={item.id}
                                            units={props.units.filter(
                                                (unit) =>
                                                    unit.id !==
                                                    item.stock_unit?.id,
                                            )}
                                            conversion={row}
                                        />
                                    ) : null,
                                ])}
                            />
                        </Section>
                    </TabsContent>
                    {props.can.viewCosts && (
                        <TabsContent value="prices" className="mt-6">
                            <Section
                                title="Price lists"
                                action={
                                    props.can.manage ? (
                                        <PriceDialog
                                            itemId={item.id}
                                            branches={props.branches}
                                            defaultBranchId={
                                                props.defaultPriceBranchId
                                            }
                                            canChangeBranch={
                                                props.canChangePriceBranch
                                            }
                                            priceLists={props.priceLists}
                                        />
                                    ) : null
                                }
                            >
                                <Table
                                    headers={[
                                        'Price list',
                                        'Context',
                                        'Unit price',
                                        '',
                                    ]}
                                    rows={props.prices.map((row) => [
                                        <div key={`${row.id}-price`}>
                                            <span className="font-medium">
                                                {row.tier_name}
                                            </span>
                                            <div className="text-muted-foreground">
                                                {row.tier_code}
                                            </div>
                                        </div>,
                                        row.branch_name,
                                        formatCurrencyAmount(
                                            row.currency,
                                            row.amount,
                                        ),
                                        props.can.manage ? (
                                            <PriceDialog
                                                key={`${row.id}-action`}
                                                itemId={item.id}
                                                branches={props.branches}
                                                defaultBranchId={
                                                    props.defaultPriceBranchId
                                                }
                                                canChangeBranch={
                                                    props.canChangePriceBranch
                                                }
                                                priceLists={props.priceLists}
                                                price={row}
                                            />
                                        ) : null,
                                    ])}
                                />
                            </Section>
                        </TabsContent>
                    )}
                    {item.tracking_type === 'batch' && (
                        <TabsContent value="batches" className="mt-6">
                            <Section title="Batches" action={null}>
                                <Table
                                    headers={[
                                        'Batch',
                                        'Store',
                                        'Manufactured',
                                        'Expiry',
                                        'Status',
                                    ]}
                                    rows={props.batches.map((row) => [
                                        row.batch_number,
                                        row.store_name ?? 'Not assigned',
                                        row.manufactured_on ?? 'Not recorded',
                                        row.expires_on ?? 'Not recorded',
                                        <Badge
                                            key={`${row.id}-status`}
                                            variant="outline"
                                        >
                                            {title(row.status)}
                                        </Badge>,
                                    ])}
                                />
                            </Section>
                        </TabsContent>
                    )}
                    <TabsContent value="stores" className="mt-6">
                        <Section
                            title="Store availability"
                            action={
                                props.can.manage ? (
                                    <StoreSettingDialog
                                        itemId={item.id}
                                        stores={props.stores}
                                    />
                                ) : null
                            }
                        >
                            <Table
                                headers={[
                                    'Store',
                                    'Branch',
                                    'Minimum stock',
                                    'Reorder quantity',
                                    'Storage location',
                                    '',
                                ]}
                                rows={props.storeSettings.map((row) => [
                                    row.store_name,
                                    row.branch_name,
                                    formatNumber(row.minimum_stock),
                                    formatNumber(row.reorder_quantity),
                                    row.storage_location ?? 'Not set',
                                    props.can.manage ? (
                                        <StoreSettingDialog
                                            key={`${row.id}-action`}
                                            itemId={item.id}
                                            stores={props.stores}
                                            setting={row}
                                        />
                                    ) : null,
                                ])}
                            />
                        </Section>
                    </TabsContent>
                    {props.can.viewStock && (
                        <TabsContent value="stock" className="mt-6 space-y-6">
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {props.stockBalances.map((balance) => {
                                    const low =
                                        balance.minimum_stock !== null &&
                                        Number(balance.on_hand) <=
                                            Number(balance.minimum_stock);
                                    return (
                                        <Card key={balance.store_id}>
                                            <CardHeader className="pb-3">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div>
                                                        <CardTitle className="text-base">
                                                            {balance.store_name}
                                                        </CardTitle>
                                                        <p className="mt-1 text-sm text-muted-foreground">
                                                            {
                                                                balance.branch_name
                                                            }
                                                        </p>
                                                    </div>
                                                    {low && (
                                                        <Badge variant="destructive">
                                                            Low stock
                                                        </Badge>
                                                    )}
                                                </div>
                                            </CardHeader>
                                            <CardContent className="grid grid-cols-3 gap-3">
                                                <Value
                                                    label="On hand"
                                                    value={formatNumber(
                                                        balance.on_hand,
                                                    )}
                                                />
                                                <Value
                                                    label="Reserved"
                                                    value={formatNumber(
                                                        balance.reserved,
                                                    )}
                                                />
                                                <Value
                                                    label="Available"
                                                    value={formatNumber(
                                                        balance.available,
                                                    )}
                                                />
                                            </CardContent>
                                        </Card>
                                    );
                                })}
                            </div>
                            <Section
                                title="Movement ledger"
                                action={
                                    <Button asChild variant="outline">
                                        <Link href="/inventory/stock-movements">
                                            Open movements
                                        </Link>
                                    </Button>
                                }
                            >
                                <Table
                                    headers={[
                                        'Recorded',
                                        'Store',
                                        'Movement',
                                        'Stock quantity',
                                        'Original quantity',
                                        'Reason',
                                        'Status',
                                        '',
                                    ]}
                                    rows={props.stockMovements.map(
                                        (movement) => [
                                            <div key={`${movement.id}-posted`}>
                                                <span className="font-medium">
                                                    {formatDateTime(
                                                        movement.posted_at,
                                                    )}
                                                </span>
                                                <div className="text-muted-foreground">
                                                    {movement.posted_by}
                                                </div>
                                            </div>,
                                            movement.store_name,
                                            <Badge
                                                key={`${movement.id}-type`}
                                                variant="outline"
                                            >
                                                {title(movement.movement_type)}
                                            </Badge>,
                                            <span
                                                key={`${movement.id}-quantity`}
                                                className={
                                                    Number(movement.quantity) <
                                                    0
                                                        ? 'text-destructive'
                                                        : 'text-emerald-700'
                                                }
                                            >
                                                {formatNumber(
                                                    movement.quantity,
                                                )}{' '}
                                                {item.stock_unit?.symbol ??
                                                    item.stock_unit?.name}
                                            </span>,
                                            `${formatNumber(movement.original_quantity)} ${movement.original_unit}`,
                                            <div
                                                key={`${movement.id}-reason`}
                                                className="max-w-xs"
                                            >
                                                <span>{movement.reason}</span>
                                                {movement.batch_number && (
                                                    <div className="text-muted-foreground">
                                                        Batch{' '}
                                                        {movement.batch_number}
                                                    </div>
                                                )}
                                            </div>,
                                            <Status
                                                key={`${movement.id}-status`}
                                                active={
                                                    movement.status === 'posted'
                                                }
                                                label={title(movement.status)}
                                            />,
                                            props.can.reverseStock &&
                                            movement.status === 'posted' &&
                                            movement.movement_type !==
                                                'reversal' ? (
                                                <StockMovementReversalDialog
                                                    key={`${movement.id}-action`}
                                                    movementId={movement.id}
                                                />
                                            ) : null,
                                        ],
                                    )}
                                />
                            </Section>
                        </TabsContent>
                    )}
                    <TabsContent value="documents" className="mt-6">
                        <Section
                            title="Linked documents"
                            action={
                                props.can.uploadDocuments ? (
                                    <DocumentDialog
                                        documentTypes={props.documentTypes}
                                        branches={props.documentBranches}
                                        linkOptions={props.documentLinkOptions}
                                        defaultLink={{
                                            type: 'inventory_item',
                                            id: item.id,
                                        }}
                                        buttonLabel="Add document"
                                    />
                                ) : null
                            }
                        >
                            <Table
                                headers={[
                                    'Document',
                                    'Type',
                                    'Status',
                                    'Expiry',
                                    '',
                                ]}
                                rows={props.documents.map((document) => [
                                    <div key={`${document.id}-document`}>
                                        <span className="font-medium">
                                            {document.title}
                                        </span>
                                        <div className="text-muted-foreground">
                                            {document.reference ??
                                                'No reference'}
                                        </div>
                                    </div>,
                                    document.type_name ?? 'Unclassified',
                                    <Status
                                        key={`${document.id}-status`}
                                        active={document.status === 'active'}
                                        label={title(document.status)}
                                    />,
                                    document.expires_on ?? 'No expiry',
                                    <Button
                                        key={`${document.id}-action`}
                                        asChild
                                        variant="outline"
                                        size="sm"
                                    >
                                        <Link
                                            href={`/documents/${document.id}`}
                                        >
                                            <ExternalLink />
                                            Open
                                        </Link>
                                    </Button>,
                                ])}
                            />
                        </Section>
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}

function Section({
    title: heading,
    action,
    children,
}: {
    title: string;
    action: ReactNode;
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-4">
                <CardTitle>{heading}</CardTitle>
                {action}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}
function Value({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-xs font-medium text-muted-foreground">
                {label}
            </div>
            <div className="mt-1 text-sm">{value}</div>
        </div>
    );
}
function Status({ active, label }: { active: boolean; label?: string }) {
    return (
        <Badge variant={active ? 'secondary' : 'outline'}>
            {label ?? (active ? 'Active' : 'Inactive')}
        </Badge>
    );
}
function Table({ headers, rows }: { headers: string[]; rows: ReactNode[][] }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        {headers.map((header, index) => (
                            <th
                                key={`${header}-${index}`}
                                className="py-3 pr-4 font-medium"
                            >
                                {header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((cells, rowIndex) => (
                        <tr key={rowIndex} className="border-b last:border-0">
                            {cells.map((cell, cellIndex) => (
                                <td key={cellIndex} className="py-3 pr-4">
                                    {cell}
                                </td>
                            ))}
                        </tr>
                    ))}
                    {rows.length === 0 && (
                        <tr>
                            <td
                                colSpan={headers.length}
                                className="py-10 text-center text-muted-foreground"
                            >
                                No records have been added.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
function title(value: string) {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
function formatOptionalNumber(value: string | null) {
    return value === null ? 'Not set' : formatNumber(value);
}
