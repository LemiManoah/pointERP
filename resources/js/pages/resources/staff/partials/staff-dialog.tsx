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
import { StaffForm, type Option, type Staff } from './staff-form';

type Props = {
    staff?: Staff;
    branches: Option[];
    positions: Option[];
};

export function StaffDialog({ staff, branches, positions }: Props) {
    const [open, setOpen] = useState(false);
    const isEditing = Boolean(staff);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={isEditing ? 'outline' : 'default'}
                    size={isEditing ? 'sm' : 'default'}
                >
                    {isEditing ? <Pencil /> : <Plus />}
                    {isEditing ? 'Edit' : 'New staff'}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? `Edit ${staff?.name}` : 'New staff'}
                    </DialogTitle>
                    <DialogDescription>
                        Staff records are the source for creating ERP users.
                    </DialogDescription>
                </DialogHeader>
                <StaffForm
                    staff={staff}
                    branches={branches}
                    positions={positions}
                    onCancel={() => setOpen(false)}
                    onSuccess={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
