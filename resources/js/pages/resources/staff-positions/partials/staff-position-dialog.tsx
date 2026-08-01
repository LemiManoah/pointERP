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
import { StaffPositionForm, type StaffPosition } from './staff-position-form';

type Props = {
    position?: StaffPosition;
};

export function StaffPositionDialog({ position }: Props) {
    const [open, setOpen] = useState(false);
    const isEditing = Boolean(position);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={isEditing ? 'outline' : 'default'}
                    size={isEditing ? 'sm' : 'default'}
                >
                    {isEditing ? <Pencil /> : <Plus />}
                    {isEditing ? 'Edit' : 'New position'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? `Edit ${position?.name}` : 'New position'}
                    </DialogTitle>
                    <DialogDescription>
                        Staff positions classify staff before ERP users are
                        created.
                    </DialogDescription>
                </DialogHeader>
                <StaffPositionForm
                    position={position}
                    onCancel={() => setOpen(false)}
                    onSuccess={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
