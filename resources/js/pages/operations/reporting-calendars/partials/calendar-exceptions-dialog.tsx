import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { useConfirmDialog } from '@/components/confirm-dialog-provider';
import { DatePicker } from '@/components/date-picker';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { CalendarRow } from './calendar-dialog';

export function CalendarExceptionsDialog({
    calendar,
}: {
    calendar: CalendarRow;
}) {
    const [open, setOpen] = useState(false);
    const confirm = useConfirmDialog();
    const form = useForm({
        exception_date: '',
        type: 'non_working',
        name: '',
        reason: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/reporting-calendars/${calendar.id}/exceptions`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    Exceptions ({calendar.exceptions.length})
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto sm:max-w-3xl">
                <div className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>{calendar.name} exceptions</DialogTitle>
                        <DialogDescription>
                            Override a normal weekday for a holiday, shutdown or
                            planned working day.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={submit}
                        className="grid gap-4 rounded-md border p-4 sm:grid-cols-2"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="exception_date">Date</Label>
                            <DatePicker
                                id="exception_date"
                                value={form.data.exception_date}
                                onChange={(value) =>
                                    form.setData('exception_date', value)
                                }
                                placeholder="Select exception date"
                            />
                            <InputError message={form.errors.exception_date} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Behaviour</Label>
                            <SearchableSelect
                                value={form.data.type}
                                onValueChange={(value) =>
                                    form.setData('type', value)
                                }
                                options={[
                                    {
                                        value: 'non_working',
                                        label: 'Non-working day',
                                    },
                                    {
                                        value: 'working_override',
                                        label: 'Working-day override',
                                    },
                                ]}
                            />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="exception_name">Name</Label>
                            <Input
                                id="exception_name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="exception_reason">Reason</Label>
                            <Textarea
                                id="exception_reason"
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                            />
                        </div>
                        <div className="flex justify-end sm:col-span-2">
                            <Button type="submit" disabled={form.processing}>
                                Add exception
                            </Button>
                        </div>
                    </form>

                    <div className="overflow-hidden rounded-md border">
                        {calendar.exceptions.map((exception) => (
                            <div
                                key={exception.id}
                                className="flex items-start justify-between gap-4 border-b p-3 last:border-b-0"
                            >
                                <div>
                                    <div className="font-medium">
                                        {exception.name}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        {exception.exception_date} -{' '}
                                        {exception.type.replaceAll('_', ' ')}
                                    </div>
                                    {exception.reason && (
                                        <div className="mt-1 text-sm">
                                            {exception.reason}
                                        </div>
                                    )}
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        confirm({
                                            title: 'Remove exception?',
                                            description: `${exception.name} will no longer override this calendar.`,
                                            confirmLabel: 'Remove',
                                            variant: 'destructive',
                                            onConfirm: () =>
                                                router.delete(
                                                    `/reporting-calendars/${calendar.id}/exceptions/${exception.id}`,
                                                    { preserveScroll: true },
                                                ),
                                        })
                                    }
                                >
                                    Remove
                                </Button>
                            </div>
                        ))}
                        {calendar.exceptions.length === 0 && (
                            <div className="p-8 text-center text-sm text-muted-foreground">
                                No dated exceptions configured.
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Close
                        </Button>
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>
    );
}
