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
    trades: Option[];
    employmentTypes: Option[];
};

export function StaffDialog({
    staff,
    branches,
    positions,
    trades,
    employmentTypes,
}: Props) {
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
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit ' + staff?.name : 'New staff'}
                    </DialogTitle>
                    <DialogDescription>
                        Position describes the job title. Trade describes the
                        practical work capability used on site.
                    </DialogDescription>
                </DialogHeader>
                <StaffForm
                    staff={staff}
                    branches={branches}
                    positions={positions}
                    trades={trades}
                    employmentTypes={employmentTypes}
                    onCancel={() => setOpen(false)}
                    onSuccess={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
