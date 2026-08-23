import { Link } from '@inertiajs/react';
import { Download, ExternalLink } from 'lucide-react';
import type { ReactNode } from 'react';
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
    external_url?: string | null;
    document_number?: string | null;
    revision?: string | null;
    discipline?: string | null;
    issuer?: string | null;
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
    actions,
}: {
    documents: LinkedDocumentRow[];
    emptyText: string;
    title?: string;
    description?: string;
    actions?: ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle>{title}</CardTitle>
                    {description && (
                        <CardDescription>{description}</CardDescription>
                    )}
                </div>
                {actions}
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
                                                document.document_number ??
                                                document.current_version
                                                    ?.original_name ??
                                                'No reference'}
                                            {document.revision &&
                                                ` · Rev ${document.revision}`}
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
                                            {document.external_url && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <a
                                                        href={
                                                            document.external_url
                                                        }
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        title="Open Google file"
                                                    >
                                                        <ExternalLink />
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
