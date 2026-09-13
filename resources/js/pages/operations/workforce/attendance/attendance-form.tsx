import { useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { CheckCircle2, Pencil, Plus, Trash2, Users } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';
import { DatePicker } from '@/components/date-picker';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';

export type AttendanceLine = {
    key: string;
    labour_source: string;
    staff_id: string;
    subcontractor_id: string;
    workforce_trade_id: string;
    worker_name_snapshot: string;
    headcount: number;
    attendance_status: string;
    regular_hours_per_person: string;
    overtime_hours_per_person: string;
    notes: string;
};
export type AttendanceOptions = {
    projects: {
        id: string;
        name: string;
        reference: string;
        branch_id: string;
        sites: { id: string; name: string }[];
    }[];
    staff: {
        id: string;
        name: string;
        staff_number: string;
        branch_id: string;
        employment_type: string;
        primary_trade_id: string | null;
    }[];
    deployments: {
        staff_id: string;
        project_id: string;
        site_id: string | null;
        workforce_trade_id: string | null;
        starts_on: string;
        ends_on: string | null;
    }[];
    trades: { id: string; name: string }[];
    subcontractors: { id: string; name: string; branch_id: string | null }[];
    shifts: { value: string; label: string }[];
    attendanceStatuses: { value: string; label: string }[];
};
export type InitialRegister = {
    id: string;
    project_id: string;
    site_id: string;
    attendance_date: string;
    shift: string;
    notes: string | null;
    records: Array<{
        id: string;
        labour_source: string;
        staff_id: string | null;
        subcontractor_id: string | null;
        workforce_trade_id: string;
        worker_name: string | null;
        headcount: number;
        attendance_status: string;
        regular_hours_per_person: string;
        overtime_hours_per_person: string;
        notes: string | null;
    }>;
};
type Props = AttendanceOptions & { register?: InitialRegister };
const makeKey = () => String(Date.now()) + '-' + String(Math.random()).slice(2);
const blankLine = (): AttendanceLine => ({
    key: makeKey(),
    labour_source: 'internal',
    staff_id: '',
    subcontractor_id: '',
    workforce_trade_id: '',
    worker_name_snapshot: '',
    headcount: 1,
    attendance_status: 'present',
    regular_hours_per_person: '8',
    overtime_hours_per_person: '0',
    notes: '',
});

export function AttendanceForm({
    register,
    projects,
    staff,
    deployments,
    trades,
    subcontractors,
    shifts,
    attendanceStatuses,
}: Props) {
    const form = useForm({
        project_id: register?.project_id ?? '',
        site_id: register?.site_id ?? '',
        attendance_date:
            register?.attendance_date ?? format(new Date(), 'yyyy-MM-dd'),
        shift: register?.shift ?? 'day',
        notes: register?.notes ?? '',
        records:
            register?.records.map((record) => ({
                key: record.id,
                labour_source: record.labour_source,
                staff_id: record.staff_id ?? '',
                subcontractor_id: record.subcontractor_id ?? '',
                workforce_trade_id: record.workforce_trade_id,
                worker_name_snapshot: record.worker_name ?? '',
                headcount: record.headcount,
                attendance_status: record.attendance_status,
                regular_hours_per_person: record.regular_hours_per_person,
                overtime_hours_per_person: record.overtime_hours_per_person,
                notes: record.notes ?? '',
            })) ?? [],
    });
    const [dialogOpen, setDialogOpen] = useState(false);
    const [line, setLine] = useState<AttendanceLine>(blankLine());
    const [editingKey, setEditingKey] = useState<string | null>(null);
    const selectedProject = projects.find(
        (project) => project.id === form.data.project_id,
    );
    const selectedSite = selectedProject?.sites.find(
        (site) => site.id === form.data.site_id,
    );
    const selectedStaff = staff.find((worker) => worker.id === line.staff_id);
    const isSubcontractor = line.labour_source === 'subcontractor';
    const totals = useMemo(
        () =>
            form.data.records.reduce(
                (result, record) => ({
                    headcount: result.headcount + Number(record.headcount),
                    hours:
                        result.hours +
                        Number(record.headcount) *
                            (Number(record.regular_hours_per_person) +
                                Number(record.overtime_hours_per_person)),
                }),
                { headcount: 0, hours: 0 },
            ),
        [form.data.records],
    );

    function openNew(source = 'internal') {
        setEditingKey(null);
        setLine({ ...blankLine(), labour_source: source });
        setDialogOpen(true);
    }
    function openEdit(record: AttendanceLine) {
        setEditingKey(record.key);
        setLine({ ...record });
        setDialogOpen(true);
    }
    function saveLine() {
        const normalized = {
            ...line,
            headcount: isSubcontractor ? Number(line.headcount) : 1,
            worker_name_snapshot: isSubcontractor
                ? line.worker_name_snapshot
                : (selectedStaff?.name ?? line.worker_name_snapshot),
        };
        form.setData(
            'records',
            editingKey
                ? form.data.records.map((record) =>
                      record.key === editingKey ? normalized : record,
                  )
                : [...form.data.records, normalized],
        );
        setDialogOpen(false);
    }
    function loadDeployedStaff() {
        if (!selectedProject || !selectedSite) return;
        const existing = new Set(
            form.data.records.map((record) => record.staff_id).filter(Boolean),
        );
        const additions = deployments
            .filter(
                (deployment) =>
                    deployment.project_id === selectedProject.id &&
                    (deployment.site_id === null ||
                        deployment.site_id === selectedSite.id) &&
                    deployment.starts_on <= form.data.attendance_date &&
                    (deployment.ends_on === null ||
                        deployment.ends_on >= form.data.attendance_date),
            )
            .flatMap((deployment) => {
                const worker = staff.find(
                    (entry) => entry.id === deployment.staff_id,
                );
                if (!worker || existing.has(worker.id)) return [];
                return [
                    {
                        ...blankLine(),
                        labour_source:
                            worker.employment_type === 'casual'
                                ? 'casual'
                                : 'internal',
                        staff_id: worker.id,
                        worker_name_snapshot: worker.name,
                        workforce_trade_id:
                            deployment.workforce_trade_id ??
                            worker.primary_trade_id ??
                            '',
                    },
                ];
            });
        form.setData('records', [...form.data.records, ...additions]);
    }
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const payload = {
            ...form.data,
            records: form.data.records.map(
                ({ key: _key, ...record }) => record,
            ),
        };
        form.transform(() => payload);
        if (register) form.put('/workforce/attendance/' + register.id);
        else form.post('/workforce/attendance');
    }

    return (
        <>
            <form onSubmit={submit} className="grid gap-6">
                <Card>
                    <CardContent className="grid gap-5 pt-6 md:grid-cols-2 lg:grid-cols-4">
                        <div className="grid gap-2">
                            <Label required>Project</Label>
                            <SearchableSelect
                                value={form.data.project_id}
                                onValueChange={(value) =>
                                    form.setData((data) => ({
                                        ...data,
                                        project_id: value,
                                        site_id: '',
                                        records: [],
                                    }))
                                }
                                options={projects.map((project) => ({
                                    value: project.id,
                                    label: project.name,
                                    description: project.reference,
                                }))}
                                placeholder="Select project"
                                searchPlaceholder="Search projects..."
                            />
                            <InputError message={form.errors.project_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label required>Site</Label>
                            <SearchableSelect
                                value={form.data.site_id}
                                onValueChange={(value) =>
                                    form.setData((data) => ({
                                        ...data,
                                        site_id: value,
                                        records: [],
                                    }))
                                }
                                options={(selectedProject?.sites ?? []).map(
                                    (site) => ({
                                        value: site.id,
                                        label: site.name,
                                    }),
                                )}
                                placeholder="Select site"
                                searchPlaceholder="Search sites..."
                                disabled={!selectedProject}
                            />
                            <InputError message={form.errors.site_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label required>Date</Label>
                            <DatePicker
                                value={form.data.attendance_date}
                                onChange={(value) =>
                                    form.setData('attendance_date', value)
                                }
                            />
                            <InputError message={form.errors.attendance_date} />
                        </div>
                        <div className="grid gap-2">
                            <Label required>Shift</Label>
                            <SearchableSelect
                                value={form.data.shift}
                                onValueChange={(value) =>
                                    form.setData('shift', value)
                                }
                                options={shifts}
                                placeholder="Select shift"
                            />
                            <InputError message={form.errors.shift} />
                        </div>
                        <div className="grid gap-2 md:col-span-2 lg:col-span-4">
                            <Label>Register notes</Label>
                            <Textarea
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                                rows={2}
                                placeholder="Optional shift-wide note"
                            />
                        </div>
                    </CardContent>
                </Card>

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="font-semibold">Attendance lines</h2>
                        <p className="text-sm text-muted-foreground">
                            {totals.headcount.toLocaleString()} people,{' '}
                            {totals.hours.toLocaleString()} person-hours
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={loadDeployedStaff}
                            disabled={!selectedSite}
                        >
                            <Users />
                            Load deployed staff
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.data.records.length === 0}
                            onClick={() =>
                                form.setData(
                                    'records',
                                    form.data.records.map((record) => ({
                                        ...record,
                                        attendance_status: 'present',
                                        regular_hours_per_person:
                                            record.regular_hours_per_person ===
                                            '0'
                                                ? '8'
                                                : record.regular_hours_per_person,
                                    })),
                                )
                            }
                        >
                            <CheckCircle2 />
                            Mark all present
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => openNew('subcontractor')}
                        >
                            <Plus />
                            Add subcontractor crew
                        </Button>
                        <Button type="button" onClick={() => openNew()}>
                            <Plus />
                            Add worker
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Worker / crew</TableHead>
                                        <TableHead>Trade</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            People
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Regular
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Overtime
                                        </TableHead>
                                        <TableHead className="w-24" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {form.data.records.map((record) => {
                                        const worker = staff.find(
                                            (entry) =>
                                                entry.id === record.staff_id,
                                        );
                                        const company = subcontractors.find(
                                            (entry) =>
                                                entry.id ===
                                                record.subcontractor_id,
                                        );
                                        return (
                                            <TableRow key={record.key}>
                                                <TableCell>
                                                    <div className="font-medium">
                                                        {worker?.name ??
                                                            record.worker_name_snapshot ??
                                                            company?.name ??
                                                            'Unnamed crew'}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {worker?.staff_number ??
                                                            company?.name ??
                                                            record.labour_source}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {trades.find(
                                                        (trade) =>
                                                            trade.id ===
                                                            record.workforce_trade_id,
                                                    )?.name ?? 'Not set'}
                                                </TableCell>
                                                <TableCell>
                                                    {
                                                        attendanceStatuses.find(
                                                            (status) =>
                                                                status.value ===
                                                                record.attendance_status,
                                                        )?.label
                                                    }
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {record.headcount}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {
                                                        record.regular_hours_per_person
                                                    }
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {
                                                        record.overtime_hours_per_person
                                                    }
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex justify-end gap-1">
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            title="Edit line"
                                                            onClick={() =>
                                                                openEdit(record)
                                                            }
                                                        >
                                                            <Pencil />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            title="Remove line"
                                                            onClick={() =>
                                                                form.setData(
                                                                    'records',
                                                                    form.data.records.filter(
                                                                        (
                                                                            entry,
                                                                        ) =>
                                                                            entry.key !==
                                                                            record.key,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                    {form.data.records.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
                                                className="h-28 text-center text-muted-foreground"
                                            >
                                                Select a site and load deployed
                                                staff, or add a worker or crew.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
                <InputError message={form.errors.records} />
                <div className="flex justify-end gap-3">
                    <Button asChild type="button" variant="outline">
                        <a href="/workforce/attendance">Cancel</a>
                    </Button>
                    <Button
                        type="submit"
                        disabled={
                            form.processing || form.data.records.length === 0
                        }
                    >
                        {form.processing && <Spinner />}Save draft
                    </Button>
                </div>
            </form>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editingKey
                                ? 'Edit attendance line'
                                : 'Add attendance line'}
                        </DialogTitle>
                        <DialogDescription>
                            Record one employee or one subcontractor crew.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-5 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label required>Labour source</Label>
                            <SearchableSelect
                                value={line.labour_source}
                                onValueChange={(value) =>
                                    setLine({
                                        ...line,
                                        labour_source: value,
                                        staff_id: '',
                                        subcontractor_id: '',
                                        headcount: 1,
                                    })
                                }
                                options={[
                                    {
                                        value: 'internal',
                                        label: 'Internal staff',
                                    },
                                    { value: 'casual', label: 'Casual labour' },
                                    {
                                        value: 'subcontractor',
                                        label: 'Subcontractor',
                                    },
                                ]}
                            />
                        </div>
                        {isSubcontractor ? (
                            <>
                                <div className="grid gap-2">
                                    <Label required>Subcontractor</Label>
                                    <SearchableSelect
                                        value={line.subcontractor_id}
                                        onValueChange={(value) =>
                                            setLine({
                                                ...line,
                                                subcontractor_id: value,
                                            })
                                        }
                                        options={subcontractors
                                            .filter(
                                                (company) =>
                                                    company.branch_id ===
                                                        null ||
                                                    company.branch_id ===
                                                        selectedProject?.branch_id,
                                            )
                                            .map((company) => ({
                                                value: company.id,
                                                label: company.name,
                                            }))}
                                        placeholder="Select company"
                                        searchPlaceholder="Search companies..."
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Crew / worker names</Label>
                                    <Input
                                        value={line.worker_name_snapshot}
                                        onChange={(event) =>
                                            setLine({
                                                ...line,
                                                worker_name_snapshot:
                                                    event.target.value,
                                            })
                                        }
                                        placeholder="Optional"
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label required>Headcount</Label>
                                    <Input
                                        type="number"
                                        min="1"
                                        value={line.headcount}
                                        onChange={(event) =>
                                            setLine({
                                                ...line,
                                                headcount: Number(
                                                    event.target.value,
                                                ),
                                            })
                                        }
                                    />
                                </div>
                            </>
                        ) : (
                            <div className="grid gap-2">
                                <Label required>Staff member</Label>
                                <SearchableSelect
                                    value={line.staff_id}
                                    onValueChange={(value) => {
                                        const worker = staff.find(
                                            (entry) => entry.id === value,
                                        );
                                        setLine({
                                            ...line,
                                            staff_id: value,
                                            labour_source:
                                                worker?.employment_type ===
                                                'casual'
                                                    ? 'casual'
                                                    : 'internal',
                                            workforce_trade_id:
                                                worker?.primary_trade_id ??
                                                line.workforce_trade_id,
                                        });
                                    }}
                                    options={staff
                                        .filter(
                                            (worker) =>
                                                worker.branch_id ===
                                                selectedProject?.branch_id,
                                        )
                                        .map((worker) => ({
                                            value: worker.id,
                                            label: worker.name,
                                            description: worker.staff_number,
                                        }))}
                                    placeholder="Select staff"
                                    searchPlaceholder="Search staff..."
                                />
                            </div>
                        )}
                        <div className="grid gap-2">
                            <Label required>Trade</Label>
                            <SearchableSelect
                                value={line.workforce_trade_id}
                                onValueChange={(value) =>
                                    setLine({
                                        ...line,
                                        workforce_trade_id: value,
                                    })
                                }
                                options={trades.map((trade) => ({
                                    value: trade.id,
                                    label: trade.name,
                                }))}
                                placeholder="Select trade"
                                searchPlaceholder="Search trades..."
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label required>Status</Label>
                            <SearchableSelect
                                value={line.attendance_status}
                                onValueChange={(value) =>
                                    setLine({
                                        ...line,
                                        attendance_status: value,
                                        regular_hours_per_person:
                                            value === 'present' ? '8' : '0',
                                        overtime_hours_per_person: '0',
                                    })
                                }
                                options={attendanceStatuses}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label required>Regular hours per person</Label>
                            <Input
                                type="number"
                                min="0"
                                max="24"
                                step="0.25"
                                disabled={line.attendance_status !== 'present'}
                                value={line.regular_hours_per_person}
                                onChange={(event) =>
                                    setLine({
                                        ...line,
                                        regular_hours_per_person:
                                            event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label required>Overtime hours per person</Label>
                            <Input
                                type="number"
                                min="0"
                                max="24"
                                step="0.25"
                                disabled={line.attendance_status !== 'present'}
                                value={line.overtime_hours_per_person}
                                onChange={(event) =>
                                    setLine({
                                        ...line,
                                        overtime_hours_per_person:
                                            event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="grid gap-2 md:col-span-2">
                            <Label>Notes</Label>
                            <Textarea
                                value={line.notes}
                                onChange={(event) =>
                                    setLine({
                                        ...line,
                                        notes: event.target.value,
                                    })
                                }
                                rows={2}
                                placeholder="Optional attendance note"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDialogOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={saveLine}
                            disabled={
                                !line.workforce_trade_id ||
                                (isSubcontractor
                                    ? !line.subcontractor_id
                                    : !line.staff_id)
                            }
                        >
                            Save line
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
