<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DocumentType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

final class IronPointProductionDocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'IRONPOINT')->firstOrFail();
        $actor = User::query()->where('tenant_id', $tenant->id)->where('email', 'lemi.manoah@gmail.com')->firstOrFail();

        foreach ([
            ['CONTRACT', 'Contract', false, true],
            ['CONTRACT_ADDENDUM', 'Contract addendum', false, true],
            ['DRAWING', 'Drawing', false, false],
            ['REVISED_DRAWING', 'Revised drawing', false, false],
            ['METHOD_STATEMENT', 'Method statement', false, false],
            ['PERMIT', 'Permit', true, false],
            ['TEST_RESULT', 'Test result', false, false],
            ['INSPECTION_RECORD', 'Inspection record', false, false],
            ['SITE_INSTRUCTION', 'Site instruction', false, false],
            ['RFI', 'Request for information', false, false],
            ['RFA', 'Request for approval', false, false],
            ['DSR_EVIDENCE', 'DSR evidence', false, false],
            ['PHOTO', 'Photo', false, false],
            ['SKETCH', 'Sketch', false, false],
            ['HSE_RECORD', 'HSE record', false, false],
            ['ENVIRONMENT_RECORD', 'Environment record', false, false],
            ['SOCIAL_RECORD', 'Social record', false, false],
            ['IPC_SUPPORT', 'IPC support', false, true],
            ['EXPENSE_RECEIPT', 'Expense receipt', false, true],
            ['CORRESPONDENCE', 'Correspondence', false, false],
        ] as [$code, $name, $requiresExpiry, $isConfidential]) {
            $type = DocumentType::query()->withTrashed()->firstOrNew([
                'tenant_id' => null,
                'code' => $code,
            ]);
            $type->fill([
                'name' => $name,
                'description' => $name.' records for project document control.',
                'requires_expiry_date' => $requiresExpiry,
                'is_confidential' => $isConfidential,
                'is_system' => true,
                'is_active' => true,
                'created_by' => $type->exists ? $type->created_by : $actor->id,
                'updated_by' => $actor->id,
            ]);
            $type->forceFill(['deleted_at' => null]);
            $type->save();
        }
    }
}
