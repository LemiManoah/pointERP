import { Head, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { CheckCircle2, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import {
    AttendanceForm,
    type AttendanceOptions,
    type InitialRegister,
} from './attendance-form';

type Register = Omit<InitialRegister, 'records'> & {
    project_name: string;
    project_reference: string;
    site_name: string;
    shift_label: string;
    status: 'draft' | 'confirmed';
    status_label: string;
    headcount: number;
    person_hours: number;
    recorded_by_name: string;
    confirmed_by_name: string | null;
    confirmed_at: string | null;
    reopen_reason: string | null;
    reopened_by_name: string | null;
    records: Array<
        InitialRegister['records'][number] & {
            worker_name: string | null;
            subcontractor_name: string | null;
            trade_name: string;
            attendance_status_label: string;
            person_hours: number;
        }
    >;
};

type Props = AttendanceOptions & {
    register: Register;
    can: { update: boolean; confirm: boolean; reopen: boolean };
};

export default function ShowAttendance({ register, can, ...options }: Props) {
    const confirm = useConfirmDialog();
    const [reopenOpen, setReopenOpen] = useState(false);
    const [reason, setReason] = useState('');
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Attendance', href: '/workforce/attendance' },
        {
            title: register.site_name,
            href: '/workforce/attendance/' + register.id,
        },
    ];

    if (register.status === 'draft' && can.update) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Edit site attendance" />
                <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h1 className="text-2xl font-semibold">
                                Edit site attendance
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {register.project_name} · {register.site_name} ·{' '}
                                {format(
                                    parseISO(register.attendance_date),
                                    'dd MMM yyyy',
                                )}
                            </p>
                        </div>
                        {can.confirm && (
                            <Button
                                onClick={() =>
                                    confirm({
                                        title: 'Confirm attendance?',
                                        description:
                                            'Confirmation locks this register against editing until an authorized user reopens it.',
                                        confirmLabel: 'Confirm register',
                                        onConfirm: () =>
                                            router.post(
                                                '/workforce/attendance/' +
                                                    register.id +
                                                    '/confirm',
                                            ),
                                    })
                                }
                            >
                                <CheckCircle2 />
                                Confirm
                            </Button>
                        )}
                    </div>
                    <AttendanceForm {...options} register={register} />
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Site attendance" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-semibold">
                                {register.site_name}
                            </h1>
                            <Badge>{register.status_label}</Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {register.project_name} ·{' '}
                            {format(
                                parseISO(register.attendance_date),
                                'dd MMM yyyy',
                            )}{' '}
                            · {register.shift_label}
                        </p>
                    </div>
                    {can.reopen && (
                        <Button
                            variant="outline"
                            onClick={() => setReopenOpen(true)}
                        >
                            <RotateCcw />
                            Reopen
                        </Button>
                    )}
                </div>
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Headcount</CardTitle>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">
                            {register.headcount.toLocaleString()}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Person-hours
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-2xl font-semibold">
                            {register.person_hours.toLocaleString()}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Recorded by
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="font-medium">
                                {register.recorded_by_name}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                Confirmed by{' '}
                                {register.confirmed_by_name ?? 'Not confirmed'}
                            </div>
                        </CardContent>
                    </Card>
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
                                        <TableHead className="text-right">
                                            Person-hours
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {register.records.map((record) => (
                                        <TableRow key={record.id}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {record.worker_name ||
                                                        record.subcontractor_name ||
                                                        'Subcontractor crew'}
                                                </div>
                                                {record.subcontractor_name && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {
                                                            record.subcontractor_name
                                                        }
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {record.trade_name}
                                            </TableCell>
                                            <TableCell>
                                                {record.attendance_status_label}
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
                                            <TableCell className="text-right">
                                                {record.person_hours.toLocaleString()}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
                <Dialog open={reopenOpen} onOpenChange={setReopenOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Reopen attendance</DialogTitle>
                            <DialogDescription>
                                Give the correction reason. Reopening and later
                                edits remain in the audit trail.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label required>Reason</Label>
                            <Textarea
                                value={reason}
                                onChange={(event) =>
                                    setReason(event.target.value)
                                }
                                rows={4}
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setReopenOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                disabled={reason.trim().length < 10}
                                onClick={() =>
                                    router.post(
                                        '/workforce/attendance/' +
                                            register.id +
                                            '/reopen',
                                        { reason },
                                        {
                                            onSuccess: () =>
                                                setReopenOpen(false),
                                        },
                                    )
                                }
                            >
                                Reopen register
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
