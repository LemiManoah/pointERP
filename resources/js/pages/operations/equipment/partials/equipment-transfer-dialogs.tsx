import { router, useForm } from '@inertiajs/react';
import { Check, Send, Truck } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type {
    BranchOption,
    EquipmentLocation,
    EquipmentRecord,
    EquipmentTransfer,
} from '../types';

function localNow(): string {
    const now = new Date();
    return new Date(now.getTime() - now.getTimezoneOffset() * 60_000)
        .toISOString()
        .slice(0, 16);
}

export function EquipmentTransferRequestDialog({
    equipment,
    branches,
    locations,
}: {
    equipment: EquipmentRecord;
    branches: BranchOption[];
    locations: EquipmentLocation[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        destination_branch_id: '',
        destination_location_id: '',
        destination_project_id: '',
        destination_site_id: '',
        reason: '',
    });
    const destinationLocations = locations.filter(
        (location) =>
            location.is_active &&
            location.branch_id === form.data.destination_branch_id &&
            location.id !== equipment.current_location_id,
    );

    function selectBranch(value: string) {
        form.setData('destination_branch_id', value);
        form.setData('destination_location_id', '');
        form.setData('destination_project_id', '');
        form.setData('destination_site_id', '');
    }

    function selectLocation(value: string) {
        const location = locations.find((item) => item.id === value);
        form.setData('destination_location_id', value);
        form.setData('destination_project_id', location?.project_id ?? '');
        form.setData('destination_site_id', location?.site_id ?? '');
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/equipment/${equipment.id}/transfers`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Truck /> Request transfer
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Request equipment transfer</DialogTitle>
                    <DialogDescription>
                        {equipment.asset_code} remains at its source until an
                        approved transfer is dispatched.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <SelectField
                        label="Destination branch"
                        error={form.errors.destination_branch_id}
                        value={form.data.destination_branch_id}
                        onChange={selectBranch}
                        options={branches.map((branch) => ({
                            value: branch.id,
                            label: branch.name,
                        }))}
                    />
                    <SelectField
                        label="Destination location"
                        error={form.errors.destination_location_id}
                        value={form.data.destination_location_id}
                        onChange={selectLocation}
                        options={destinationLocations.map((location) => ({
                            value: location.id,
                            label: location.name,
                            description: [
                                location.project_name,
                                location.site_name,
                            ]
                                .filter(Boolean)
                                .join(' · '),
                        }))}
                    />
                    <Field label="Transfer reason" error={form.errors.reason}>
                        <Textarea
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    </Field>
                    <Actions
                        processing={form.processing}
                        onCancel={() => setOpen(false)}
                        label="Request transfer"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function TransferApproveButton({
    transfer,
}: {
    transfer: EquipmentTransfer;
}) {
    const confirm = useConfirmDialog();
    return (
        <Button
            size="sm"
            variant="outline"
            onClick={() =>
                confirm({
                    title: 'Approve equipment transfer?',
                    description: `Approve movement to ${transfer.destination_location_name}. Dispatch will still be recorded separately.`,
                    confirmLabel: 'Approve',
                    onConfirm: () =>
                        router.post(
                            `/equipment-transfers/${transfer.id}/approve`,
                            {},
                            { preserveScroll: true },
                        ),
                })
            }
        >
            <Check /> Approve
        </Button>
    );
}

export function TransferDispatchDialog({
    equipment,
    transfer,
}: {
    equipment: EquipmentRecord;
    transfer: EquipmentTransfer;
}) {
    return (
        <TransferEventDialog
            equipment={equipment}
            transfer={transfer}
            action="dispatch"
        />
    );
}

export function TransferReceiptDialog({
    equipment,
    transfer,
}: {
    equipment: EquipmentRecord;
    transfer: EquipmentTransfer;
}) {
    return (
        <TransferEventDialog
            equipment={equipment}
            transfer={transfer}
            action="receive"
        />
    );
}

function TransferEventDialog({
    equipment,
    transfer,
    action,
}: {
    equipment: EquipmentRecord;
    transfer: EquipmentTransfer;
    action: 'dispatch' | 'receive';
}) {
    const [open, setOpen] = useState(false);
    const dispatching = action === 'dispatch';
    const form = useForm({
        [dispatching ? 'dispatched_at' : 'received_at']: localNow(),
        [dispatching ? 'dispatch_meter_reading' : 'receipt_meter_reading']:
            equipment.current_meter_reading ?? '',
        [dispatching ? 'dispatch_condition' : 'receipt_condition']:
            equipment.condition_summary ?? '',
        transport_reference: transfer.transport_reference ?? '',
    });
    const timeKey = dispatching ? 'dispatched_at' : 'received_at';
    const meterKey = dispatching
        ? 'dispatch_meter_reading'
        : 'receipt_meter_reading';
    const conditionKey = dispatching
        ? 'dispatch_condition'
        : 'receipt_condition';

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/equipment-transfers/${transfer.id}/${action}`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant={dispatching ? 'outline' : 'default'}>
                    {dispatching ? <Send /> : <Check />}
                    {dispatching ? 'Dispatch' : 'Receive'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {dispatching
                            ? 'Dispatch equipment'
                            : 'Receive equipment'}
                    </DialogTitle>
                    <DialogDescription>
                        {transfer.source_location_name} to{' '}
                        {transfer.destination_location_name}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <Field
                        label={dispatching ? 'Dispatched at' : 'Received at'}
                        error={form.errors[timeKey]}
                    >
                        <Input
                            type="datetime-local"
                            value={form.data[timeKey]}
                            onChange={(event) =>
                                form.setData(timeKey, event.target.value)
                            }
                        />
                    </Field>
                    {equipment.meter_type !== 'none' && (
                        <Field
                            label={
                                dispatching ? 'Dispatch meter' : 'Receipt meter'
                            }
                            error={form.errors[meterKey]}
                        >
                            <Input
                                type="number"
                                min="0"
                                step="0.0001"
                                value={form.data[meterKey]}
                                onChange={(event) =>
                                    form.setData(meterKey, event.target.value)
                                }
                            />
                        </Field>
                    )}
                    <Field
                        label={
                            dispatching
                                ? 'Dispatch condition'
                                : 'Receipt condition'
                        }
                        error={form.errors[conditionKey]}
                    >
                        <Textarea
                            value={form.data[conditionKey]}
                            onChange={(event) =>
                                form.setData(conditionKey, event.target.value)
                            }
                        />
                    </Field>
                    {dispatching && (
                        <Field
                            label="Transport reference"
                            error={form.errors.transport_reference}
                        >
                            <Input
                                value={form.data.transport_reference}
                                onChange={(event) =>
                                    form.setData(
                                        'transport_reference',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    )}
                    <Actions
                        processing={form.processing}
                        onCancel={() => setOpen(false)}
                        label={
                            dispatching ? 'Confirm dispatch' : 'Accept receipt'
                        }
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

function SelectField({
    label,
    error,
    value,
    onChange,
    options,
}: {
    label: string;
    error?: string;
    value: string;
    onChange: (value: string) => void;
    options: Array<{ value: string; label: string; description?: string }>;
}) {
    return (
        <Field label={label} error={error}>
            <SearchableSelect
                value={value}
                onValueChange={onChange}
                options={options}
                placeholder="Select option"
            />
        </Field>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function Actions({
    processing,
    onCancel,
    label,
}: {
    processing: boolean;
    onCancel: () => void;
    label: string;
}) {
    return (
        <div className="flex justify-end gap-3">
            <Button type="button" variant="outline" onClick={onCancel}>
                Cancel
            </Button>
            <Button type="submit" disabled={processing}>
                {processing && <Spinner />}
                {label}
            </Button>
        </div>
    );
}
