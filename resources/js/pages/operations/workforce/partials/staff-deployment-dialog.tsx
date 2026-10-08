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
import { store } from '@/routes/workforce/deployments';

type StaffOption = {
    id: string;
    name: string;
    staff_number: string;
    branch_id: string;
    branch_name: string;
    primary_trade_id: string | null;
    primary_trade_name: string | null;
    staff_position_id: string | null;
    position_name: string | null;
    has_active_deployment: boolean;
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
    const [positionFilter, setPositionFilter] = useState('');
    const [tradeFilter, setTradeFilter] = useState('');
    const [availability, setAvailability] = useState('available');
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
    const selectedProject = projects.find(
        (project) => project.id === form.data.project_id,
    );
    const eligibleStaff = useMemo(() => staff.filter((person) =>
        person.branch_id === selectedProject?.branch_id &&
        (!positionFilter || person.staff_position_id === positionFilter) &&
        (!tradeFilter || person.primary_trade_id === tradeFilter) &&
        (availability === 'all' || !person.has_active_deployment)
    ), [staff, selectedProject, positionFilter, tradeFilter, availability]);
    const positions = [...new Map(staff.filter((person) => person.staff_position_id && person.branch_id === selectedProject?.branch_id)
        .map((person) => [person.staff_position_id!, { value: person.staff_position_id!, label: person.position_name ?? 'Position' }])).values()];

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
                option?.primary_trade_id ?? '',
        }));
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(store.url(), {
            onSuccess: () => {
                setOpen(false);
                form.reset();
                setPositionFilter('');
                setTradeFilter('');
                setAvailability('available');
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
                    <DialogTitle>Assign person to project</DialogTitle>
                    <DialogDescription>
                        Assign a worker to a project or site. A current
                        deployment is ended automatically when the worker is
                        reassigned.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-5">
                    <div className="grid gap-5 md:grid-cols-2">
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
                                        staff_id: '',
                                    }))
                                }
                                options={projects.map((project) => ({
                                    value: project.id,
                                    label: project.name,
                                    description:
                                        project.reference +
                                        ' - ' +
                                        project.branch_name,
                                }))}
                                placeholder="Select project"
                                searchPlaceholder="Search projects..."
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

                        <div className="grid gap-2 md:col-span-2">
                            <Label>Filter eligible people</Label>
                            <div className="grid gap-3 md:grid-cols-3">
                                <SearchableSelect value={positionFilter} onValueChange={(value) => { setPositionFilter(value); form.setData('staff_id', ''); }}
                                    options={[{ value: '', label: 'All positions' }, ...positions]} placeholder="Position" disabled={!selectedProject} />
                                <SearchableSelect value={tradeFilter} onValueChange={(value) => { setTradeFilter(value); form.setData('staff_id', ''); }}
                                    options={[{ value: '', label: 'All trades' }, ...trades.filter((trade) => trade.is_active).map((trade) => ({ value: trade.id, label: trade.name }))]} placeholder="Trade" disabled={!selectedProject} />
                                <SearchableSelect value={availability} onValueChange={(value) => { setAvailability(value); form.setData('staff_id', ''); }}
                                    options={[{ value: 'available', label: 'Available people' }, { value: 'all', label: 'Include deployed people' }]} placeholder="Availability" />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="deployment_staff" required>
                                Person
                            </Label>
                            <SearchableSelect
                                value={form.data.staff_id}
                                onValueChange={selectStaff}
                                options={eligibleStaff.map((option) => ({
                                    value: option.id,
                                    label: option.name,
                                    description:
                                        option.staff_number +
                                        ' - ' +
                                        (option.position_name ?? option.primary_trade_name ?? option.branch_name) + (option.has_active_deployment ? ' · Currently deployed' : ' · Available'),
                                }))}
                                placeholder="Select eligible person"
                                searchPlaceholder="Search people..."
                                disabled={!selectedProject}
                                emptyMessage="No people match these filters."
                            />
                            <InputError message={form.errors.staff_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="deployment_trade">Trade for this assignment</Label>
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
