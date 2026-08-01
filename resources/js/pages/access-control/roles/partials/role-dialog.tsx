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
import { RoleForm, type AccessRole } from './role-form';

type Props = {
    role?: AccessRole;
    permissions: string[];
};

export function RoleDialog({ role, permissions }: Props) {
    const [open, setOpen] = useState(false);
    const isEditing = Boolean(role);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={isEditing ? 'outline' : 'default'}
                    size={isEditing ? 'sm' : 'default'}
                >
                    {isEditing ? <Pencil /> : <Plus />}
                    {isEditing ? 'Edit' : 'New role'}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-5xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? `Edit ${role?.name}` : 'New role'}
                    </DialogTitle>
                    <DialogDescription>
                        Roles group permissions and can be assigned to users.
                    </DialogDescription>
                </DialogHeader>
                <RoleForm
                    role={role}
                    permissions={permissions}
                    onCancel={() => setOpen(false)}
                    onSuccess={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
