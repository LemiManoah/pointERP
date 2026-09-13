import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { AttendanceForm, type AttendanceOptions } from './attendance-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Attendance', href: '/workforce/attendance' },
    { title: 'New', href: '/workforce/attendance/create' },
];

export default function CreateAttendance(props: AttendanceOptions) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New site attendance" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        New site attendance
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Prepare the attendance register, then confirm it after
                        checking the shift record.
                    </p>
                </div>
                <AttendanceForm {...props} />
            </div>
        </AppLayout>
    );
}
