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

export type Staff = {
    id: string;
    branch_id: string;
    staff_position_id: string;
    staff_number: string;
    name: string;
    email: string;
    phone: string | null;
    status: 'active' | 'inactive';
    branch_name: string;
    position_name: string;
    has_user: boolean;
};

export type Option = {
    id: string;
    name: string;
};

type StaffFormData = Record<string, string> & {
    branch_id: string;
    staff_position_id: string;
    staff_number: string;
    name: string;
    email: string;
    phone: string;
    status: 'active' | 'inactive';
};

type Props = {
    staff?: Staff;
    branches: Option[];
    positions: Option[];
    onCancel?: () => void;
    onSuccess?: () => void;
};

export function StaffForm({
    staff,
    branches,
    positions,
    onCancel,
    onSuccess,
}: Props) {
    const form = useForm<StaffFormData>({
        branch_id: staff?.branch_id ?? branches[0]?.id ?? '',
        staff_position_id: staff?.staff_position_id ?? positions[0]?.id ?? '',
        staff_number: staff?.staff_number ?? '',
        name: staff?.name ?? '',
        email: staff?.email ?? '',
        phone: staff?.phone ?? '',
        status: staff?.status ?? 'active',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (staff) {
            form.put(`/resources/staff/${staff.id}`, {
                onSuccess,
            });

            return;
        }

        form.post('/resources/staff', {
            onSuccess: () => {
                form.reset();
                onSuccess?.();
            },
        });
    }

    return (
        <form onSubmit={submit} className="grid gap-5">
            <div className="grid gap-2">
                <Label htmlFor="branch_id">Branch</Label>
                <SearchableSelect
                    value={form.data.branch_id}
                    onValueChange={(value) => form.setData('branch_id', value)}
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
                <Label htmlFor="staff_position_id">Position</Label>
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
                <Label htmlFor="staff_number">Staff number</Label>
                <Input
                    id="staff_number"
                    value={form.data.staff_number}
                    onChange={(event) =>
                        form.setData(
                            'staff_number',
                            event.target.value.toUpperCase(),
                        )
                    }
                    placeholder="POINT-002"
                />
                <InputError message={form.errors.staff_number} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
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
                <Label htmlFor="email">Email</Label>
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
                <Label htmlFor="status">Status</Label>
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

            <div className="flex justify-end gap-3">
                <Button type="button" variant="outline" onClick={onCancel}>
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Save staff
                </Button>
            </div>
        </form>
    );
}
