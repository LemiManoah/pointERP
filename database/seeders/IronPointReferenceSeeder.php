<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UnitDimension;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\InventoryPriceTier;
use App\Models\ReportingCalendar;
use App\Models\Tenant;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;

final class IronPointReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'IRONPOINT')->firstOrFail();
        $actor = User::query()->where('tenant_id', $tenant->id)->where('email', 'lemi.manoah@gmail.com')->firstOrFail();
        resolve(TenantContext::class)->set($tenant);

        $units = [];
        foreach ([
            ['KG', 'Kilogram', 'kg', UnitDimension::Mass, true],
            ['G', 'Gram', 'g', UnitDimension::Mass, false],
            ['TONNE', 'Tonne', 't', UnitDimension::Mass, false],
            ['LITRE', 'Litre', 'L', UnitDimension::Volume, true],
            ['MILLILITRE', 'Millilitre', 'ml', UnitDimension::Volume, false],
            ['METRE', 'Metre', 'm', UnitDimension::Length, true],
            ['KILOMETRE', 'Kilometre', 'km', UnitDimension::Length, false],
            ['SQM', 'Square metre', 'm2', UnitDimension::Area, true],
            ['PIECE', 'Piece', 'pc', UnitDimension::Count, true],
            ['BAG', 'Bag', 'bag', UnitDimension::Count, false],
            ['TRIP', 'Trip', 'trip', UnitDimension::Count, false],
            ['MEAL', 'Meal', 'meal', UnitDimension::Count, false],
            ['NIGHT', 'Night', 'night', UnitDimension::Time, false],
            ['DAY', 'Day', 'day', UnitDimension::Time, true],
            ['HOUR', 'Hour', 'hr', UnitDimension::Time, false],
        ] as [$code, $name, $symbol, $dimension, $isBase]) {
            $units[$code] = UnitOfMeasure::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                [
                    'name' => $name,
                    'symbol' => $symbol,
                    'quantity_dimension' => $dimension,
                    'is_base_unit' => $isBase,
                    'is_active' => true,
                ],
            );
        }

        foreach ([
            ['RETAIL', 'Retail', 'Standard counter selling price.', 100],
            ['WHOLESALE', 'Wholesale', 'Bulk customer selling price.', 50],
        ] as [$code, $name, $description, $priority]) {
            $tier = InventoryPriceTier::query()->withTrashed()->firstOrNew([
                'tenant_id' => $tenant->id,
                'code' => $code,
            ]);
            $tier->fill([
                'name' => $name,
                'description' => $description,
                'priority' => $priority,
                'is_active' => true,
                'created_by' => $tier->exists ? $tier->created_by : $actor->id,
                'updated_by' => $actor->id,
            ]);
            $tier->deleted_at = null;
            $tier->save();
        }

        $categories = [];
        foreach ([
            ['UTILITIES', 'Utilities', true, 'Electricity, water, internet and similar services.'],
            ['SITE-WELFARE', 'Site welfare', false, 'Meals, drinking water and workforce welfare.'],
            ['TRAVEL', 'Travel and accommodation', true, 'Transport and accommodation costs.'],
            ['STATUTORY', 'Statutory and permits', true, 'Permits, licences and statutory charges.'],
            ['ADMIN', 'Administration', false, 'Office, printing and communication costs.'],
        ] as [$code, $name, $requiresEvidence, $description]) {
            $category = ExpenseCategory::query()->withTrashed()->firstOrNew([
                'tenant_id' => $tenant->id,
                'code' => $code,
            ]);
            $category->fill([
                'name' => $name,
                'description' => $description,
                'requires_evidence' => $requiresEvidence,
                'is_active' => true,
                'created_by' => $category->exists ? $category->created_by : $actor->id,
                'updated_by' => $actor->id,
            ]);
            $category->deleted_at = null;
            $category->save();
            $categories[$code] = $category;
        }

        foreach ([
            ['YAKA', 'Yaka electricity', 'UTILITIES', false, null],
            ['WATER', 'Water', 'UTILITIES', false, null],
            ['INTERNET', 'Internet subscription', 'UTILITIES', false, null],
            ['SITE-MEALS', 'Site meals', 'SITE-WELFARE', true, 'MEAL'],
            ['DRINKING-WATER', 'Drinking water', 'SITE-WELFARE', true, 'LITRE'],
            ['LOCAL-TRANSPORT', 'Local transport', 'TRAVEL', true, 'TRIP'],
            ['ACCOMMODATION', 'Accommodation', 'TRAVEL', true, 'NIGHT'],
            ['PERMIT-FEE', 'Permit fee', 'STATUTORY', false, null],
            ['PRINTING', 'Printing and photocopying', 'ADMIN', false, null],
        ] as [$code, $name, $categoryCode, $hasQuantity, $unitCode]) {
            $item = ExpenseItem::query()->withTrashed()->firstOrNew([
                'tenant_id' => $tenant->id,
                'code' => $code,
            ]);
            $item->fill([
                'expense_category_id' => $categories[$categoryCode]->id,
                'default_unit_of_measure_id' => $unitCode === null ? null : $units[$unitCode]->id,
                'name' => $name,
                'has_quantity' => $hasQuantity,
                'requires_evidence' => false,
                'is_active' => true,
                'created_by' => $item->exists ? $item->created_by : $actor->id,
                'updated_by' => $actor->id,
            ]);
            $item->deleted_at = null;
            $item->save();
        }

        $calendar = ReportingCalendar::query()->withTrashed()->firstOrNew([
            'tenant_id' => $tenant->id,
            'project_id' => null,
            'site_id' => null,
            'name' => 'IronPoint standard reporting calendar',
        ]);
        $calendar->fill([
            'branch_id' => null,
            'timezone' => 'Africa/Kampala',
            'reporting_deadline' => '18:00:00',
            'working_days' => [1, 2, 3, 4, 5, 6],
            'missing_escalation_days' => 2,
            'is_active' => true,
            'created_by' => $calendar->exists ? $calendar->created_by : $actor->id,
            'updated_by' => $actor->id,
        ]);
        $calendar->deleted_at = null;
        $calendar->save();
    }
}
