<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EstimateResourceType;
use App\Models\Tenant;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WorkItemResourceTemplate;
use App\Models\WorkItemTemplate;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;

final class WorkItemTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::query()->get();

        foreach ($tenants as $tenant) {
            resolve(TenantContext::class)->set($tenant);
            $user = User::query()->where('tenant_id', $tenant->id)->first();
            $actorId = $user?->id;

            $m3 = UnitOfMeasure::query()
                ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
                ->where('symbol', 'm³')
                ->orWhere('symbol', 'm3')
                ->firstOrFail();

            $m2 = UnitOfMeasure::query()
                ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
                ->where('symbol', 'm²')
                ->orWhere('symbol', 'm2')
                ->firstOrFail();

            $m = UnitOfMeasure::query()
                ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
                ->where('symbol', 'm')
                ->firstOrFail();

            $bag = UnitOfMeasure::query()
                ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
                ->where(fn ($q) => $q->where('name', 'Bag')->orWhere('symbol', 'bag'))
                ->first();

            $hr = UnitOfMeasure::query()
                ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
                ->where(fn ($q) => $q->where('name', 'Hour')->orWhere('symbol', 'hr'))
                ->first();

            $templates = [
                [
                    'code' => 'CONC-025',
                    'category' => 'Concrete Works',
                    'name' => 'Grade 25 Reinforced Concrete in Slabs & Beams',
                    'unit_of_measure_id' => $m3->id,
                    'default_selling_rate' => '450000.0000',
                    'specifications' => 'Mix ratio approx 1:1.5:3, 20mm crushed granite aggregate, 42.5N cement.',
                    'resources' => [
                        [
                            'resource_type' => EstimateResourceType::Material,
                            'name' => 'Cement CEM II 42.5N',
                            'unit_of_measure_id' => $bag?->id,
                            'quantity_per_work_unit' => '7.200000',
                            'unit_cost' => '38000.0000',
                            'notes' => '50kg bag',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Material,
                            'name' => 'Washed River Sand',
                            'unit_of_measure_id' => $m3->id,
                            'quantity_per_work_unit' => '0.450000',
                            'unit_cost' => '65000.0000',
                            'notes' => 'Clean natural river sand',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Material,
                            'name' => 'Crushed Stone Aggregate 20mm',
                            'unit_of_measure_id' => $m3->id,
                            'quantity_per_work_unit' => '0.850000',
                            'unit_cost' => '70000.0000',
                            'notes' => 'Clean angular hard stone',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Concrete Mixer 400L',
                            'unit_of_measure_id' => $hr?->id,
                            'quantity_per_work_unit' => '0.350000',
                            'unit_cost' => '40000.0000',
                            'notes' => 'Including fuel and operator',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Poker Vibrator',
                            'unit_of_measure_id' => $hr?->id,
                            'quantity_per_work_unit' => '0.350000',
                            'unit_cost' => '15000.0000',
                            'notes' => 'Petrol driven needle vibrator',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Labour,
                            'name' => 'Concreting Mason',
                            'unit_of_measure_id' => $hr?->id,
                            'quantity_per_work_unit' => '0.500000',
                            'unit_cost' => '10000.0000',
                            'notes' => 'Skilled tradesman',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Labour,
                            'name' => 'Concreting Helper Gang',
                            'unit_of_measure_id' => $hr?->id,
                            'quantity_per_work_unit' => '2.500000',
                            'unit_cost' => '4000.0000',
                            'notes' => 'Material handling & placing helpers',
                        ],
                    ],
                ],
                [
                    'code' => 'CONC-015',
                    'category' => 'Concrete Works',
                    'name' => 'Grade 15 Blinding Concrete 50mm',
                    'unit_of_measure_id' => $m3->id,
                    'default_selling_rate' => '320000.0000',
                    'specifications' => 'Mass concrete blinding 50mm thick under foundations and ground beams.',
                    'resources' => [
                        [
                            'resource_type' => EstimateResourceType::Material,
                            'name' => 'Cement CEM II 32.5R',
                            'unit_of_measure_id' => $bag?->id,
                            'quantity_per_work_unit' => '4.500000',
                            'unit_cost' => '34000.0000',
                            'notes' => '50kg bag',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Material,
                            'name' => 'River Sand',
                            'unit_of_measure_id' => $m3->id,
                            'quantity_per_work_unit' => '0.500000',
                            'unit_cost' => '65000.0000',
                            'notes' => null,
                        ],
                        [
                            'resource_type' => EstimateResourceType::Material,
                            'name' => 'Crushed Aggregate 20mm',
                            'unit_of_measure_id' => $m3->id,
                            'quantity_per_work_unit' => '0.900000',
                            'unit_cost' => '70000.0000',
                            'notes' => null,
                        ],
                        [
                            'resource_type' => EstimateResourceType::Labour,
                            'name' => 'Concreting Crew',
                            'unit_of_measure_id' => $hr?->id,
                            'quantity_per_work_unit' => '1.500000',
                            'unit_cost' => '10000.0000',
                            'notes' => 'Combined screeding & placing crew',
                        ],
                    ],
                ],
                [
                    'code' => 'EW-EXC-01',
                    'category' => 'Earthworks',
                    'name' => 'Bulk Excavation in Common Soil (Disposal to Spoil)',
                    'unit_of_measure_id' => $m3->id,
                    'default_selling_rate' => '18000.0000',
                    'specifications' => 'Excavation in ordinary soil including loading and dumping within 2km.',
                    'resources' => [
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Hydraulic Excavator 20T',
                            'unit_of_measure_id' => $hr?->id,
                            'quantity_per_work_unit' => '0.040000',
                            'unit_cost' => '220000.0000',
                            'notes' => 'Wet rate including operator & fuel',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Tipper Truck 15T',
                            'unit_of_measure_id' => $hr?->id,
                            'quantity_per_work_unit' => '0.120000',
                            'unit_cost' => '30000.0000',
                            'notes' => 'Haulage within 2km',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Labour,
                            'name' => 'Banksman / Spotter',
                            'unit_of_measure_id' => $hr?->id,
                            'quantity_per_work_unit' => '0.040000',
                            'unit_cost' => '5000.0000',
                            'notes' => 'Safety directing tippers',
                        ],
                    ],
                ],
                [
                    'code' => 'EW-SUB-01',
                    'category' => 'Earthworks',
                    'name' => 'Natural Gravel Subbase Spreading & Compaction',
                    'unit_of_measure_id' => $m3->id,
                    'default_selling_rate' => '35000.0000',
                    'specifications' => 'Approved laterite/murram gravel compacted to 95% MDD in 150mm layers.',
                    'resources' => [
                        [
                            'resource_type' => EstimateResourceType::Material,
                            'name' => 'Approved Natural Gravel',
                            'unit_of_measure_id' => $m3->id,
                            'quantity_per_work_unit' => '1.250000',
                            'unit_cost' => '18000.0000',
                            'notes' => 'Including compaction bulkage factor (25%)',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Motor Grader 140K',
                            'unit_of_measure_id' => $hr?->id,
                            'quantity_per_work_unit' => '0.030000',
                            'unit_cost' => '180000.0000',
                            'notes' => 'Spreading & camber formation',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Vibratory Steel Roller 10T',
                            'unit_of_measure_id' => $hr?->id,
                            'quantity_per_work_unit' => '0.025000',
                            'unit_cost' => '120000.0000',
                            'notes' => 'Compaction passes',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Water Bowser 10,000L',
                            'unit_of_measure_id' => $hr?->id,
                            'quantity_per_work_unit' => '0.020000',
                            'unit_cost' => '50000.0000',
                            'notes' => 'Optimum moisture watering',
                        ],
                    ],
                ],
                [
                    'code' => 'DRN-CUL-600',
                    'category' => 'Drainage',
                    'name' => '600mm Dia Precast Concrete Pipe Culvert Installation',
                    'unit_of_measure_id' => $m->id,
                    'default_selling_rate' => '280000.0000',
                    'specifications' => 'Bedding, laying and jointing 600mm Class C spun concrete pipes with 1:3 mortar.',
                    'resources' => [
                        [
                            'resource_type' => EstimateResourceType::Material,
                            'name' => '600mm Class C Precast Pipe',
                            'unit_of_measure_id' => $m->id,
                            'quantity_per_work_unit' => '1.000000',
                            'unit_cost' => '140000.0000',
                            'notes' => 'Spun reinforced concrete pipe',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Material,
                            'name' => 'Grade 20 Concrete Bedding & Haunching',
                            'unit_of_measure_id' => $m3->id,
                            'quantity_per_work_unit' => '0.150000',
                            'unit_cost' => '380000.0000',
                            'notes' => 'Granular bedding / mass concrete cradle',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Backhoe Loader (Lifting & Trenching)',
                            'unit_of_measure_id' => $hr?->id,
                            'quantity_per_work_unit' => '0.150000',
                            'unit_cost' => '90000.0000',
                            'notes' => 'Lowering pipes into trench',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Labour,
                            'name' => 'Culvert Mason & Crew',
                            'unit_of_measure_id' => $hr?->id,
                            'quantity_per_work_unit' => '1.000000',
                            'unit_cost' => '12000.0000',
                            'notes' => 'Jointing, alignment & packing',
                        ],
                    ],
                ],
                [
                    'code' => 'MAS-BLK-200',
                    'category' => 'Masonry & Walling',
                    'name' => '200mm Solid Concrete Block Walling (1:4 Mortar)',
                    'unit_of_measure_id' => $m2->id,
                    'default_selling_rate' => '65000.0000',
                    'specifications' => '200mm solid precast concrete blockwork in 1:4 cement sand mortar including hoop iron.',
                    'resources' => [
                        [
                            'resource_type' => EstimateResourceType::Material,
                            'name' => '200mm Solid Precast Concrete Block',
                            'unit_of_measure_id' => null,
                            'quantity_per_work_unit' => '10.500000',
                            'unit_cost' => '2800.0000',
                            'notes' => 'Includes 5% breakage allowance',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Material,
                            'name' => 'Cement CEM II 42.5N',
                            'unit_of_measure_id' => $bag?->id,
                            'quantity_per_work_unit' => '0.280000',
                            'unit_cost' => '38000.0000',
                            'notes' => 'Mortar binder',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Material,
                            'name' => 'Clean Plaster / Building Sand',
                            'unit_of_measure_id' => $m3->id,
                            'quantity_per_work_unit' => '0.045000',
                            'unit_cost' => '65000.0000',
                            'notes' => 'Mortar aggregate',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Labour,
                            'name' => 'Blocklayer Mason',
                            'unit_of_measure_id' => $hr?->id,
                            'quantity_per_work_unit' => '0.600000',
                            'unit_cost' => '10000.0000',
                            'notes' => 'Laying blockwork to line & level',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Labour,
                            'name' => 'Masonry Helper',
                            'unit_of_measure_id' => $hr?->id,
                            'quantity_per_work_unit' => '0.800000',
                            'unit_cost' => '4000.0000',
                            'notes' => 'Mortar mixing & block lifting',
                        ],
                    ],
                ],
            ];

            foreach ($templates as $data) {
                // Dynamically calculate unit cost
                $calculatedCost = 0.0;
                foreach ($data['resources'] as $res) {
                    $calculatedCost += ((float) $res['quantity_per_work_unit'] * (float) $res['unit_cost']);
                }

                $template = WorkItemTemplate::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'code' => $data['code'],
                    ],
                    [
                        'category' => $data['category'],
                        'name' => $data['name'],
                        'unit_of_measure_id' => $data['unit_of_measure_id'],
                        'default_selling_rate' => $data['default_selling_rate'],
                        'default_unit_cost' => (string) round($calculatedCost, 4),
                        'specifications' => $data['specifications'],
                        'is_active' => true,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]
                );

                $template->resources()->delete();

                foreach ($data['resources'] as $sortIndex => $resource) {
                    WorkItemResourceTemplate::query()->create([
                        'tenant_id' => $tenant->id,
                        'work_item_template_id' => $template->id,
                        'resource_type' => $resource['resource_type'],
                        'name' => $resource['name'],
                        'unit_of_measure_id' => $resource['unit_of_measure_id'],
                        'quantity_per_work_unit' => $resource['quantity_per_work_unit'],
                        'unit_cost' => $resource['unit_cost'],
                        'notes' => $resource['notes'],
                        'sort_order' => $sortIndex,
                    ]);
                }
            }
        }
    }
}
