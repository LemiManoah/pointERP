import { useForm, usePage } from '@inertiajs/react';
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
    branch_ids: string[];
    branches: BranchOption[];
    default_branch_id: string | null;
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

export type BranchOption = {
    id: string;
    name: string;
    code: string;
};

type UserFormData = Record<string, string | boolean | string[]> & {
    staff_id: string;
    password: string;
    password_confirmation: string;
    is_active: boolean;
    is_director: boolean;
    roles: string[];
    permissions: string[];
    branch_ids: string[];
    default_branch_id: string;
};

type Props = {
    user?: AccessUser;
    roles: string[];
    permissions: string[];
    staff: StaffOption[];
    branches: BranchOption[];
    onCancel?: () => void;
    onSuccess?: () => void;
};

export function UserForm({
    user,
    roles,
    permissions,
    staff,
    branches: branchOptionsSource,
    onCancel,
    onSuccess,
}: Props) {
    const currentUserId = usePage().props.auth.user.id;
    const isCurrentUser = user?.id === currentUserId;
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
        branch_ids: user?.branch_ids ?? [],
        default_branch_id: user?.default_branch_id ?? '',
    });
    const selectedStaff = staff.find(
        (staffMember) => staffMember.id === form.data.staff_id,
    );
    const staffBranches = useMemo(
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
            ...staffBranches.map((branch) => ({
                value: branch.id,
                label: branch.name,
            })),
        ],
        [staffBranches],
    );
    const defaultBranchOptions = useMemo(
        () =>
            branchOptionsSource
                .filter((branch) => form.data.branch_ids.includes(branch.id))
                .map((branch) => ({
                    value: branch.id,
                    label: branch.name,
                    description: branch.code,
                })),
        [branchOptionsSource, form.data.branch_ids],
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

    function toggleBranch(branchId: string, checked: boolean) {
        const nextBranchIds = checked
            ? [...form.data.branch_ids, branchId]
            : form.data.branch_ids.filter(
                  (currentBranchId) => currentBranchId !== branchId,
              );

        form.setData({
            ...form.data,
            branch_ids: nextBranchIds,
            default_branch_id: nextBranchIds.includes(
                form.data.default_branch_id,
            )
                ? form.data.default_branch_id
                : (nextBranchIds[0] ?? ''),
        });
    }

    function setStaffId(staffId: string) {
        const nextStaff = staff.find(
            (staffMember) => staffMember.id === staffId,
        );

        const branchIds =
            nextStaff && !form.data.branch_ids.includes(nextStaff.branch_id)
                ? [...form.data.branch_ids, nextStaff.branch_id]
                : form.data.branch_ids;

        form.setData({
            ...form.data,
            staff_id: staffId,
            branch_ids: branchIds,
            default_branch_id:
                form.data.default_branch_id || nextStaff?.branch_id || '',
        });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (user) {
            form.put(`/users/${user.id}`, {
                onSuccess,
            });

            return;
        }

        form.post('/users', {
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
                    onValueChange={setStaffId}
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
                            {selectedStaff.branch_name} -
                            {selectedStaff.position_name}
                        </div>
                    </div>
                )}
            </div>

            <div className="grid gap-3">
                <Label>Branch access</Label>
                <div className="grid max-h-48 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2">
                    {branchOptionsSource.map((branch) => (
                        <label
                            key={branch.id}
                            className="flex items-center gap-3 text-sm"
                        >
                            <Checkbox
                                checked={form.data.branch_ids.includes(
                                    branch.id,
                                )}
                                onCheckedChange={(checked) =>
                                    toggleBranch(branch.id, checked === true)
                                }
                            />
                            <span>
                                {branch.name}
                                <span className="ml-1 text-muted-foreground">
                                    {branch.code}
                                </span>
                            </span>
                        </label>
                    ))}
                </div>
                <InputError message={form.errors.branch_ids} />
            </div>

            <div className="grid gap-2">
                <Label>Default branch</Label>
                <SearchableSelect
                    value={form.data.default_branch_id}
                    onValueChange={(value) =>
                        form.setData('default_branch_id', value)
                    }
                    options={defaultBranchOptions}
                    placeholder="Select default branch"
                    searchPlaceholder="Search selected branches..."
                />
                <InputError message={form.errors.default_branch_id} />
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
                        disabled={isCurrentUser}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked === true)
                        }
                    />
                    {isCurrentUser ? 'Active current user' : 'Active'}
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
