import { useForm } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

type FormData = Record<string, string | File | null> & {
    file: File | null;
    notes: string;
};

export function VersionDialog({ documentId }: { documentId: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm<FormData>({
        file: null,
        notes: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post(`/documents/${documentId}/versions`, {
            forceFormData: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Upload />
                    Upload version
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Upload new version</DialogTitle>
                    <DialogDescription>
                        The existing version stays in history.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="version-file">File</Label>
                        <Input
                            id="version-file"
                            type="file"
                            onChange={(event) =>
                                form.setData(
                                    'file',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={form.errors.file} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notes</Label>
                        <Textarea
                            id="notes"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                        />
                    </div>
                    <div className="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Upload
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
