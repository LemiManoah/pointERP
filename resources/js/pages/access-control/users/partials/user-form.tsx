import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export type AccessUser = {
    id: string;
    staff_id: string | null;
    name: string;
    email: string;
    staff_number: string | null;
    branch_name: string | null;
    position_name: string | null;
    is_active: boolean;
    is_director: boolean;
    last_login_at: string | null;
    roles: string[];
    permissions: string[];
};

export type StaffOption = {
    id: string;
    staff_number: string;
    name: string;
    email: string;
    branch_id: string;
    branch_name: string;
    position_name: string;
    user_id: string | null;
};

type UserFormData = Record<string, string | boolean | string[]> & {
    staff_id: string;
    password: string;
    password_confirmation: string;
    is_active: boolean;
    is_director: boolean;
    roles: string[];
    permissions: string[];
};

type Props = {
    user?: AccessUser;
    roles: string[];
    permissions: string[];
    staff: StaffOption[];
    onCancel?: () => void;
    onSuccess?: () => void;
};

export function UserForm({
    user,
    roles,
    permissions,
    staff,
    onCancel,
    onSuccess,
}: Props) {
    const initialBranchId =
        staff.find((staffMember) => staffMember.id === user?.staff_id)
            ?.branch_id ?? 'all';
    const [branchId, setBranchId] = useState(initialBranchId);
    const form = useForm<UserFormData>({
        staff_id: user?.staff_id ?? '',
        password: '',
        password_confirmation: '',
        is_active: user?.is_active ?? true,
        is_director: user?.is_director ?? false,
        roles: user?.roles ?? [],
        permissions: user?.permissions ?? [],
    });
    const selectedStaff = staff.find(
        (staffMember) => staffMember.id === form.data.staff_id,
    );
    const branches = useMemo(
        () =>
            Array.from(
                new Map(
                    staff.map((staffMember) => [
                        staffMember.branch_id,
                        {
                            id: staffMember.branch_id,
                            name: staffMember.branch_name,
                        },
                    ]),
                ).values(),
            ),
        [staff],
    );
    const branchStaff = useMemo(
        () =>
            branchId === 'all'
                ? staff
                : staff.filter(
                      (staffMember) => staffMember.branch_id === branchId,
                  ),
        [branchId, staff],
    );
    const branchOptions = useMemo(
        () => [
            { value: 'all', label: 'All branches' },
            ...branches.map((branch) => ({
                value: branch.id,
                label: branch.name,
            })),
        ],
        [branches],
    );
    const staffOptions = useMemo(
        () =>
            branchStaff.map((staffMember) => {
                const isAssigned =
                    staffMember.user_id !== null &&
                    staffMember.user_id !== user?.id;

                return {
                    value: staffMember.id,
                    label: `${staffMember.staff_number} - ${staffMember.name}`,
                    description: `${staffMember.email} - ${staffMember.branch_name} - ${staffMember.position_name}${isAssigned ? ' - assigned' : ''}`,
                    disabled: isAssigned,
                };
            }),
        [branchStaff, user?.id],
    );

    function toggleRole(role: string, checked: boolean) {
        form.setData(
            'roles',
            checked
                ? [...form.data.roles, role]
                : form.data.roles.filter((currentRole) => currentRole !== role),
        );
    }

    function togglePermission(permission: string, checked: boolean) {
        form.setData(
            'permissions',
            checked
                ? [...form.data.permissions, permission]
                : form.data.permissions.filter(
                      (currentPermission) => currentPermission !== permission,
                  ),
        );
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (user) {
            form.put(`/access-control/users/${user.id}`, {
                onSuccess,
            });

            return;
        }

        form.post('/access-control/users', {
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
                    value={branchId}
                    onValueChange={(value) => {
                        setBranchId(value);
                        form.setData('staff_id', '');
                    }}
                    options={branchOptions}
                    placeholder="Select branch"
                    searchPlaceholder="Search branches..."
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="staff_id">Staff member</Label>
                <SearchableSelect
                    value={form.data.staff_id}
                    onValueChange={(value) => form.setData('staff_id', value)}
                    options={staffOptions}
                    placeholder="Select staff"
                    searchPlaceholder="Search staff..."
                />
                <InputError message={form.errors.staff_id} />
                {selectedStaff && (
                    <div className="rounded-md border bg-muted/30 p-3 text-sm">
                        <div className="font-medium">{selectedStaff.email}</div>
                        <div className="mt-1 text-muted-foreground">{`${selectedStaff.branch_name} - ${selectedStaff.position_name}`}</div>
                        <div className="hidden">
                            {selectedStaff.branch_name} ·{' '}
                            {selectedStaff.position_name}
                        </div>
                    </div>
                )}
            </div>

            <div className="grid gap-2">
                <Label htmlFor="password">
                    {user ? 'New password' : 'Password'}
                </Label>
                <PasswordInput
                    id="password"
                    value={form.data.password}
                    onChange={(event) =>
                        form.setData('password', event.target.value)
                    }
                    placeholder={
                        user
                            ? 'Leave blank to keep current password'
                            : 'Password'
                    }
                />
                <InputError message={form.errors.password} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    value={form.data.password_confirmation}
                    onChange={(event) =>
                        form.setData(
                            'password_confirmation',
                            event.target.value,
                        )
                    }
                    placeholder="Confirm password"
                />
                <InputError message={form.errors.password_confirmation} />
            </div>

            <div className="grid gap-3">
                <Label>Roles</Label>
                <div className="grid gap-2 rounded-md border p-3">
                    {roles.map((role) => (
                        <label
                            key={role}
                            className="flex items-center gap-3 text-sm"
                        >
                            <Checkbox
                                checked={form.data.roles.includes(role)}
                                onCheckedChange={(checked) =>
                                    toggleRole(role, checked === true)
                                }
                            />
                            {role}
                        </label>
                    ))}
                    {roles.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No roles are available yet.
                        </p>
                    )}
                </div>
                <InputError message={form.errors.roles} />
            </div>

            <div className="grid gap-3">
                <Label>Direct permissions</Label>
                <div className="grid max-h-48 gap-2 overflow-y-auto rounded-md border p-3">
                    {permissions.map((permission) => (
                        <label
                            key={permission}
                            className="flex items-center gap-3 text-sm"
                        >
                            <Checkbox
                                checked={form.data.permissions.includes(
                                    permission,
                                )}
                                onCheckedChange={(checked) =>
                                    togglePermission(
                                        permission,
                                        checked === true,
                                    )
                                }
                            />
                            {permission}
                        </label>
                    ))}
                </div>
                <InputError message={form.errors.permissions} />
            </div>

            <div className="grid gap-2">
                <label className="flex items-center gap-3 text-sm">
                    <Checkbox
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked === true)
                        }
                    />
                    Active
                </label>
                <label className="flex items-center gap-3 text-sm">
                    <Checkbox
                        checked={form.data.is_director}
                        onCheckedChange={(checked) =>
                            form.setData('is_director', checked === true)
                        }
                    />
                    Director
                </label>
            </div>

            <div className="flex justify-end gap-3">
                <Button type="button" variant="outline" onClick={onCancel}>
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Save user
                </Button>
            </div>
        </form>
    );
}
