import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Send } from 'lucide-react';
import type { ReactNode } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatNumber } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import {
    CancelRequisitionDialog,
    IssueLineDialog,
    ReturnLineDialog,
    ReviewRequisitionDialog,
} from './partials/requisition-action-dialogs';
import { RequisitionDialog } from './partials/requisition-dialog';
import type { RequisitionFormOptions, RequisitionLineForm } from './types';

type Line = {
    id: string;
    inventory_item_id: string | null;
    item_code: string | null;
    item_name: string;
    tracking_type: string | null;
    unit_of_measure_id: string;
    project_activity_id: string | null;
    unit_name: string;
    stock_unit_name: string | null;
    requested_quantity: string;
    stock_quantity: string;
    approved_quantity: string;
    issued_quantity: string;
    returned_quantity: string;
    outstanding_quantity: string;
    outstanding_request_unit_quantity: string;
    purpose: string | null;
    notes: string | null;
    available_stock: string | null;
    reserved_quantity: string | null;
    movements: {
        id: string;
        type: string;
        quantity: string;
        original_quantity: string;
        posted_at: string;
        posted_by: string;
    }[];
};
type Requisition = {
    id: string;
    branch_id: string;
    inventory_store_id: string;
    project_id: string | null;
    site_id: string | null;
    reference: string;
    branch_name: string;
    store_name: string;
    requester_name: string;
    department: string | null;
    project_name: string | null;
    site_name: string | null;
    required_by_date: string;
    priority: string;
    status: string;
    reason: string;
    decision_reason: string | null;
    approved_by: string | null;
    lines: Line[];
};
type Props = RequisitionFormOptions & {
    requisition: Requisition;
    batches: {
        id: string;
        inventory_item_id: string;
        batch_number: string;
        expires_on: string | null;
    }[];
    can: {
        update: boolean;
        submit: boolean;
        approve: boolean;
        issue: boolean;
        returnStock: boolean;
        cancel: boolean;
    };
};

export default function MaterialRequisitionShow(props: Props) {
    const { requisition } = props;
    const confirm = useConfirmDialog();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Material requisitions', href: '/inventory/requisitions' },
        {
            title: requisition.reference,
            href: `/inventory/requisitions/${requisition.id}`,
        },
    ];
    const editLines: RequisitionLineForm[] = requisition.lines.map((line) => ({
        inventory_item_id: line.inventory_item_id ?? '',
        unit_of_measure_id: line.unit_of_measure_id,
        requested_quantity: line.requested_quantity,
        project_activity_id: line.project_activity_id ?? '',
        purpose: line.purpose ?? '',
        notes: line.notes ?? '',
    }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={requisition.reference} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Button asChild variant="ghost" className="mb-2 -ml-3">
                            <Link href="/inventory/requisitions">
                                <ArrowLeft /> Requisitions
                            </Link>
                        </Button>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-semibold">
                                {requisition.reference}
                            </h1>
                            <StatusBadge status={requisition.status} />
                            <Badge
                                variant={
                                    requisition.priority === 'urgent'
                                        ? 'destructive'
                                        : 'outline'
                                }
                            >
                                {label(requisition.priority)}
                            </Badge>
                        </div>
                        <p className="mt-2 max-w-3xl text-sm text-muted-foreground">
                            {requisition.reason}
                        </p>
                    </div>
                    <div className="flex flex-wrap justify-end gap-2">
                        {props.can.update && (
                            <RequisitionDialog
                                options={props}
                                requisition={{
                                    id: requisition.id,
                                    branch_id: requisition.branch_id,
                                    inventory_store_id:
                                        requisition.inventory_store_id,
                                    project_id: requisition.project_id,
                                    site_id: requisition.site_id,
                                    department: requisition.department,
                                    required_by_date:
                                        requisition.required_by_date,
                                    priority: requisition.priority,
                                    reason: requisition.reason,
                                    lines: editLines,
                                }}
                            />
                        )}
                        {props.can.submit && (
                            <Button
                                onClick={() =>
                                    confirm({
                                        title: 'Submit material requisition?',
                                        description:
                                            'The draft will be locked while it is awaiting review.',
                                        confirmLabel: 'Submit',
                                        onConfirm: () =>
                                            router.post(
                                                `/inventory/requisitions/${requisition.id}/submit`,
                                                {},
                                                { preserveScroll: true },
                                            ),
                                    })
                                }
                            >
                                <Send /> Submit
                            </Button>
                        )}
                        {props.can.approve && (
                            <ReviewRequisitionDialog
                                requisitionId={requisition.id}
                                lines={requisition.lines}
                            />
                        )}
                        {props.can.cancel && (
                            <CancelRequisitionDialog
                                requisitionId={requisition.id}
                            />
                        )}
                    </div>
                </div>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Summary
                        label="Source store"
                        value={requisition.store_name}
                        detail={requisition.branch_name}
                    />
                    <Summary
                        label="Destination"
                        value={
                            requisition.site_name ??
                            requisition.project_name ??
                            requisition.department ??
                            'Branch operations'
                        }
                    />
                    <Summary
                        label="Requested by"
                        value={requisition.requester_name}
                        detail={`Required ${requisition.required_by_date}`}
                    />
                    <Summary
                        label="Approval"
                        value={requisition.approved_by ?? 'Awaiting approval'}
                        detail={requisition.decision_reason ?? undefined}
                    />
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Requested materials
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <Th>Material</Th>
                                        <Th>Requested</Th>
                                        <Th>Approved</Th>
                                        <Th>Issued</Th>
                                        <Th>Returned</Th>
                                        <Th>Outstanding</Th>
                                        <Th>Store position</Th>
                                        <Th>Actions</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {requisition.lines.map((line) => (
                                        <tr
                                            key={line.id}
                                            className="border-b last:border-0"
                                        >
                                            <Td>
                                                <span className="font-medium">
                                                    {line.item_name}
                                                </span>
                                                <div className="text-muted-foreground">
                                                    {line.item_code ??
                                                        'Unregistered item'}
                                                    {line.purpose
                                                        ? ` · ${line.purpose}`
                                                        : ''}
                                                </div>
                                            </Td>
                                            <Td>
                                                {formatNumber(
                                                    line.requested_quantity,
                                                )}{' '}
                                                {line.unit_name}
                                                <div className="text-muted-foreground">
                                                    {formatNumber(
                                                        line.stock_quantity,
                                                    )}{' '}
                                                    {line.stock_unit_name}
                                                </div>
                                            </Td>
                                            <Td>
                                                {formatNumber(
                                                    line.approved_quantity,
                                                )}{' '}
                                                {line.stock_unit_name}
                                            </Td>
                                            <Td>
                                                {formatNumber(
                                                    line.issued_quantity,
                                                )}{' '}
                                                {line.stock_unit_name}
                                            </Td>
                                            <Td>
                                                {formatNumber(
                                                    line.returned_quantity,
                                                )}{' '}
                                                {line.stock_unit_name}
                                            </Td>
                                            <Td>
                                                {formatNumber(
                                                    line.outstanding_quantity,
                                                )}{' '}
                                                {line.stock_unit_name}
                                            </Td>
                                            <Td>
                                                {line.available_stock ===
                                                null ? (
                                                    'Not stocked'
                                                ) : (
                                                    <>
                                                        <span>
                                                            {formatNumber(
                                                                line.available_stock,
                                                            )}{' '}
                                                            {
                                                                line.stock_unit_name
                                                            }{' '}
                                                            available
                                                        </span>
                                                        <div className="text-muted-foreground">
                                                            {formatNumber(
                                                                line.reserved_quantity ??
                                                                    0,
                                                            )}{' '}
                                                            reserved here
                                                        </div>
                                                    </>
                                                )}
                                            </Td>
                                            <Td>
                                                <div className="flex gap-2">
                                                    {props.can.issue && (
                                                        <IssueLineDialog
                                                            requisitionId={
                                                                requisition.id
                                                            }
                                                            line={line}
                                                            batches={
                                                                props.batches
                                                            }
                                                        />
                                                    )}
                                                    {props.can.returnStock && (
                                                        <ReturnLineDialog
                                                            requisitionId={
                                                                requisition.id
                                                            }
                                                            line={line}
                                                            batches={
                                                                props.batches
                                                            }
                                                        />
                                                    )}
                                                </div>
                                            </Td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
                {requisition.lines.some(
                    (line) => line.movements.length > 0,
                ) && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Issue and return history
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {requisition.lines.flatMap((line) =>
                                line.movements.map((movement) => (
                                    <div
                                        key={movement.id}
                                        className="flex flex-wrap items-center justify-between gap-3 border-b pb-3 last:border-0"
                                    >
                                        <div>
                                            <span className="font-medium">
                                                {line.item_name}
                                            </span>
                                            <div className="text-sm text-muted-foreground">
                                                {label(movement.type)} by{' '}
                                                {movement.posted_by}
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <span className="font-medium">
                                                {formatNumber(
                                                    Math.abs(
                                                        Number(
                                                            movement.quantity,
                                                        ),
                                                    ),
                                                )}{' '}
                                                {line.stock_unit_name}
                                            </span>
                                            <div className="text-sm text-muted-foreground">
                                                {formatDateTime(movement.posted_at)}
                                            </div>
                                        </div>
                                    </div>
                                )),
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}

function Summary({
    label: title,
    value,
    detail,
}: {
    label: string;
    value: string;
    detail?: string;
}) {
    return (
        <Card>
            <CardContent className="pt-6">
                <p className="text-sm text-muted-foreground">{title}</p>
                <p className="mt-1 font-medium">{value}</p>
                {detail && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        {detail}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
function StatusBadge({ status }: { status: string }) {
    const variant =
        status === 'rejected' || status === 'cancelled'
            ? 'destructive'
            : status === 'fulfilled'
              ? 'default'
              : 'secondary';
    return <Badge variant={variant}>{label(status)}</Badge>;
}
function label(value: string) {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
function Th({ children }: { children: ReactNode }) {
    return <th className="px-3 py-3 font-medium">{children}</th>;
}
function Td({ children }: { children: ReactNode }) {
    return <td className="px-3 py-4 align-top">{children}</td>;
}
