import { Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export type LinkedDocumentRow = {
    id: string;
    title: string;
    reference: string | null;
    type_name: string;
    confidentiality: string;
    status: string;
    expires_on: string | null;
    is_expired: boolean;
    current_version: {
        id: string;
        version_number: number;
        original_name: string;
    } | null;
};

export function DocumentEvidenceTable({
    documents,
    emptyText,
    title = 'Linked documents',
    description,
}: {
    documents: LinkedDocumentRow[];
    emptyText: string;
    title?: string;
    description?: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description && (
                    <CardDescription>{description}</CardDescription>
                )}
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-3 pr-4 font-medium">
                                    Document
                                </th>
                                <th className="py-3 pr-4 font-medium">Type</th>
                                <th className="py-3 pr-4 font-medium">
                                    Expiry
                                </th>
                                <th className="py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {documents.map((document) => (
                                <tr
                                    key={document.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="py-3 pr-4">
                                        <Link
                                            href={`/documents/${document.id}`}
                                            className="font-medium hover:underline"
                                        >
                                            {document.title}
                                        </Link>
                                        <div className="text-muted-foreground">
                                            {document.reference ??
                                                document.current_version
                                                    ?.original_name ??
                                                'No reference'}
                                        </div>
                                    </td>
                                    <td className="py-3 pr-4">
                                        <Badge variant="secondary">
                                            {document.type_name}
                                        </Badge>
                                        <div className="mt-1 text-muted-foreground">
                                            {document.confidentiality}
                                        </div>
                                    </td>
                                    <td className="py-3 pr-4">
                                        <Badge
                                            variant={
                                                document.is_expired
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                        >
                                            {document.expires_on ?? 'No expiry'}
                                        </Badge>
                                    </td>
                                    <td className="py-3">
                                        <div className="flex justify-end gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={`/documents/${document.id}`}
                                                >
                                                    Open
                                                </Link>
                                            </Button>
                                            {document.current_version && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <a
                                                        href={`/documents/${document.id}/versions/${document.current_version.id}/download`}
                                                    >
                                                        <Download />
                                                    </a>
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {documents.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        {emptyText}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}
