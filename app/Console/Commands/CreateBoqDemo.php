<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Operations\DailySiteReports\ApproveDailySiteReport;
use App\Actions\Operations\DailySiteReports\SaveDailySiteReport;
use App\Actions\Operations\DailySiteReports\SubmitDailySiteReport;
use App\Actions\Operations\Estimates\ApproveProjectEstimate;
use App\Actions\Operations\Estimates\SaveProjectEstimate;
use App\Actions\Operations\ProjectActivities\SaveProjectActivity;
use App\Models\Branch;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Site;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

#[Description('Create a local Centre of Excellence walkthrough with fictional site output')]
#[Signature('boq:demo {--user= : Existing local user email} {--branch= : Existing branch UUID}')]
final class CreateBoqDemo extends Command
{
    public function handle(TenantContext $context, SaveProjectEstimate $saveEstimate, ApproveProjectEstimate $approveEstimate,
        SaveProjectActivity $saveActivity, SaveDailySiteReport $saveReport, SubmitDailySiteReport $submitReport, ApproveDailySiteReport $approveReport): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This demonstration is restricted to local and testing environments.');

            return self::FAILURE;
        }

        $actor = User::query()->where('email', $this->option('user'))->where('is_active', true)->firstOrFail();
        $context->set($actor->tenant);
        $branch = Branch::query()->where('tenant_id', $actor->tenant_id)->whereKey($this->option('branch'))->firstOrFail();
        $existing = Project::query()->where('reference', 'BOQ-DEMO-COE')->first();
        if ($existing) {
            $this->info('Demo already exists: '.$existing->id);

            return self::SUCCESS;
        }

        Notification::fake();
        config(['operations.notifications.email_enabled' => false]);
        $project = DB::transaction(function () use ($actor, $branch, $saveEstimate, $approveEstimate, $saveActivity, $saveReport, $submitReport, $approveReport): Project {
            $units = UnitOfMeasure::query()->whereIn('code', ['M3', 'PIECE'])
                ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $actor->tenant_id))->get()->keyBy('code');
            $project = Project::query()->create([
                'tenant_id' => $actor->tenant_id, 'branch_id' => $branch->id,
                'reference' => 'BOQ-DEMO-COE', 'name' => 'DEMO - Centre of Excellence BoQ',
                'description' => 'Local walkthrough only. Excavation B uses the client workbook. All site measurements and other scope items are fictional examples, not a live contract.',
                'manager_id' => $actor->id, 'base_currency_code' => 'UGX', 'starts_on' => today()->subDays(7),
                'status' => 'active', 'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            $site = Site::query()->create([
                'tenant_id' => $actor->tenant_id, 'branch_id' => $branch->id, 'project_id' => $project->id,
                'reference' => 'DEMO-COE-GF', 'name' => 'Demo ground floor', 'manager_id' => $actor->id,
                'reporting_deadline' => '17:00', 'status' => 'active', 'created_by' => $actor->id,
            ]);
            $lines = [[
                'work_item_key' => (string) Str::uuid(), 'bill' => 'Bill 2 - Building civil works',
                'section' => 'Ground floor', 'element' => 'Substructures', 'boq_reference' => 'B',
                'name' => 'Mass excavation not exceeding 1.5 m deep',
                'description' => 'Client workbook example: mass excavation not exceeding 1.5 m deep. Original quantity 1,648 CM, explicitly mapped to cubic metres for this demonstration.',
                'unit_of_measure_id' => $units->get('M3')->id, 'planned_quantity' => '1648', 'selling_rate' => null,
                'source_document' => 'Centre of Excellence BOQs - Unpriced BOQs.xlsx',
                'source_sheet' => 'Bill No. 2.1 GF', 'source_row' => 10, 'item_type' => 'measured',
            ], [
                'work_item_key' => (string) Str::uuid(), 'bill' => 'Demo examples', 'boq_reference' => 'DEMO-02',
                'name' => 'Unpriced drainage excavation - fictional example', 'description' => 'Demonstrates a blank rate. This is not an extracted client item.',
                'unit_of_measure_id' => $units->get('M3')->id, 'planned_quantity' => '80', 'selling_rate' => null, 'item_type' => 'measured',
            ], [
                'work_item_key' => (string) Str::uuid(), 'bill' => 'Demo examples', 'boq_reference' => 'DEMO-PS',
                'name' => 'Provisional allowance - fictional example', 'description' => 'An allowance with no physical completion quantity. Not extracted from the client workbook.',
                'unit_of_measure_id' => $units->get('PIECE')->id, 'planned_quantity' => '1', 'selling_rate' => null, 'item_type' => 'provisional_sum',
            ]];
            $first = $saveEstimate->handle($project, ['title' => 'Demo: unpriced scope', 'currency_code' => 'UGX', 'lines' => $lines], $actor);
            $approveEstimate->handle($first, $actor);
            $lines[0]['selling_rate'] = '25000';
            $lines[0]['source_document'] = 'Centre of Excellence BOQs - Point Investment Company ltd.xlsx';
            $priced = $saveEstimate->handle($project, ['title' => 'Demo: partial pricing confirmed', 'currency_code' => 'UGX', 'lines' => $lines], $actor);
            $approveEstimate->handle($priced, $actor);
            $excavation = ProjectActivity::query()->where('project_id', $project->id)->where('estimate_work_item_key', $lines[0]['work_item_key'])->firstOrFail();
            $north = $saveActivity->handle(['project_id' => $project->id, 'boq_item_id' => $excavation->boq_item_id,
                'name' => 'Excavation - north wing', 'unit' => $excavation->unit, 'progress_method' => 'measured', 'status' => 'active'], $actor);
            $support = $saveActivity->handle(['project_id' => $project->id, 'boq_item_id' => $excavation->boq_item_id,
                'name' => 'Setting-out and level checks', 'unit' => 'day', 'progress_method' => 'supporting', 'status' => 'active'], $actor);
            foreach ([[$excavation, '100'], [$north, '150'], [$north, '40']] as $index => [$activity, $quantity]) {
                $work = [['project_activity_id' => $activity->id, 'description' => 'DEMO: '.($index === 2 ? 'Pending excavation measurement' : 'Approved excavation measurement'), 'quantity' => $quantity]];
                if ($index === 0) {
                    $work[] = ['project_activity_id' => $support->id, 'description' => 'DEMO: setting-out support; no excavation output', 'quantity' => '2'];
                }

                $report = $saveReport->handle(['site_id' => $site->id, 'report_date' => today()->subDays(3 - $index)->toDateString(),
                    'work_summary' => 'FICTIONAL DEMONSTRATION - do not use for contract payment.', 'work_lines' => $work], $actor);
                if ($index < 2) {
                    $submitReport->handle($report, $actor, 'Local demonstration only; these are fictional measurements.');
                    $approveReport->handle($report, $actor);
                }
            }

            $lines[0]['planned_quantity'] = '1800';
            $saveEstimate->handle($project, ['title' => 'Demo: proposed quantity revision (not approved)', 'currency_code' => 'UGX', 'lines' => $lines], $actor);

            return $project;
        });
        $this->info('Demo project created: '.$project->id);
        $this->line('Open Projects > DEMO - Centre of Excellence BoQ > BoQ and progress.');

        return self::SUCCESS;
    }
}
