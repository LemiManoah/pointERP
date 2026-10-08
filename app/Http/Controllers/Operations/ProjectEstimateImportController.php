<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Estimates\PreviewBoqImport;
use App\Actions\Operations\Estimates\SaveProjectEstimate;
use App\Enums\BoqItemType;
use App\Http\Requests\Operations\Estimates\PreviewBoqRequest;
use App\Http\Requests\Operations\Estimates\StoreProjectEstimateRequest;
use App\Http\Requests\Operations\Estimates\UploadBoqRequest;
use App\Models\EquipmentCategory;
use App\Models\BoqImport;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Models\ProjectEstimate;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WorkforceTrade;
use App\Services\BoqWorkbookReader;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Throwable;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @phpstan-import-type BoqSheet from PreviewBoqImport
 * @phpstan-import-type ProjectEstimatePayload from SaveProjectEstimate
 *
 * @phpstan-type ImportPreview array{rows: list<array<string, mixed>>, skipped: list<array<string, mixed>>, retained: list<array<string, mixed>>, existing: list<array<string, mixed>>, title?: string, notes?: string, currency_code?: string, preview_id?: string}
 * @phpstan-type ImportState array{name: string, sheets: list<BoqSheet>, format_version?: int, preview?: ImportPreview, saved_id?: string, base_id?: string|null, target_id?: string|null, fingerprint?: string, preview_id?: string, decisions?: array<string, mixed>}
 */
final class ProjectEstimateImportController
{
    public function index(Request $request, Project $project): Response|RedirectResponse
    {
        Gate::authorize('create', [ProjectEstimate::class, $project]);
        $token = $request->query('import');
        $state = is_string($token) && Str::isUuid($token) ? $this->state($project, $token) : null;

        if (isset($state['saved_id'])) {
            return to_route('project-estimates.show', $state['saved_id']);
        }

        if ($state !== null && isset($state['preview'])) {
            return to_route('project-estimates.import.review', ['project' => $project, 'import' => $token]);
        }

        return Inertia::render('operations/projects/estimates/import', [
            'project' => $project->only(['id', 'name', 'reference', 'base_currency_code']),
            'pageMode' => 'upload',
            'token' => $state === null ? null : $token,
            'imports' => BoqImport::query()->where('project_id', $project->id)->latest()->limit(50)->get()
                ->map(fn (BoqImport $item): array => [
                    'id' => $item->id, 'filename' => $item->filename,
                    'created_at' => $item->created_at?->toDateTimeString(),
                    'estimate_id' => $item->project_estimate_id ?? $item->state['saved_id'] ?? null,
                    'saved' => ($item->project_estimate_id ?? $item->state['saved_id'] ?? null) !== null,
                    'can_resume' => $item->uploaded_by === $request->user()->id
                        && ($item->state['format_version'] ?? null) === 1
                        && ($item->project_estimate_id ?? $item->state['saved_id'] ?? null) === null,
                ]),
            'filename' => $state['name'] ?? null,
            'targetId' => $state['target_id'] ?? null,
            'preview' => isset($state['preview']) && ($state['format_version'] ?? null) === 1 ? collect($state['preview'])->except('existing')->all() : null,
            'drafts' => ProjectEstimate::query()->where('project_id', $project->id)->where('status', 'draft')->orderByDesc('version_number')->get(['id', 'title', 'version_number']),
            'units' => UnitOfMeasure::query()->where('is_active', true)
                ->where(fn (Builder $query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $project->tenant_id))
                ->orderBy('name')->get()->map(fn (UnitOfMeasure $unit): array => ['value' => $unit->id, 'label' => $unit->name.' ('.$unit->code.')', 'dimension' => $unit->quantity_dimension->value]),
            'itemTypes' => collect(BoqItemType::cases())->map(fn (BoqItemType $type): array => ['value' => $type->value, 'label' => $type->label()]),
            'items' => InventoryItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'stock_unit_id'])->map(fn (InventoryItem $item): array => ['value' => $item->id, 'label' => $item->code.' - '.$item->name, 'unit_id' => $item->stock_unit_id]),
            'equipmentCategories' => EquipmentCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])->map(fn (EquipmentCategory $item): array => ['value' => $item->id, 'label' => mb_trim($item->code.' - '.$item->name)]),
            'workforceTrades' => WorkforceTrade::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])->map(fn (WorkforceTrade $item): array => ['value' => $item->id, 'label' => mb_trim($item->code.' - '.$item->name)]),
        ]);
    }

    public function template(Project $project): BinaryFileResponse
    {
        Gate::authorize('create', [ProjectEstimate::class, $project]);

        return response()->download(resource_path('templates/boq-import-template.xlsx'), 'BOQ-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function upload(UploadBoqRequest $request, Project $project, BoqWorkbookReader $reader, PreviewBoqImport $preview): RedirectResponse
    {
        $file = $request->file('file');
        $token = Str::uuid()->toString();
        $sheets = $reader->read($file->getPathname());
        $base = ProjectEstimate::query()->where('project_id', $project->id)->where('is_baseline', true)->first();
        $name = mb_substr(basename($file->getClientOriginalName()), 0, 255);
        $previewData = $preview->handle($project, ['name' => $name, 'sheets' => $sheets], $base);
        $state = [
            'name' => $name,
            'sheets' => $sheets,
            'format_version' => 1,
            'preview' => [
                ...$previewData,
                'title' => $base->title ?? $project->name.' BOQ',
                'notes' => $base->notes ?? '',
                'currency_code' => $base->currency_code ?? $project->base_currency_code,
            ],
            'base_id' => $base?->id,
            'target_id' => null,
            'fingerprint' => $this->fingerprint($base, $preview),
            'preview_id' => Str::uuid()->toString(),
        ];
        $state['preview']['preview_id'] = $state['preview_id'];
        $path = $file->store('boq-imports/'.$project->tenant_id.'/'.$project->id, 'local');
        throw_unless(is_string($path), LogicException::class, 'Unable to retain the BOQ workbook.');
        try {
            BoqImport::query()->create([
                'id' => $token, 'tenant_id' => $project->tenant_id, 'project_id' => $project->id,
                'uploaded_by' => $request->user()->id, 'filename' => $state['name'], 'path' => $path,
                'sha256' => hash_file('sha256', $file->getPathname()), 'state' => $state,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        Cache::store('file')->put($this->key($project, $token), $state, now()->addHours(2));

        return to_route('project-estimates.import.review', ['project' => $project, 'import' => $token]);
    }

    public function review(Project $project, string $import): Response|RedirectResponse
    {
        Gate::authorize('create', [ProjectEstimate::class, $project]);
        $state = $this->state($project, $import);
        if (! isset($state['preview'])) {
            return to_route('project-estimates.import', $project);
        }

        return $this->importPage($project, $import, $state, 'review');
    }

    /** @param ImportState $state */
    private function importPage(Project $project, string $import, array $state, string $pageMode): Response
    {
        return Inertia::render('operations/projects/estimates/import', [
            'project' => $project->only(['id', 'name', 'reference', 'base_currency_code']),
            'token' => $import,
            'pageMode' => $pageMode,
            'imports' => [],
            'filename' => $state['name'],
            'targetId' => $state['target_id'] ?? null,
            'preview' => collect($state['preview'])->except('existing')->all(),
            'drafts' => ProjectEstimate::query()->where('project_id', $project->id)->where('status', 'draft')->orderByDesc('version_number')->get(['id', 'title', 'version_number']),
            'units' => UnitOfMeasure::query()->where('is_active', true)
                ->where(fn (Builder $query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $project->tenant_id))
                ->orderBy('name')->get()->map(fn (UnitOfMeasure $unit): array => ['value' => $unit->id, 'label' => $unit->name.' ('.$unit->code.')', 'dimension' => $unit->quantity_dimension->value]),
            'itemTypes' => collect(BoqItemType::cases())->map(fn (BoqItemType $type): array => ['value' => $type->value, 'label' => $type->label()]),
            'items' => InventoryItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'stock_unit_id'])->map(fn (InventoryItem $item): array => ['value' => $item->id, 'label' => $item->code.' - '.$item->name, 'unit_id' => $item->stock_unit_id]),
            'equipmentCategories' => EquipmentCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])->map(fn (EquipmentCategory $item): array => ['value' => $item->id, 'label' => mb_trim($item->code.' - '.$item->name)]),
            'workforceTrades' => WorkforceTrade::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name'])->map(fn (WorkforceTrade $item): array => ['value' => $item->id, 'label' => mb_trim($item->code.' - '.$item->name)]),
        ]);
    }

    public function preview(PreviewBoqRequest $request, Project $project, string $import, PreviewBoqImport $action): RedirectResponse
    {
        return $this->lockStore()->lock($this->key($project, $import).':lock', 120)->block(5, fn (): RedirectResponse => DB::transaction(function () use ($request, $project, $import, $action): RedirectResponse {
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $state = $this->state($project, $import);
            if (isset($state['saved_id'])) {
                return to_route('project-estimates.show', $state['saved_id']);
            }

            $targetId = $request->validated('target_id');
            $base = $targetId !== null
                ? ProjectEstimate::query()->where('project_id', $project->id)->whereKey($targetId)->firstOrFail()
                : ProjectEstimate::query()->where('project_id', $project->id)->where('is_baseline', true)->first();
            if ($targetId !== null) {
                Gate::authorize('update', $base);
            }

            $state['preview'] = $action->handle($project, ['name' => $state['name'], 'sheets' => $state['sheets']], $base);
            $state['format_version'] = 1;
            $state['preview']['title'] = $base->title ?? $project->name.' BOQ';
            $state['preview']['notes'] = $base->notes ?? '';
            $state['preview']['currency_code'] = $base->currency_code ?? $project->base_currency_code;
            $state['base_id'] = $base?->id;
            $state['target_id'] = $targetId;
            $state['fingerprint'] = $this->fingerprint($base, $action);
            $state['preview_id'] = Str::uuid()->toString();
            $state['preview']['preview_id'] = $state['preview_id'];
            BoqImport::query()->whereKey($import)->where('project_id', $project->id)->where('uploaded_by', request()->user()->id)->first()?->update(['state' => $state]);
            Cache::store('file')->put($this->key($project, $import), $state, now()->addHours(2));

            return to_route('project-estimates.import.review', ['project' => $project, 'import' => $import]);
        }));
    }

    public function store(StoreProjectEstimateRequest $request, Project $project, string $import, SaveProjectEstimate $save, PreviewBoqImport $preview): RedirectResponse
    {
        Gate::authorize('create', [ProjectEstimate::class, $project]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $this->lockStore()->lock($this->key($project, $import).':lock', 120)->block(5, function () use ($request, $project, $import, $save, $preview, $actor): RedirectResponse {
            $state = $this->state($project, $import);
            if (isset($state['saved_id'])) {
                return to_route('project-estimates.show', $state['saved_id']);
            }

            if (($state['format_version'] ?? null) !== 1) {
                throw ValidationException::withMessages(['file' => 'This import used an older workbook format. Upload the downloaded BOQ template to continue.']);
            }

            if (! isset($state['preview']) || $request->input('preview_id') !== $state['preview_id']) {
                throw ValidationException::withMessages(['lines' => 'Generate a fresh preview before saving.']);
            }

            $estimate = DB::transaction(function () use ($request, $project, $state, $save, $preview, $actor, $import): ProjectEstimate {
                Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
                $base = isset($state['base_id']) ? ProjectEstimate::query()->where('project_id', $project->id)->whereKey($state['base_id'])->first() : null;
                if ($this->fingerprint($base, $preview) !== $state['fingerprint']) {
                    throw ValidationException::withMessages(['lines' => 'The BOQ changed after preview. Preview again before saving.']);
                }

                if ($state['target_id'] === null) {
                    $baselineId = ProjectEstimate::query()->where('project_id', $project->id)->where('is_baseline', true)->value('id');
                    if ($baselineId !== $state['base_id']) {
                        throw ValidationException::withMessages(['lines' => 'The project baseline changed. Preview again before saving.']);
                    }
                } else {
                    abort_unless($base instanceof ProjectEstimate, 404);
                    Gate::authorize('update', $base);
                }

                $data = $request->validated();
                $data['currency_code'] = $state['preview']['currency_code'];
                $available = collect($state['preview']['rows'])->filter(fn (array $row): bool => ! (bool) $row['blocked'] || (bool) ($row['daywork_resource_required'] ?? false))->keyBy('line.work_item_key');
                $merged = collect($state['preview']['existing'])->keyBy('work_item_key');
                foreach ($data['lines'] as $line) {
                    $candidate = $available->get($line['work_item_key'] ?? '');
                    if ($candidate === null) {
                        throw ValidationException::withMessages(['lines' => 'Only unambiguous items from this preview can be imported.']);
                    }

                    $allowed = array_intersect_key($line, array_flip(['bill', 'section', 'element', 'item_type', 'name', 'description', 'unit_of_measure_id', 'planned_quantity', 'selling_rate', 'percentage_rate', 'percentage_base_keys', 'daywork_resource_type', 'daywork_inventory_item_id', 'daywork_equipment_category_id', 'daywork_workforce_trade_id', 'boq_reference']));
                    if ($candidate['line']['item_type'] === 'percentage_adjustment' && ($allowed['item_type'] ?? null) !== 'percentage_adjustment') {
                        throw ValidationException::withMessages(['lines' => 'Percentage adjustments must retain their calculation type.']);
                    }

                    if ($candidate['line']['item_type'] === 'daywork' && ($allowed['item_type'] ?? null) !== 'daywork') {
                        throw ValidationException::withMessages(['lines' => 'Daywork entries must retain their daywork calculation type.']);
                    }

                    if (in_array($candidate['line']['item_type'], ['preliminary_fixed', 'preliminary_time'], true)
                        && ! in_array($allowed['item_type'] ?? null, ['preliminary_fixed', 'preliminary_time'], true)) {
                        throw ValidationException::withMessages(['lines' => 'Keep preliminary entries as fixed or time-based preliminaries. Correct the source heading if this classification is wrong.']);
                    }

                    if (($data['import_mode'] ?? 'scope') === 'prices') {
                        $existing = $merged->get($line['work_item_key']);
                        if (! $existing || $data['currency_code'] !== $base?->currency_code) {
                            throw ValidationException::withMessages(['lines' => 'A prices-only import requires matching existing items and the existing BOQ currency.']);
                        }
                        $priceField = $existing['item_type'] === 'percentage_adjustment' ? 'percentage_rate' : 'selling_rate';
                        $price = $allowed[$priceField] ?? null;
                        $merged->put($line['work_item_key'], [...$existing, $priceField => $price ?? $existing[$priceField] ?? null]);
                    } else {
                        $merged->put($line['work_item_key'], [...$candidate['line'], ...$allowed]);
                    }
                }

                $removeKeys = $data['remove_keys'] ?? [];
                $retainedKeys = array_column($state['preview']['retained'], 'work_item_key');
                if (($removeKeys !== [] && ($data['import_mode'] ?? 'scope') === 'prices') || array_diff($removeKeys, $retainedKeys) !== []) {
                    throw ValidationException::withMessages(['remove_keys' => 'Only explicitly reviewed missing items can be omitted in a scope import.']);
                }
                $merged = $merged->except($removeKeys);

                if ($merged->count() > 2000) {
                    throw ValidationException::withMessages(['lines' => 'A BOQ supports at most 2,000 items.']);
                }

                /** @var ProjectEstimatePayload $payload */
                $payload = [
                    'title' => $data['title'],
                    'currency_code' => $data['currency_code'],
                    'notes' => $data['notes'] ?? null,
                    'lines' => $merged->values()->all(),
                ];

                $saved = $save->handle($project, $payload, $actor, $state['target_id'] !== null ? $base : null);
                BoqImport::query()->whereKey($import)->where('project_id', $project->id)->where('uploaded_by', $actor->id)
                    ->first()?->update(['project_estimate_id' => $saved->id, 'state' => [...$state, 'saved_id' => $saved->id, 'decisions' => $data]]);
                return $saved;
            });
            $state['saved_id'] = $estimate->id;
            $state['decisions'] = $request->validated();
            BoqImport::query()->whereKey($import)->where('project_id', $project->id)->where('uploaded_by', request()->user()->id)->first()?->update(['state' => $state]);
            Cache::store('file')->put($this->key($project, $import), $state, now()->addHours(2));
            Inertia::flash('toast', ['type' => 'success', 'message' => 'BOQ imported into a draft. Review it before approving.']);

            return to_route('project-estimates.show', $estimate);
        });
    }

    public function source(Project $project, BoqImport $boqImport): StreamedResponse
    {
        Gate::authorize('create', [ProjectEstimate::class, $project]);
        abort_unless($boqImport->project_id === $project->id, 404);
        abort_unless(Storage::disk('local')->exists($boqImport->path), 404);
        return Storage::disk('local')->download($boqImport->path, $boqImport->filename);
    }

    private function fingerprint(?ProjectEstimate $estimate, PreviewBoqImport $preview): string
    {
        return hash('sha256', json_encode([$estimate?->only(['id', 'status', 'title', 'notes', 'currency_code', 'is_baseline']), $preview->existingLines($estimate)], JSON_THROW_ON_ERROR));
    }

    private function key(Project $project, string $token): string
    {
        return 'boq-import:'.$project->tenant_id.':'.request()->user()->id.':'.$project->id.':'.$token;
    }

    /** @return ImportState */
    private function state(Project $project, string $token): array
    {
        $archived = BoqImport::query()->whereKey($token)->where('project_id', $project->id)->where('uploaded_by', request()->user()->id)->first();
        $state = $archived?->state ?? Cache::store('file')->get($this->key($project, $token));
        if (! is_array($state)) {
            throw ValidationException::withMessages(['file' => 'This import expired or belongs to another session. Upload the workbook again.']);
        }

        /** @var ImportState $state */
        return $state;
    }

    private function lockStore(): LockProvider
    {
        $store = Cache::store('file')->getStore();
        throw_unless($store instanceof LockProvider, LogicException::class, 'The file cache store must support locks.');

        return $store;
    }
}
