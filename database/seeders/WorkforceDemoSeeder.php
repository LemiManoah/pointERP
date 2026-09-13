<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StaffDeploymentStatus;
use App\Enums\StaffEmploymentType;
use App\Enums\WorkforceTradeCategory;
use App\Models\Project;
use App\Models\Site;
use App\Models\Staff;
use App\Models\StaffDeployment;
use App\Models\User;
use App\Models\WorkforceTrade;
use Illuminate\Database\Seeder;

final class WorkforceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'lemi@gmail.com')->firstOrFail();
        $project = Project::query()->where('reference', 'NAK-QRY')->firstOrFail();
        $pit = Site::query()->where('project_id', $project->id)->where('reference', 'QUARRY-PIT')->firstOrFail();
        $crusherYard = Site::query()->where('project_id', $project->id)->where('reference', 'CRUSHER-YARD')->firstOrFail();

        /** @var array<string, WorkforceTrade> $trades */
        $trades = [];
        /** @var list<array{code: string, name: string, category: WorkforceTradeCategory}> $tradeRows */
        $tradeRows = [
            ['code' => 'SITE-ENGINEER', 'name' => 'Site engineering', 'category' => WorkforceTradeCategory::Skilled],
            ['code' => 'PROJECT-SUPERVISION', 'name' => 'Project supervision', 'category' => WorkforceTradeCategory::Specialist],
            ['code' => 'QUARRY-SUPERVISOR', 'name' => 'Quarry supervision', 'category' => WorkforceTradeCategory::Specialist],
            ['code' => 'EXCAVATOR-OPERATOR', 'name' => 'Excavator operator', 'category' => WorkforceTradeCategory::Skilled],
            ['code' => 'CRUSHER-OPERATOR', 'name' => 'Crusher operator', 'category' => WorkforceTradeCategory::Skilled],
            ['code' => 'MECHANIC', 'name' => 'Plant mechanic', 'category' => WorkforceTradeCategory::Skilled],
            ['code' => 'ELECTRICIAN', 'name' => 'Plant electrician', 'category' => WorkforceTradeCategory::Skilled],
            ['code' => 'GENERAL-LABOURER', 'name' => 'General labourer', 'category' => WorkforceTradeCategory::Unskilled],
        ];

        foreach ($tradeRows as $row) {
            $trades[$row['code']] = WorkforceTrade::query()->updateOrCreate(
                ['tenant_id' => $project->tenant_id, 'code' => $row['code']],
                [
                    'name' => $row['name'],
                    'category' => $row['category'],
                    'is_active' => true,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ],
            );
        }

        /** @var array<string, array{trade: string, employment_type: StaffEmploymentType, site: Site|null}> $assignments */
        $assignments = [
            'luate@gmail.com' => ['trade' => 'SITE-ENGINEER', 'employment_type' => StaffEmploymentType::Permanent, 'site' => $pit],
            'latif@gmail.com' => ['trade' => 'PROJECT-SUPERVISION', 'employment_type' => StaffEmploymentType::Permanent, 'site' => null],
            'rober@gmail.com' => ['trade' => 'QUARRY-SUPERVISOR', 'employment_type' => StaffEmploymentType::FixedTerm, 'site' => $crusherYard],
        ];

        foreach ($assignments as $email => $assignment) {
            $staff = Staff::query()->where('email', $email)->firstOrFail();
            $trade = $trades[$assignment['trade']];

            $staff->update([
                'employment_type' => $assignment['employment_type'],
                'primary_trade_id' => $trade->id,
            ]);

            StaffDeployment::query()->updateOrCreate(
                [
                    'tenant_id' => $project->tenant_id,
                    'staff_id' => $staff->id,
                    'status' => StaffDeploymentStatus::Active->value,
                ],
                [
                    'branch_id' => $project->branch_id,
                    'project_id' => $project->id,
                    'site_id' => $assignment['site']?->id,
                    'workforce_trade_id' => $trade->id,
                    'starts_on' => now()->startOfMonth()->toDateString(),
                    'ends_on' => null,
                    'notes' => 'Current quarry demo deployment.',
                    'assigned_by' => $actor->id,
                    'ended_by' => null,
                ],
            );
        }
    }
}
