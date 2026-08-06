import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    UserForm,
    type AccessUser,
    type BranchOption,
    type StaffOption,
} from './user-form';

type Props = {
    user?: AccessUser;
    roles: string[];
    permissions: string[];
    staff: StaffOption[];
    branches: BranchOption[];
};

export function UserDialog({
    user,
    roles,
    permissions,
    staff,
    branches,
}: Props) {
    const [open, setOpen] = useState(false);
    const isEditing = Boolean(user);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={isEditing ? 'outline' : 'default'}
                    size={isEditing ? 'sm' : 'default'}
                >
                    {isEditing ? <Pencil /> : <Plus />}
                    {isEditing ? 'Edit' : 'New user'}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? `Edit ${user?.name}` : 'New user'}
                    </DialogTitle>
                    <DialogDescription>
                        Admin-created users are scoped to the current ERP
                        tenant.
                    </DialogDescription>
                </DialogHeader>
                <UserForm
                    user={user}
                    roles={roles}
                    permissions={permissions}
                    staff={staff}
                    branches={branches}
                    onCancel={() => setOpen(false)}
                    onSuccess={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
