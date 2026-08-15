import { useForm } from '@inertiajs/react';
import { CornerDownLeft, UserRoundCheck } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo, useState } from 'react';
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
    EquipmentAssignment,
    EquipmentLocation,
    EquipmentRecord,
    ProjectOption,
    SiteOption,
    StaffOption,
} from '../types';

function localNow(): string {
    const now = new Date();
    return new Date(now.getTime() - now.getTimezoneOffset() * 60_000)
        .toISOString()
        .slice(0, 16);
}

type AssignmentProps = {
    equipment: EquipmentRecord;
    projects: ProjectOption[];
    sites: SiteOption[];
    locations: EquipmentLocation[];
    staff: StaffOption[];
};

export function EquipmentAssignmentDialog({
    equipment,
    projects,
    sites,
    locations,
    staff,
}: AssignmentProps) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        project_id: '',
        site_id: '',
        equipment_location_id: '',
        custodian_staff_id: '',
        external_custodian_name: '',
        external_custodian_employer: '',
        assigned_at: localNow(),
        expected_return_at: '',
        handover_meter_reading: equipment.current_meter_reading ?? '',
        handover_condition: equipment.condition_summary ?? '',
        assignment_notes: '',
    });
    const projectOptions = projects.filter(
        (project) => project.branch_id === equipment.branch_id,
    );
    const siteOptions = sites.filter(
        (site) => site.project_id === form.data.project_id,
    );
    const locationOptions = locations.filter(
        (location) =>
            location.is_active &&
            location.branch_id === equipment.branch_id &&
            (!location.project_id ||
                location.project_id === form.data.project_id) &&
            (!location.site_id || location.site_id === form.data.site_id),
    );
    const staffOptions = staff.filter(
        (person) => person.branch_id === equipment.branch_id,
    );

    function selectProject(value: string) {
        form.setData('project_id', value);
        form.setData('site_id', '');
        form.setData('equipment_location_id', '');
    }

    function selectSite(value: string) {
        form.setData('site_id', value);
        form.setData('equipment_location_id', '');
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/equipment/${equipment.id}/assignments`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <UserRoundCheck /> Assign equipment
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Assign and hand over equipment</DialogTitle>
                    <DialogDescription>
                        {equipment.asset_code} will become assigned when this
                        handover is saved.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            label="Project"
                            error={form.errors.project_id}
                            value={form.data.project_id}
                            onChange={selectProject}
                            options={projectOptions.map((project) => ({
                                value: project.id,
                                label: project.name,
                                description: project.reference,
                            }))}
                        />
                        <SelectField
                            label="Site"
                            error={form.errors.site_id}
                            value={form.data.site_id}
                            onChange={selectSite}
                            options={siteOptions.map((site) => ({
                                value: site.id,
                                label: site.name,
                                description: site.reference,
                            }))}
                        />
                        <SelectField
                            label="Handover location"
                            error={form.errors.equipment_location_id}
                            value={form.data.equipment_location_id}
                            onChange={(value) =>
                                form.setData('equipment_location_id', value)
                            }
                            options={locationOptions.map((location) => ({
                                value: location.id,
                                label: location.name,
                                description: location.code,
                            }))}
                        />
                        <SelectField
                            label="Internal custodian"
                            error={form.errors.custodian_staff_id}
                            value={form.data.custodian_staff_id}
                            onChange={(value) =>
                                form.setData('custodian_staff_id', value)
                            }
                            options={[
                                { value: '', label: 'External custodian' },
                                ...staffOptions.map((person) => ({
                                    value: person.id,
                                    label: person.name,
                                    description: person.staff_number,
                                })),
                            ]}
                        />
                        {!form.data.custodian_staff_id && (
                            <>
                                <Field
                                    label="External custodian"
                                    error={form.errors.external_custodian_name}
                                >
                                    <Input
                                        value={
                                            form.data.external_custodian_name
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'external_custodian_name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Employer"
                                    error={
                                        form.errors.external_custodian_employer
                                    }
                                >
                                    <Input
                                        value={
                                            form.data
                                                .external_custodian_employer
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'external_custodian_employer',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </>
                        )}
                        <Field
                            label="Handed over at"
                            error={form.errors.assigned_at}
                        >
                            <Input
                                type="datetime-local"
                                value={form.data.assigned_at}
                                onChange={(event) =>
                                    form.setData(
                                        'assigned_at',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label="Expected return"
                            error={form.errors.expected_return_at}
                        >
                            <Input
                                type="datetime-local"
                                value={form.data.expected_return_at}
                                onChange={(event) =>
                                    form.setData(
                                        'expected_return_at',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        {equipment.meter_type !== 'none' && (
                            <Field
                                label="Handover meter"
                                error={form.errors.handover_meter_reading}
                            >
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.0001"
                                    value={form.data.handover_meter_reading}
                                    onChange={(event) =>
                                        form.setData(
                                            'handover_meter_reading',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        )}
                    </div>
                    <Field
                        label="Handover condition"
                        error={form.errors.handover_condition}
                    >
                        <Textarea
                            value={form.data.handover_condition}
                            onChange={(event) =>
                                form.setData(
                                    'handover_condition',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <Field label="Notes" error={form.errors.assignment_notes}>
                        <Textarea
                            value={form.data.assignment_notes}
                            onChange={(event) =>
                                form.setData(
                                    'assignment_notes',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <Actions
                        processing={form.processing}
                        onCancel={() => setOpen(false)}
                        label="Complete handover"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function EquipmentReturnDialog({
    equipment,
    assignment,
    locations,
}: {
    equipment: EquipmentRecord;
    assignment: EquipmentAssignment;
    locations: EquipmentLocation[];
}) {
    const [open, setOpen] = useState(false);
    const locationOptions = useMemo(
        () =>
            locations.filter(
                (location) =>
                    location.is_active &&
                    location.branch_id === equipment.branch_id,
            ),
        [equipment.branch_id, locations],
    );
    const form = useForm({
        return_location_id:
            equipment.default_location_id ?? locationOptions[0]?.id ?? '',
        returned_at: localNow(),
        return_meter_reading: equipment.current_meter_reading ?? '',
        return_condition: equipment.condition_summary ?? '',
        return_notes: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/equipment-assignments/${assignment.id}/return`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <CornerDownLeft /> Return equipment
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Accept equipment return</DialogTitle>
                    <DialogDescription>
                        Close the assignment to {assignment.site_name} and
                        record the received condition.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <SelectField
                        label="Return location"
                        error={form.errors.return_location_id}
                        value={form.data.return_location_id}
                        onChange={(value) =>
                            form.setData('return_location_id', value)
                        }
                        options={locationOptions.map((location) => ({
                            value: location.id,
                            label: location.name,
                            description: location.code,
                        }))}
                    />
                    <Field label="Returned at" error={form.errors.returned_at}>
                        <Input
                            type="datetime-local"
                            value={form.data.returned_at}
                            onChange={(event) =>
                                form.setData('returned_at', event.target.value)
                            }
                        />
                    </Field>
                    {equipment.meter_type !== 'none' && (
                        <Field
                            label="Return meter"
                            error={form.errors.return_meter_reading}
                        >
                            <Input
                                type="number"
                                min="0"
                                step="0.0001"
                                value={form.data.return_meter_reading}
                                onChange={(event) =>
                                    form.setData(
                                        'return_meter_reading',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    )}
                    <Field
                        label="Return condition"
                        error={form.errors.return_condition}
                    >
                        <Textarea
                            value={form.data.return_condition}
                            onChange={(event) =>
                                form.setData(
                                    'return_condition',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <Field
                        label="Return notes"
                        error={form.errors.return_notes}
                    >
                        <Textarea
                            value={form.data.return_notes}
                            onChange={(event) =>
                                form.setData('return_notes', event.target.value)
                            }
                        />
                    </Field>
                    <Actions
                        processing={form.processing}
                        onCancel={() => setOpen(false)}
                        label="Accept return"
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
        <div className="grid min-w-0 gap-2">
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
