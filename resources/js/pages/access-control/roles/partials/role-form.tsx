import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export type AccessRole = {
    id: string;
    name: string;
    guard_name: string;
    users_count: number;
    permissions: string[];
};

type RoleFormData = Record<string, string | string[]> & {
    name: string;
    permissions: string[];
};

type Props = {
    role?: AccessRole;
    permissions: string[];
    onCancel?: () => void;
    onSuccess?: () => void;
};

export function RoleForm({ role, permissions, onCancel, onSuccess }: Props) {
    const form = useForm<RoleFormData>({
        name: role?.name ?? '',
        permissions: role?.permissions ?? [],
    });

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

    const groupedPermissions = permissions.reduce<Record<string, string[]>>(
        (groups, permission) => {
            const [module] = permission.split('.');

            return {
                ...groups,
                [module]: [...(groups[module] ?? []), permission],
            };
        },
        {},
    );

    function togglePermissionGroup(
        groupPermissions: string[],
        checked: boolean,
    ) {
        form.setData(
            'permissions',
            checked
                ? Array.from(
                      new Set([...form.data.permissions, ...groupPermissions]),
                  )
                : form.data.permissions.filter(
                      (permission) => !groupPermissions.includes(permission),
                  ),
        );
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (role) {
            form.put(`/access-control/roles/${role.id}`, {
                onSuccess,
            });

            return;
        }

        form.post('/access-control/roles', {
            onSuccess: () => {
                form.reset();
                onSuccess?.();
            },
        });
    }

    return (
        <form onSubmit={submit} className="grid gap-5">
            <div className="grid gap-2">
                <Label htmlFor="name">Role name</Label>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    placeholder="Project Manager"
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-3">
                <Label>Permissions</Label>
                <div className="grid max-h-[45dvh] gap-4 overflow-y-auto rounded-md border p-4 md:grid-cols-2 xl:grid-cols-3">
                    {Object.entries(groupedPermissions).map(
                        ([module, modulePermissions]) => {
                            const allSelected = modulePermissions.every(
                                (permission) =>
                                    form.data.permissions.includes(permission),
                            );

                            return (
                                <section
                                    key={module}
                                    className="grid gap-3 rounded-md border bg-muted/20 p-3"
                                >
                                    <label className="flex items-center gap-3 text-sm font-medium capitalize">
                                        <Checkbox
                                            checked={allSelected}
                                            onCheckedChange={(checked) =>
                                                togglePermissionGroup(
                                                    modulePermissions,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        {module}
                                    </label>
                                    <div className="grid gap-2">
                                        {modulePermissions.map((permission) => (
                                            <label
                                                key={permission}
                                                className="flex items-center gap-3 text-sm"
                                            >
                                                <Checkbox
                                                    checked={form.data.permissions.includes(
                                                        permission,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
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
                                </section>
                            );
                        },
                    )}
                </div>
                <InputError message={form.errors.permissions} />
            </div>

            <div className="flex justify-end gap-3">
                <Button type="button" variant="outline" onClick={onCancel}>
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Save role
                </Button>
            </div>
        </form>
    );
}
