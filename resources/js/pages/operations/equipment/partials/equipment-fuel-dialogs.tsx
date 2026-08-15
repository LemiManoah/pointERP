import { router, useForm } from '@inertiajs/react';
import { Check, Fuel, RotateCcw } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    EquipmentFuelTransaction,
    EquipmentRecord,
    Option,
    OwnerOption,
    StaffOption,
} from '../types';

function localNow(): string {
    const now = new Date();

    return new Date(now.getTime() - now.getTimezoneOffset() * 60_000)
        .toISOString()
        .slice(0, 16);
}

export function EquipmentFuelDialog({
    equipment,
    staff,
    providers,
    currencies,
    canViewCosts,
}: {
    equipment: EquipmentRecord;
    staff: StaffOption[];
    providers: OwnerOption[];
    currencies: Option[];
    canViewCosts: boolean;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        transacted_at: localNow(),
        transaction_type: 'refuel',
        fuel_type: 'diesel',
        quantity: '',
        source_type: 'supplier',
        provider_customer_id: '',
        source_name: '',
        unit_cost: '',
        currency_code: currencies[0]?.id ?? '',
        meter_reading: equipment.current_meter_reading ?? '',
        tank_level_before: '',
        tank_level_after: '',
        is_full_tank: false,
        received_by_staff_id: '',
        voucher_reference: '',
        notes: '',
    });
    const branchStaff = staff.filter(
        (person) => person.branch_id === equipment.branch_id,
    );
    const branchProviders = providers.filter(
        (provider) =>
            provider.branch_id === null ||
            provider.branch_id === equipment.branch_id,
    );

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/equipment/${equipment.id}/fuel-transactions`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Fuel /> Record fuel
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Record equipment fuel</DialogTitle>
                    <DialogDescription>
                        Submit a controlled fuel entry for{' '}
                        {equipment.asset_code}. A different authorized user must
                        post it.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field
                            label="Transaction time"
                            error={form.errors.transacted_at}
                        >
                            <Input
                                type="datetime-local"
                                value={form.data.transacted_at}
                                onChange={(event) =>
                                    form.setData(
                                        'transacted_at',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <SelectField
                            label="Transaction type"
                            error={form.errors.transaction_type}
                            value={form.data.transaction_type}
                            onChange={(value) =>
                                form.setData('transaction_type', value)
                            }
                            options={[
                                { value: 'refuel', label: 'Refuel' },
                                { value: 'issue', label: 'Fuel issue' },
                                { value: 'consumption', label: 'Consumption' },
                                { value: 'return', label: 'Fuel return' },
                                { value: 'adjustment', label: 'Adjustment' },
                            ]}
                        />
                        <Field label="Fuel type" error={form.errors.fuel_type}>
                            <Input
                                value={form.data.fuel_type}
                                onChange={(event) =>
                                    form.setData(
                                        'fuel_type',
                                        event.target.value,
                                    )
                                }
                                placeholder="Diesel"
                            />
                        </Field>
                        <Field
                            label="Quantity (litres)"
                            error={form.errors.quantity}
                        >
                            <Input
                                type="number"
                                min="0.0001"
                                step="0.0001"
                                value={form.data.quantity}
                                onChange={(event) =>
                                    form.setData('quantity', event.target.value)
                                }
                            />
                        </Field>
                        <SelectField
                            label="Fuel source"
                            error={form.errors.source_type}
                            value={form.data.source_type}
                            onChange={(value) =>
                                form.setData('source_type', value)
                            }
                            options={[
                                { value: 'supplier', label: 'Supplier' },
                                { value: 'store', label: 'Company store' },
                                { value: 'site_stock', label: 'Site stock' },
                                {
                                    value: 'mobile_bowser',
                                    label: 'Mobile bowser',
                                },
                                { value: 'other', label: 'Other' },
                            ]}
                        />
                        {form.data.source_type === 'supplier' && (
                            <SelectField
                                label="Supplier / subcontractor"
                                error={form.errors.provider_customer_id}
                                value={form.data.provider_customer_id}
                                onChange={(value) =>
                                    form.setData('provider_customer_id', value)
                                }
                                options={branchProviders.map((provider) => ({
                                    value: provider.id,
                                    label: provider.name,
                                }))}
                            />
                        )}
                        <Field
                            label="External source name"
                            error={form.errors.source_name}
                        >
                            <Input
                                value={form.data.source_name}
                                onChange={(event) =>
                                    form.setData(
                                        'source_name',
                                        event.target.value,
                                    )
                                }
                                placeholder="Use when the source is not registered"
                            />
                        </Field>
                        <SelectField
                            label="Received by"
                            error={form.errors.received_by_staff_id}
                            value={form.data.received_by_staff_id}
                            onChange={(value) =>
                                form.setData('received_by_staff_id', value)
                            }
                            options={branchStaff.map((person) => ({
                                value: person.id,
                                label: person.name,
                                description: person.staff_number,
                            }))}
                        />
                        {equipment.meter_type !== 'none' && (
                            <Field
                                label="Meter reading"
                                error={form.errors.meter_reading}
                            >
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.0001"
                                    value={form.data.meter_reading}
                                    onChange={(event) =>
                                        form.setData(
                                            'meter_reading',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        )}
                        <Field
                            label="Voucher / delivery reference"
                            error={form.errors.voucher_reference}
                        >
                            <Input
                                value={form.data.voucher_reference}
                                onChange={(event) =>
                                    form.setData(
                                        'voucher_reference',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Tank level before (litres)"
                            error={form.errors.tank_level_before}
                        >
                            <Input
                                type="number"
                                min="0"
                                step="0.0001"
                                value={form.data.tank_level_before}
                                onChange={(event) =>
                                    form.setData(
                                        'tank_level_before',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Tank level after (litres)"
                            error={form.errors.tank_level_after}
                        >
                            <Input
                                type="number"
                                min="0"
                                step="0.0001"
                                value={form.data.tank_level_after}
                                onChange={(event) =>
                                    form.setData(
                                        'tank_level_after',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        {canViewCosts && (
                            <>
                                <Field
                                    label="Unit cost"
                                    error={form.errors.unit_cost}
                                >
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.0001"
                                        value={form.data.unit_cost}
                                        onChange={(event) =>
                                            form.setData(
                                                'unit_cost',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <SelectField
                                    label="Currency"
                                    error={form.errors.currency_code}
                                    value={form.data.currency_code}
                                    onChange={(value) =>
                                        form.setData('currency_code', value)
                                    }
                                    options={currencies.map((currency) => ({
                                        value: currency.id,
                                        label: currency.name,
                                    }))}
                                />
                            </>
                        )}
                    </div>
                    <label className="flex items-center gap-3 text-sm font-medium">
                        <Checkbox
                            checked={form.data.is_full_tank}
                            onCheckedChange={(checked) =>
                                form.setData('is_full_tank', checked === true)
                            }
                        />
                        Tank filled to capacity
                    </label>
                    <Field label="Notes" error={form.errors.notes}>
                        <Textarea
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                        />
                    </Field>
                    <Actions
                        processing={form.processing}
                        onCancel={() => setOpen(false)}
                        label="Submit fuel entry"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function FuelApproveButton({
    transaction,
}: {
    transaction: EquipmentFuelTransaction;
}) {
    const confirm = useConfirmDialog();

    return (
        <Button
            size="sm"
            variant="outline"
            onClick={() =>
                confirm({
                    title: 'Post fuel transaction?',
                    description: `Post ${transaction.quantity} ${transaction.unit} to the permanent fuel ledger. Any meter reading will also be recorded.`,
                    confirmLabel: 'Post transaction',
                    onConfirm: () =>
                        router.post(
                            `/equipment-fuel-transactions/${transaction.id}/approve`,
                            {},
                            { preserveScroll: true },
                        ),
                })
            }
        >
            <Check /> Post
        </Button>
    );
}

export function FuelReversalDialog({
    transaction,
}: {
    transaction: EquipmentFuelTransaction;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/equipment-fuel-transactions/${transaction.id}/reverse`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <RotateCcw /> Reverse
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Reverse fuel transaction</DialogTitle>
                    <DialogDescription>
                        A matching negative adjustment will preserve the
                        original audit record.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <Field
                        label="Reason for reversal"
                        error={form.errors.reason}
                    >
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
                        label="Create reversal"
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
