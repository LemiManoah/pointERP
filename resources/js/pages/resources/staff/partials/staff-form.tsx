import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import * as staffRoutes from '@/routes/resources/staff';
import * as workerRoutes from '@/routes/workforce/workers';

export type Staff = {
    person_category: 'company_staff' | 'workforce';
    id: string;
    branch_id: string;
    staff_position_id: string | null;
    employment_type: string;
    employment_type_label: string;
    primary_trade_id: string | null;
    primary_trade_name: string | null;
    staff_number: string;
    name: string;
    email: string | null;
    phone: string | null;
    status: 'active' | 'inactive';
    branch_name: string;
    position_name: string | null;
    has_user: boolean;
};

export type Option = {
    id: string;
    name: string;
};

type StaffFormData = Record<string, string> & {
    person_category: 'company_staff' | 'workforce';
    branch_id: string;
    staff_position_id: string;
    employment_type: string;
    primary_trade_id: string;
    staff_number: string;
    name: string;
    email: string;
    phone: string;
    status: 'active' | 'inactive';
};

type Props = {
    canReclassify?: boolean;
    directory?: 'company_staff' | 'workforce';
    staff?: Staff;
    branches: Option[];
    positions: Option[];
    trades: Option[];
    employmentTypes: Option[];
    onCancel?: () => void;
    onSuccess?: () => void;
};

export function StaffForm({
    canReclassify = false,
    directory = 'company_staff',
    staff,
    branches,
    positions,
    trades,
    employmentTypes,
    onCancel,
    onSuccess,
}: Props) {
    const isWorkerDirectory = directory === 'workforce';
    const routes = isWorkerDirectory ? workerRoutes : staffRoutes;
    const form = useForm<StaffFormData>({
        person_category: staff?.person_category ?? directory,
        branch_id: staff?.branch_id ?? branches[0]?.id ?? '',
        staff_position_id: staff?.staff_position_id ?? (isWorkerDirectory ? '' : positions[0]?.id ?? ''),
        employment_type:
            staff?.employment_type ?? (isWorkerDirectory ? 'casual' : employmentTypes[0]?.id ?? 'permanent'),
        primary_trade_id: staff?.primary_trade_id ?? '',
        staff_number: staff?.staff_number ?? '',
        name: staff?.name ?? '',
        email: staff?.email ?? '',
        phone: staff?.phone ?? '',
        status: staff?.status ?? 'active',
    });
    const isWorker = form.data.person_category === 'workforce';

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (staff) {
            form.put(routes.update.url(staff.id), { onSuccess });

            return;
        }

        form.post(routes.store.url(), {
            onSuccess: () => {
                form.reset();
                onSuccess?.();
            },
        });
    }

    return (
        <form onSubmit={submit} className="grid gap-5">
            {staff && canReclassify && <div className="grid gap-2">
                <Label htmlFor="person-category">Directory</Label>
                <NativeSelect id="person-category" value={form.data.person_category}
                    onChange={(event) => form.setData('person_category', event.target.value as 'company_staff' | 'workforce')}>
                    <NativeSelectOption value="company_staff">Company Staff</NativeSelectOption>
                    <NativeSelectOption value="workforce">Project Workforce</NativeSelectOption>
                </NativeSelect>
                <p className="text-sm text-muted-foreground">Moving a person keeps their assignments, attendance and account history.</p>
                <InputError message={form.errors.person_category} />
            </div>}
            <div className="grid gap-5 md:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="branch_id" required>
                        Branch
                    </Label>
                    <SearchableSelect
                        value={form.data.branch_id}
                        onValueChange={(value) =>
                            form.setData('branch_id', value)
                        }
                        options={branches.map((branch) => ({
                            value: branch.id,
                            label: branch.name,
                        }))}
                        placeholder="Select branch"
                        searchPlaceholder="Search branches..."
                    />
                    <InputError message={form.errors.branch_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="staff_position_id" required={!isWorker}>
                        Position
                    </Label>
                    <SearchableSelect
                        value={form.data.staff_position_id}
                        onValueChange={(value) =>
                            form.setData('staff_position_id', value)
                        }
                        options={positions.map((position) => ({
                            value: position.id,
                            label: position.name,
                        }))}
                        placeholder="Select position"
                        searchPlaceholder="Search positions..."
                    />
                    <InputError message={form.errors.staff_position_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="employment_type" required>
                        Employment type
                    </Label>
                    <SearchableSelect
                        value={form.data.employment_type}
                        onValueChange={(value) =>
                            form.setData('employment_type', value)
                        }
                        options={employmentTypes.map((type) => ({
                            value: type.id,
                            label: type.name,
                        }))}
                        placeholder="Select employment type"
                        searchPlaceholder="Search employment types..."
                    />
                    <InputError message={form.errors.employment_type} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="primary_trade_id" required={isWorker}>Primary trade</Label>
                    <SearchableSelect
                        value={form.data.primary_trade_id}
                        onValueChange={(value) =>
                            form.setData('primary_trade_id', value)
                        }
                        options={[
                            { value: '', label: 'No primary trade' },
                            ...trades.map((trade) => ({
                                value: trade.id,
                                label: trade.name,
                            })),
                        ]}
                        placeholder="Select primary trade"
                        searchPlaceholder="Search trades..."
                    />
                    <InputError message={form.errors.primary_trade_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="staff_number">{isWorker ? 'Worker number' : 'Staff number'}</Label>
                    <Input
                        id="staff_number"
                        value={form.data.staff_number}
                        onChange={(event) =>
                            form.setData(
                                'staff_number',
                                event.target.value.toUpperCase(),
                            )
                        }
                        placeholder="Generated automatically"
                    />
                    <InputError message={form.errors.staff_number} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="name" required>
                        Name
                    </Label>
                    <Input
                        id="name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        placeholder="Full name"
                    />
                    <InputError message={form.errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="email" required={!isWorker}>
                        Email
                    </Label>
                    <Input
                        id="email"
                        type="email"
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                        placeholder="staff@example.com"
                    />
                    <InputError message={form.errors.email} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="phone">Phone</Label>
                    <Input
                        id="phone"
                        value={form.data.phone}
                        onChange={(event) =>
                            form.setData('phone', event.target.value)
                        }
                        placeholder="+256..."
                    />
                    <InputError message={form.errors.phone} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="status" required>
                        Status
                    </Label>
                    <NativeSelect
                        id="status"
                        value={form.data.status}
                        onChange={(event) =>
                            form.setData(
                                'status',
                                event.target.value as 'active' | 'inactive',
                            )
                        }
                        className="w-full"
                    >
                        <NativeSelectOption value="active">
                            Active
                        </NativeSelectOption>
                        <NativeSelectOption value="inactive">
                            Inactive
                        </NativeSelectOption>
                    </NativeSelect>
                    <InputError message={form.errors.status} />
                </div>
            </div>

            <div className="flex justify-end gap-3">
                <Button type="button" variant="outline" onClick={onCancel}>
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    {isWorker ? 'Save worker' : 'Save staff'}
                </Button>
            </div>
        </form>
    );
}
