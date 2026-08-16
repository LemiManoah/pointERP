import { router, useForm } from '@inertiajs/react';
import {
    Check,
    ClipboardPlus,
    Play,
    Plus,
    Trash2,
    Wrench,
    X,
} from 'lucide-react';
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
    EquipmentMaintenanceSchedule,
    EquipmentMaintenanceWorkOrder,
    EquipmentRecord,
    Option,
    OwnerOption,
} from '../types';

const maintenanceTypes = [
    'preventive_service',
    'inspection',
    'certification',
    'lubrication',
    'tyres',
    'other',
].map(option);

function localNow(): string {
    const now = new Date();
    return new Date(now.getTime() - now.getTimezoneOffset() * 60_000)
        .toISOString()
        .slice(0, 16);
}

export function MaintenanceScheduleDialog({
    equipment,
    users,
    schedule,
}: {
    equipment: EquipmentRecord;
    users: Option[];
    schedule?: EquipmentMaintenanceSchedule;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        maintenance_type: schedule?.maintenance_type ?? 'preventive_service',
        name: schedule?.name ?? '',
        basis: schedule?.basis ?? 'whichever_first',
        interval_days: schedule?.interval_days?.toString() ?? '',
        interval_meter_units: schedule?.interval_meter_units ?? '',
        last_service_date: schedule?.last_service_date ?? '',
        last_service_reading:
            schedule?.last_service_reading ??
            equipment.current_meter_reading ??
            '',
        warning_days: schedule?.warning_days?.toString() ?? '14',
        warning_meter_units: schedule?.warning_meter_units ?? '50',
        responsible_user_id: schedule?.responsible_user_id ?? '',
        is_active: schedule?.is_active ?? true,
    });
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };
        if (schedule)
            form.put(
                `/equipment/${equipment.id}/maintenance-schedules/${schedule.id}`,
                options,
            );
        else
            form.post(
                `/equipment/${equipment.id}/maintenance-schedules`,
                options,
            );
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={schedule ? 'outline' : 'default'}
                    size={schedule ? 'sm' : 'default'}
                >
                    <Wrench />
                    {schedule ? 'Edit' : 'New schedule'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {schedule
                            ? 'Edit maintenance schedule'
                            : 'Create maintenance schedule'}
                    </DialogTitle>
                    <DialogDescription>
                        Define when {equipment.asset_code} should next be
                        serviced and when advance warnings begin.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            label="Maintenance type"
                            value={form.data.maintenance_type}
                            onChange={(value) =>
                                form.setData('maintenance_type', value)
                            }
                            options={maintenanceTypes}
                            error={form.errors.maintenance_type}
                        />
                        <Field label="Schedule name" error={form.errors.name}>
                            <Input
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                        </Field>
                        <SelectField
                            label="Schedule basis"
                            value={form.data.basis}
                            onChange={(value) => form.setData('basis', value)}
                            options={['date', 'meter', 'whichever_first'].map(
                                option,
                            )}
                            error={form.errors.basis}
                        />
                        <SelectField
                            label="Responsible user"
                            value={form.data.responsible_user_id}
                            onChange={(value) =>
                                form.setData('responsible_user_id', value)
                            }
                            options={[
                                { value: '', label: 'Not assigned' },
                                ...users.map((user) => ({
                                    value: user.id,
                                    label: user.name,
                                })),
                            ]}
                            error={form.errors.responsible_user_id}
                        />
                        {form.data.basis !== 'meter' && (
                            <Field
                                label="Interval (days)"
                                error={form.errors.interval_days}
                            >
                                <Input
                                    type="number"
                                    min="1"
                                    value={form.data.interval_days}
                                    onChange={(event) =>
                                        form.setData(
                                            'interval_days',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        )}
                        {form.data.basis !== 'date' && (
                            <Field
                                label="Interval (meter units)"
                                error={form.errors.interval_meter_units}
                            >
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.0001"
                                    value={form.data.interval_meter_units}
                                    onChange={(event) =>
                                        form.setData(
                                            'interval_meter_units',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        )}
                        <Field
                            label="Last service date"
                            error={form.errors.last_service_date}
                        >
                            <Input
                                type="date"
                                value={form.data.last_service_date}
                                onChange={(event) =>
                                    form.setData(
                                        'last_service_date',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        {equipment.meter_type !== 'none' && (
                            <Field
                                label="Last service reading"
                                error={form.errors.last_service_reading}
                            >
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.0001"
                                    value={form.data.last_service_reading}
                                    onChange={(event) =>
                                        form.setData(
                                            'last_service_reading',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        )}
                        <Field
                            label="Warning days"
                            error={form.errors.warning_days}
                        >
                            <Input
                                type="number"
                                min="0"
                                value={form.data.warning_days}
                                onChange={(event) =>
                                    form.setData(
                                        'warning_days',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Warning meter units"
                            error={form.errors.warning_meter_units}
                        >
                            <Input
                                type="number"
                                min="0"
                                step="0.0001"
                                value={form.data.warning_meter_units}
                                onChange={(event) =>
                                    form.setData(
                                        'warning_meter_units',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked === true)
                            }
                        />
                        Active schedule
                    </label>
                    <Actions
                        processing={form.processing}
                        onCancel={() => setOpen(false)}
                        label={schedule ? 'Save schedule' : 'Create schedule'}
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function MaintenanceWorkOrderDialog({
    equipment,
    schedules,
    providers,
}: {
    equipment: EquipmentRecord;
    schedules: EquipmentMaintenanceSchedule[];
    providers: OwnerOption[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        equipment_maintenance_schedule_id: '',
        reference: `MWO-${equipment.asset_code}-${Date.now().toString().slice(-6)}`,
        maintenance_type: 'preventive_service',
        priority: 'normal',
        description: '',
        reported_at: localNow(),
        planned_start_at: '',
        provider_customer_id: '',
        provider_name: '',
    });
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/equipment/${equipment.id}/maintenance-work-orders`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <ClipboardPlus />
                    New work order
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Open maintenance work order</DialogTitle>
                    <DialogDescription>
                        Plan maintenance for {equipment.asset_code}. Approval is
                        required before work starts.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            label="Schedule"
                            value={form.data.equipment_maintenance_schedule_id}
                            onChange={(value) =>
                                form.setData(
                                    'equipment_maintenance_schedule_id',
                                    value,
                                )
                            }
                            options={[
                                { value: '', label: 'Unscheduled maintenance' },
                                ...schedules
                                    .filter((item) => item.is_active)
                                    .map((item) => ({
                                        value: item.id,
                                        label: item.name,
                                    })),
                            ]}
                            error={
                                form.errors.equipment_maintenance_schedule_id
                            }
                        />
                        <Field label="Reference" error={form.errors.reference}>
                            <Input
                                value={form.data.reference}
                                onChange={(event) =>
                                    form.setData(
                                        'reference',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <SelectField
                            label="Maintenance type"
                            value={form.data.maintenance_type}
                            onChange={(value) =>
                                form.setData('maintenance_type', value)
                            }
                            options={maintenanceTypes}
                            error={form.errors.maintenance_type}
                        />
                        <SelectField
                            label="Priority"
                            value={form.data.priority}
                            onChange={(value) =>
                                form.setData('priority', value)
                            }
                            options={['low', 'normal', 'high', 'critical'].map(
                                option,
                            )}
                            error={form.errors.priority}
                        />
                        <Field
                            label="Reported at"
                            error={form.errors.reported_at}
                        >
                            <Input
                                type="datetime-local"
                                value={form.data.reported_at}
                                onChange={(event) =>
                                    form.setData(
                                        'reported_at',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Planned start"
                            error={form.errors.planned_start_at}
                        >
                            <Input
                                type="datetime-local"
                                value={form.data.planned_start_at}
                                onChange={(event) =>
                                    form.setData(
                                        'planned_start_at',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <SelectField
                            label="Service provider"
                            value={form.data.provider_customer_id}
                            onChange={(value) =>
                                form.setData('provider_customer_id', value)
                            }
                            options={[
                                {
                                    value: '',
                                    label: 'Internal workshop / external name',
                                },
                                ...providers.map((provider) => ({
                                    value: provider.id,
                                    label: provider.name,
                                })),
                            ]}
                            error={form.errors.provider_customer_id}
                        />
                        <Field
                            label="External provider name"
                            error={form.errors.provider_name}
                        >
                            <Input
                                value={form.data.provider_name}
                                onChange={(event) =>
                                    form.setData(
                                        'provider_name',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>
                    <Field
                        label="Description / reported problem"
                        error={form.errors.description}
                    >
                        <Textarea
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                    </Field>
                    <Actions
                        processing={form.processing}
                        onCancel={() => setOpen(false)}
                        label="Submit work order"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function MaintenanceApproveButton({
    workOrder,
}: {
    workOrder: EquipmentMaintenanceWorkOrder;
}) {
    const confirm = useConfirmDialog();
    return (
        <Button
            size="sm"
            variant="outline"
            onClick={() =>
                confirm({
                    title: 'Approve maintenance work?',
                    description: `${workOrder.reference} may then be started and will make the asset unavailable.`,
                    confirmLabel: 'Approve',
                    onConfirm: () =>
                        router.post(
                            `/equipment-maintenance-work-orders/${workOrder.id}/approve`,
                            {},
                            { preserveScroll: true },
                        ),
                })
            }
        >
            <Check />
            Approve
        </Button>
    );
}

export function MaintenanceStartDialog({
    workOrder,
    equipment,
}: {
    workOrder: EquipmentMaintenanceWorkOrder;
    equipment: EquipmentRecord;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        actual_start_at: localNow(),
        opening_meter_reading: equipment.current_meter_reading ?? '',
    });
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/equipment-maintenance-work-orders/${workOrder.id}/start`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }
    return (
        <ActionDialog
            open={open}
            setOpen={setOpen}
            trigger={
                <>
                    <Play />
                    Start
                </>
            }
            title="Start maintenance"
            description="Starting work makes this equipment unavailable for new assignments."
        >
            <form onSubmit={submit} className="grid gap-5">
                <Field label="Actual start" error={form.errors.actual_start_at}>
                    <Input
                        type="datetime-local"
                        value={form.data.actual_start_at}
                        onChange={(event) =>
                            form.setData('actual_start_at', event.target.value)
                        }
                    />
                </Field>
                {equipment.meter_type !== 'none' && (
                    <Field
                        label="Opening meter"
                        error={form.errors.opening_meter_reading}
                    >
                        <Input
                            type="number"
                            step="0.0001"
                            value={form.data.opening_meter_reading}
                            onChange={(event) =>
                                form.setData(
                                    'opening_meter_reading',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                )}
                <Actions
                    processing={form.processing}
                    onCancel={() => setOpen(false)}
                    label="Start work"
                />
            </form>
        </ActionDialog>
    );
}

type PartInput = {
    part_code: string;
    part_name: string;
    quantity: string;
    unit: string;
    unit_cost: string;
    provider_name: string;
    reference: string;
    notes: string;
};

export function MaintenanceCompleteDialog({
    workOrder,
    equipment,
    currencies,
    canViewCosts,
}: {
    workOrder: EquipmentMaintenanceWorkOrder;
    equipment: EquipmentRecord;
    currencies: Option[];
    canViewCosts: boolean;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        completed_at: localNow(),
        closing_meter_reading: equipment.current_meter_reading ?? '',
        downtime_hours: '',
        findings: '',
        work_performed: '',
        completion_notes: '',
        labour_cost: '',
        other_cost: '',
        currency_code: currencies[0]?.id ?? '',
        parts: [] as PartInput[],
    });
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(
            `/equipment-maintenance-work-orders/${workOrder.id}/complete`,
            { preserveScroll: true, onSuccess: () => setOpen(false) },
        );
    }
    function addPart() {
        form.setData('parts', [
            ...form.data.parts,
            {
                part_code: '',
                part_name: '',
                quantity: '1',
                unit: 'piece',
                unit_cost: '',
                provider_name: '',
                reference: '',
                notes: '',
            },
        ]);
    }
    function updatePart(index: number, field: keyof PartInput, value: string) {
        form.setData(
            'parts',
            form.data.parts.map((part, partIndex) =>
                partIndex === index ? { ...part, [field]: value } : part,
            ),
        );
    }
    return (
        <ActionDialog
            open={open}
            setOpen={setOpen}
            trigger={
                <>
                    <Wrench />
                    Complete
                </>
            }
            title="Complete maintenance"
            description="Record the service evidence, meter, downtime, parts and final costs before releasing the asset."
            wide
        >
            <form onSubmit={submit} className="grid gap-5">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Completed at"
                        error={form.errors.completed_at}
                    >
                        <Input
                            type="datetime-local"
                            value={form.data.completed_at}
                            onChange={(event) =>
                                form.setData('completed_at', event.target.value)
                            }
                        />
                    </Field>
                    {equipment.meter_type !== 'none' && (
                        <Field
                            label="Closing meter"
                            error={form.errors.closing_meter_reading}
                        >
                            <Input
                                type="number"
                                step="0.0001"
                                value={form.data.closing_meter_reading}
                                onChange={(event) =>
                                    form.setData(
                                        'closing_meter_reading',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    )}
                    <Field
                        label="Downtime hours"
                        error={form.errors.downtime_hours}
                    >
                        <Input
                            type="number"
                            min="0"
                            step="0.0001"
                            value={form.data.downtime_hours}
                            onChange={(event) =>
                                form.setData(
                                    'downtime_hours',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    {canViewCosts && (
                        <SelectField
                            label="Currency"
                            value={form.data.currency_code}
                            onChange={(value) =>
                                form.setData('currency_code', value)
                            }
                            options={currencies.map((currency) => ({
                                value: currency.id,
                                label: currency.name,
                            }))}
                            error={form.errors.currency_code}
                        />
                    )}
                </div>
                <Field label="Findings" error={form.errors.findings}>
                    <Textarea
                        value={form.data.findings}
                        onChange={(event) =>
                            form.setData('findings', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Work performed"
                    error={form.errors.work_performed}
                >
                    <Textarea
                        value={form.data.work_performed}
                        onChange={(event) =>
                            form.setData('work_performed', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Completion notes"
                    error={form.errors.completion_notes}
                >
                    <Textarea
                        value={form.data.completion_notes}
                        onChange={(event) =>
                            form.setData('completion_notes', event.target.value)
                        }
                    />
                </Field>
                {canViewCosts && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Labour cost"
                            error={form.errors.labour_cost}
                        >
                            <Input
                                type="number"
                                min="0"
                                step="0.0001"
                                value={form.data.labour_cost}
                                onChange={(event) =>
                                    form.setData(
                                        'labour_cost',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Other cost"
                            error={form.errors.other_cost}
                        >
                            <Input
                                type="number"
                                min="0"
                                step="0.0001"
                                value={form.data.other_cost}
                                onChange={(event) =>
                                    form.setData(
                                        'other_cost',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>
                )}
                <div className="grid gap-3">
                    <div className="flex items-center justify-between">
                        <Label>Parts used</Label>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={addPart}
                        >
                            <Plus />
                            Add part
                        </Button>
                    </div>
                    {form.data.parts.map((part, index) => (
                        <div
                            key={index}
                            className="grid gap-3 border-t pt-4 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            <Input
                                placeholder="Part code"
                                value={part.part_code}
                                onChange={(event) =>
                                    updatePart(
                                        index,
                                        'part_code',
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                placeholder="Part name"
                                value={part.part_name}
                                onChange={(event) =>
                                    updatePart(
                                        index,
                                        'part_name',
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                type="number"
                                min="0"
                                step="0.0001"
                                placeholder="Quantity"
                                value={part.quantity}
                                onChange={(event) =>
                                    updatePart(
                                        index,
                                        'quantity',
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                placeholder="Unit"
                                value={part.unit}
                                onChange={(event) =>
                                    updatePart(
                                        index,
                                        'unit',
                                        event.target.value,
                                    )
                                }
                            />
                            {canViewCosts && (
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.0001"
                                    placeholder="Unit cost"
                                    value={part.unit_cost}
                                    onChange={(event) =>
                                        updatePart(
                                            index,
                                            'unit_cost',
                                            event.target.value,
                                        )
                                    }
                                />
                            )}
                            <Input
                                placeholder="Provider"
                                value={part.provider_name}
                                onChange={(event) =>
                                    updatePart(
                                        index,
                                        'provider_name',
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                placeholder="Reference"
                                value={part.reference}
                                onChange={(event) =>
                                    updatePart(
                                        index,
                                        'reference',
                                        event.target.value,
                                    )
                                }
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                title="Remove part"
                                onClick={() =>
                                    form.setData(
                                        'parts',
                                        form.data.parts.filter(
                                            (_, partIndex) =>
                                                partIndex !== index,
                                        ),
                                    )
                                }
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    ))}
                    <InputError message={form.errors.parts} />
                </div>
                <Actions
                    processing={form.processing}
                    onCancel={() => setOpen(false)}
                    label="Complete and release"
                />
            </form>
        </ActionDialog>
    );
}

export function MaintenanceCancelDialog({
    workOrder,
}: {
    workOrder: EquipmentMaintenanceWorkOrder;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        reason: '',
        release_status: [
            'available',
            'assigned',
            'idle',
            'out_of_service',
        ].includes(workOrder.prior_equipment_status ?? '')
            ? (workOrder.prior_equipment_status ?? 'available')
            : 'available',
    });
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/equipment-maintenance-work-orders/${workOrder.id}/cancel`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }
    return (
        <ActionDialog
            open={open}
            setOpen={setOpen}
            trigger={
                <>
                    <X />
                    Cancel
                </>
            }
            title="Cancel maintenance work order"
            description="A reason and explicit equipment release state are required."
        >
            <form onSubmit={submit} className="grid gap-5">
                <Field label="Cancellation reason" error={form.errors.reason}>
                    <Textarea
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                    />
                </Field>
                <SelectField
                    label="Release status"
                    value={form.data.release_status}
                    onChange={(value) => form.setData('release_status', value)}
                    options={[
                        'available',
                        'assigned',
                        'idle',
                        'out_of_service',
                    ].map(option)}
                    error={form.errors.release_status}
                />
                <Actions
                    processing={form.processing}
                    onCancel={() => setOpen(false)}
                    label="Cancel work order"
                />
            </form>
        </ActionDialog>
    );
}

function ActionDialog({
    open,
    setOpen,
    trigger,
    title,
    description,
    children,
    wide = false,
}: {
    open: boolean;
    setOpen: (open: boolean) => void;
    trigger: ReactNode;
    title: string;
    description: string;
    children: ReactNode;
    wide?: boolean;
}) {
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    {trigger}
                </Button>
            </DialogTrigger>
            <DialogContent
                className={`max-h-[calc(100vh-2rem)] overflow-y-auto ${wide ? 'sm:max-w-5xl' : 'sm:max-w-xl'}`}
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                {children}
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
    options: Array<{ value: string; label: string }>;
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
function option(value: string) {
    return {
        value,
        label: value
            .replaceAll('_', ' ')
            .replace(/\b\w/g, (letter) => letter.toUpperCase()),
    };
}
