import { useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { Plus } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';
import { DatePicker } from '@/components/date-picker';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { Trade } from './workforce-trade-dialog';

type StaffOption = {
    id: string;
    name: string;
    staff_number: string;
    branch_id: string;
    branch_name: string;
    primary_trade_id: string | null;
    primary_trade_name: string | null;
};

type SiteOption = {
    id: string;
    name: string;
};

type ProjectOption = {
    id: string;
    name: string;
    reference: string;
    branch_id: string;
    branch_name: string;
    sites: SiteOption[];
};

type Props = {
    staff: StaffOption[];
    projects: ProjectOption[];
    trades: Trade[];
};

export function StaffDeploymentDialog({ staff, projects, trades }: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        staff_id: '',
        project_id: '',
        site_id: '',
        workforce_trade_id: '',
        starts_on: format(new Date(), 'yyyy-MM-dd'),
        notes: '',
    });
    const selectedStaff = staff.find(
        (option) => option.id === form.data.staff_id,
    );
    const availableProjects = useMemo(
        () =>
            selectedStaff
                ? projects.filter(
                      (project) =>
                          project.branch_id === selectedStaff.branch_id,
                  )
                : projects,
        [projects, selectedStaff],
    );
    const selectedProject = availableProjects.find(
        (project) => project.id === form.data.project_id,
    );

    function selectStaff(staffId: string) {
        const option = staff.find((entry) => entry.id === staffId);
        const projectStillAvailable = projects.some(
            (project) =>
                project.id === form.data.project_id &&
                project.branch_id === option?.branch_id,
        );

        form.setData((data) => ({
            ...data,
            staff_id: staffId,
            project_id: projectStillAvailable ? data.project_id : '',
            site_id: projectStillAvailable ? data.site_id : '',
            workforce_trade_id:
                option?.primary_trade_id ?? data.workforce_trade_id,
        }));
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/workforce/deployments', {
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus />
                    New deployment
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Deploy staff</DialogTitle>
                    <DialogDescription>
                        Assign a worker to a project or site. A current
                        deployment is ended automatically when the worker is
                        reassigned.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-5 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="deployment_staff" required>
                                Staff member
                            </Label>
                            <SearchableSelect
                                value={form.data.staff_id}
                                onValueChange={selectStaff}
                                options={staff.map((option) => ({
                                    value: option.id,
                                    label: option.name,
                                    description:
                                        option.staff_number +
                                        ' - ' +
                                        option.branch_name,
                                }))}
                                placeholder="Select staff"
                                searchPlaceholder="Search staff..."
                            />
                            <InputError message={form.errors.staff_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="deployment_project" required>
                                Project
                            </Label>
                            <SearchableSelect
                                value={form.data.project_id}
                                onValueChange={(value) =>
                                    form.setData((data) => ({
                                        ...data,
                                        project_id: value,
                                        site_id: '',
                                    }))
                                }
                                options={availableProjects.map((project) => ({
                                    value: project.id,
                                    label: project.name,
                                    description:
                                        project.reference +
                                        ' - ' +
                                        project.branch_name,
                                }))}
                                placeholder="Select project"
                                searchPlaceholder="Search projects..."
                                disabled={!form.data.staff_id}
                            />
                            <InputError message={form.errors.project_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="deployment_site">Site</Label>
                            <SearchableSelect
                                value={form.data.site_id}
                                onValueChange={(value) =>
                                    form.setData('site_id', value)
                                }
                                options={[
                                    {
                                        value: '',
                                        label: 'Project-level deployment',
                                    },
                                    ...(selectedProject?.sites ?? []).map(
                                        (site) => ({
                                            value: site.id,
                                            label: site.name,
                                        }),
                                    ),
                                ]}
                                placeholder="Select site"
                                searchPlaceholder="Search sites..."
                                disabled={!selectedProject}
                            />
                            <InputError message={form.errors.site_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="deployment_trade">Trade</Label>
                            <SearchableSelect
                                value={form.data.workforce_trade_id}
                                onValueChange={(value) =>
                                    form.setData('workforce_trade_id', value)
                                }
                                options={[
                                    {
                                        value: '',
                                        label:
                                            selectedStaff?.primary_trade_name ??
                                            'No trade specified',
                                    },
                                    ...trades
                                        .filter((trade) => trade.is_active)
                                        .map((trade) => ({
                                            value: trade.id,
                                            label: trade.name,
                                        })),
                                ]}
                                placeholder="Use primary trade"
                                searchPlaceholder="Search trades..."
                            />
                            <InputError
                                message={form.errors.workforce_trade_id}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="deployment_starts_on" required>
                                Start date
                            </Label>
                            <DatePicker
                                id="deployment_starts_on"
                                value={form.data.starts_on}
                                onChange={(value) =>
                                    form.setData('starts_on', value)
                                }
                            />
                            <InputError message={form.errors.starts_on} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="deployment_notes">Notes</Label>
                        <Textarea
                            id="deployment_notes"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            placeholder="Optional deployment instructions"
                            rows={3}
                        />
                        <InputError message={form.errors.notes} />
                    </div>

                    <div className="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save deployment
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
