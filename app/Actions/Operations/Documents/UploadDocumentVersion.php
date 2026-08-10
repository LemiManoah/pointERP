<?php

declare(strict_types=1);

namespace App\Actions\Operations\Documents;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final readonly class UploadDocumentVersion
{
    public function __construct(private AuditLogger $auditLogger)
    {
        //
    }

    /**
     * @param  array{file: UploadedFile, notes?: string|null}  $data
     *
     * @throws Throwable
     */
    public function handle(Document $document, array $data, User $actor): DocumentVersion
    {
        $file = $data['file'];
        $nextVersion = ((int) $document->versions()->max('version_number')) + 1;
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');
        $path = sprintf('documents/%s/%s/v%d/%s', $document->tenant_id, $document->id, $nextVersion, $filename);
        $disk = 'local';

        $storedPath = $file->storeAs(dirname($path), basename($path), $disk);

        try {
            return DB::transaction(function () use ($actor, $data, $disk, $document, $file, $nextVersion, $storedPath): DocumentVersion {
                $version = DocumentVersion::query()->create([
                    'tenant_id' => $document->tenant_id,
                    'document_id' => $document->id,
                    'version_number' => $nextVersion,
                    'disk' => $disk,
                    'path' => $storedPath,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
                    'size_bytes' => $file->getSize() ?: 0,
                    'checksum' => $file->getRealPath() !== false ? hash_file('sha256', $file->getRealPath()) : null,
                    'notes' => $data['notes'] ?? null,
                    'uploaded_by' => $actor->id,
                    'uploaded_at' => now(),
                ]);

                $oldValues = ['current_version_id' => $document->current_version_id];
                $document->forceFill([
                    'current_version_id' => $version->id,
                    'updated_by' => $actor->id,
                ])->save();

                $this->auditLogger->record('operations.document.version_uploaded', $document, $actor, $oldValues, [
                    'current_version_id' => $version->id,
                    'version_number' => $nextVersion,
                    'original_name' => $version->original_name,
                ]);

                return $version;
            });
        } catch (Throwable $throwable) {
            Storage::disk($disk)->delete($storedPath);

            throw $throwable;
        }
    }
}
