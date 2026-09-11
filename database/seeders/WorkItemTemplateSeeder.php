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
    public function __construct(private readonly bool $quarryOnly = false) {}

    public function run(): void
    {
        $tenants = Tenant::query()->get();

        foreach ($tenants as $tenant) {
            resolve(TenantContext::class)->set($tenant);
            $user = User::query()->where('tenant_id', $tenant->id)->first();
            $actorId = $user?->id;

            $m3 = UnitOfMeasure::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'M3'],
                ['name' => 'Cubic metre', 'symbol' => 'm3', 'quantity_dimension' => 'volume', 'is_base_unit' => false, 'is_active' => true],
            );

            $m2 = UnitOfMeasure::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'M2'],
                ['name' => 'Square metre', 'symbol' => 'm2', 'quantity_dimension' => 'area', 'is_base_unit' => false, 'is_active' => true],
            );

            $m = UnitOfMeasure::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'M'],
                ['name' => 'Linear Metre', 'symbol' => 'm', 'quantity_dimension' => 'length', 'is_base_unit' => false, 'is_active' => true],
            );

            $bag = UnitOfMeasure::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'BAG'],
                ['name' => 'Bag', 'symbol' => 'bag', 'quantity_dimension' => 'count', 'is_base_unit' => false, 'is_active' => true],
            );

            $hr = UnitOfMeasure::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'HOUR'],
                ['name' => 'Hour', 'symbol' => 'hr', 'quantity_dimension' => 'time', 'is_base_unit' => true, 'is_active' => true],
            );

            $tonne = UnitOfMeasure::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'TONNE'],
                ['name' => 'Tonne', 'symbol' => 't', 'quantity_dimension' => 'mass', 'is_base_unit' => false, 'is_active' => true],
            );

            $kg = UnitOfMeasure::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'KG'],
                ['name' => 'Kilogram', 'symbol' => 'kg', 'quantity_dimension' => 'mass', 'is_base_unit' => true, 'is_active' => true],
            );

            $piece = UnitOfMeasure::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'PIECE'],
                ['name' => 'Piece', 'symbol' => 'pc', 'quantity_dimension' => 'count', 'is_base_unit' => true, 'is_active' => true],
            );

            $templates = [
                [
                    'code' => 'QRY-BLAST-01',
                    'category' => 'Quarry & Mining Operations',
                    'name' => 'Rock Drilling & Primary Blasting in Hard Granite',
                    'unit_of_measure_id' => $m3->id,
                    'default_selling_rate' => '48000.0000',
                    'specifications' => 'Primary quarry face benching 10-12m, 89mm blast holes with crawler rig, ANFO bulk charge and non-electric ms delay detonators.',
                    'resources' => [
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Crawler Hydraulic Drill Rig 89mm',
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.045000',
                            'unit_cost' => '250000.0000',
                            'notes' => 'Includes drill bit wear, shank adaptor, compressor fuel and operator',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Material,
                            'name' => 'ANFO Bulk Explosives (Porous Prill & Fuel Oil)',
                            'unit_of_measure_id' => $kg->id,
                            'quantity_per_work_unit' => '0.750000',
                            'unit_cost' => '12000.0000',
                            'notes' => 'Bulk column charge powder factor 0.75 kg/m3',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Material,
                            'name' => 'Cast Booster Primer 400g & Nonel Detonator',
                            'unit_of_measure_id' => $piece->id,
                            'quantity_per_work_unit' => '0.120000',
                            'unit_cost' => '18000.0000',
                            'notes' => 'Bottom hole initiation and surface tie-in trunkline',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Labour,
                            'name' => 'Certified Quarry Blaster & Charging Gang',
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.080000',
                            'unit_cost' => '16000.0000',
                            'notes' => 'Licensed shotfirer, hole charging, stemming and firing guard',
                        ],
                    ],
                ],
                [
                    'code' => 'QRY-CRUSH-01',
                    'category' => 'Quarry & Mining Operations',
                    'name' => 'Primary Jaw Crushing & Secondary Cone Crushing',
                    'unit_of_measure_id' => $tonne->id,
                    'default_selling_rate' => '24000.0000',
                    'specifications' => 'Feeding run-of-mine blasted rock into 150 TPH primary jaw and secondary cone crushing circuit to produce multi-fraction crushed stone.',
                    'resources' => [
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Crushing & Screening Plant 150TPH',
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.010000',
                            'unit_cost' => '450000.0000',
                            'notes' => 'Power generation fuel, jaw die plate wear, cone liner wear and plant operator',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Wheel Loader 5T (Crusher Feed)',
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.012000',
                            'unit_cost' => '180000.0000',
                            'notes' => 'Feeding primary hopper from blasted rock muckpile',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Labour,
                            'name' => 'Crusher Maintenance & Chute Clearing Crew',
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.030000',
                            'unit_cost' => '8000.0000',
                            'notes' => 'Feed monitoring and oversized rock picking',
                        ],
                    ],
                ],
                [
                    'code' => 'QRY-STK-01',
                    'category' => 'Quarry & Mining Operations',
                    'name' => 'Aggregate Screening, Stockpiling & Weighbridge Loading',
                    'unit_of_measure_id' => $tonne->id,
                    'default_selling_rate' => '15000.0000',
                    'specifications' => 'Triple-deck screen separation into 0/4 stone dust, 6/10mm chip, 14/20mm aggregate and base course, haulage to stock bays and loading customer tippers.',
                    'resources' => [
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Wheel Loader (Stockpile Loading)',
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.012000',
                            'unit_cost' => '180000.0000',
                            'notes' => 'Loading tippers at stock bays over certified weighbridge',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Internal Stockyard Dump Truck 20T',
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.015000',
                            'unit_cost' => '90000.0000',
                            'notes' => 'Conveyor discharge to segregated product bays within 500m',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Labour,
                            'name' => 'Weighbridge Clerk & Tally Spotter',
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.015000',
                            'unit_cost' => '6000.0000',
                            'notes' => 'Axle weight compliance, delivery note issuing and safety directing',
                        ],
                    ],
                ],
                [
                    'code' => 'QRY-STRIP-01',
                    'category' => 'Quarry & Mining Operations',
                    'name' => 'Overburden Soil Stripping & Pit Development',
                    'unit_of_measure_id' => $m3->id,
                    'default_selling_rate' => '16000.0000',
                    'specifications' => 'Stripping weathered topsoil and decomposed rock to expose fresh granite bed, hauling 1.5km to environmental spoil dump.',
                    'resources' => [
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Hydraulic Excavator 30T',
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.025000',
                            'unit_cost' => '280000.0000',
                            'notes' => 'Heavy bucket breakout and loading',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Articulated Dump Truck (ADT) 30T',
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.050000',
                            'unit_cost' => '160000.0000',
                            'notes' => 'Haulage over rough pit access ramps to spoil area',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Bulldozer D6 / D7 (Dump Trimming)',
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.015000',
                            'unit_cost' => '220000.0000',
                            'notes' => 'Spoil tip grading and safety bund maintenance',
                        ],
                    ],
                ],
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
                            'unit_of_measure_id' => $bag->id,
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
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.350000',
                            'unit_cost' => '40000.0000',
                            'notes' => 'Including fuel and operator',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Poker Vibrator',
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.350000',
                            'unit_cost' => '15000.0000',
                            'notes' => 'Petrol driven needle vibrator',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Labour,
                            'name' => 'Concreting Mason',
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.500000',
                            'unit_cost' => '10000.0000',
                            'notes' => 'Skilled tradesman',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Labour,
                            'name' => 'Concreting Helper Gang',
                            'unit_of_measure_id' => $hr->id,
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
                            'unit_of_measure_id' => $bag->id,
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
                            'unit_of_measure_id' => $hr->id,
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
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.040000',
                            'unit_cost' => '220000.0000',
                            'notes' => 'Wet rate including operator & fuel',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Tipper Truck 15T',
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.120000',
                            'unit_cost' => '30000.0000',
                            'notes' => 'Haulage within 2km',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Labour,
                            'name' => 'Banksman / Spotter',
                            'unit_of_measure_id' => $hr->id,
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
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.030000',
                            'unit_cost' => '180000.0000',
                            'notes' => 'Spreading & camber formation',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Vibratory Steel Roller 10T',
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.025000',
                            'unit_cost' => '120000.0000',
                            'notes' => 'Compaction passes',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Equipment,
                            'name' => 'Water Bowser 10,000L',
                            'unit_of_measure_id' => $hr->id,
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
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.150000',
                            'unit_cost' => '90000.0000',
                            'notes' => 'Lowering pipes into trench',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Labour,
                            'name' => 'Culvert Mason & Crew',
                            'unit_of_measure_id' => $hr->id,
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
                            'unit_of_measure_id' => $bag->id,
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
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.600000',
                            'unit_cost' => '10000.0000',
                            'notes' => 'Laying blockwork to line & level',
                        ],
                        [
                            'resource_type' => EstimateResourceType::Labour,
                            'name' => 'Masonry Helper',
                            'unit_of_measure_id' => $hr->id,
                            'quantity_per_work_unit' => '0.800000',
                            'unit_cost' => '4000.0000',
                            'notes' => 'Mortar mixing & block lifting',
                        ],
                    ],
                ],
            ];

            if ($this->quarryOnly) {
                $templates = array_values(array_filter($templates, fn (array $template): bool => str_starts_with($template['code'], 'QRY-')));
            }

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
