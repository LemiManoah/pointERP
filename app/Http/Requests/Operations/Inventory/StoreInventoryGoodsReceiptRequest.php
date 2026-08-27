<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Services\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreInventoryGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof User && $actor->can('inventory.stock.receive');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'purchase_order_id' => ['required', 'uuid', Rule::exists((new PurchaseOrder)->getTable(), 'id')->where('tenant_id', $tenantId)->whereIn('status', ['approved', 'partially_received'])],
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'uuid', 'distinct', Rule::exists((new PurchaseOrderLine)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.accepted_quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.rejected_quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.rejection_reason' => ['nullable', 'string', 'max:2000'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:100'],
            'lines.*.manufactured_on' => ['nullable', 'date'],
            'lines.*.expires_on' => ['nullable', 'date', 'after_or_equal:lines.*.manufactured_on'],
        ];
    }

    /** @return array<int, Closure(Validator):void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $lines = $this->input('lines', []);
            if (! is_array($lines)) {
                return;
            }

            foreach ($lines as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $delivered = (float) ($line['quantity'] ?? 0);
                $accepted = (float) ($line['accepted_quantity'] ?? 0);
                $rejected = (float) ($line['rejected_quantity'] ?? 0);
                if (abs(($accepted + $rejected) - $delivered) > 0.0001) {
                    $validator->errors()->add(sprintf('lines.%s.accepted_quantity', $index), 'Accepted plus rejected quantity must equal delivered quantity.');
                }

                if ($rejected > 0 && empty($line['rejection_reason'])) {
                    $validator->errors()->add(sprintf('lines.%s.rejection_reason', $index), 'Give a reason for rejected quantity.');
                }
            }
        }];
    }
}
