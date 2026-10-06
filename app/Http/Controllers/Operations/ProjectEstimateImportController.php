<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Estimates\PreviewBoqImport;
use App\Actions\Operations\Estimates\SaveProjectEstimate;
use App\Enums\BoqItemType;
use App\Http\Requests\Operations\Estimates\PreviewBoqRequest;
use App\Http\Requests\Operations\Estimates\StoreProjectEstimateRequest;
use App\Http\Requests\Operations\Estimates\UploadBoqRequest;
use App\Models\Project;
use App\Models\ProjectEstimate;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\BoqWorkbookReader;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @phpstan-import-type BoqSheet from PreviewBoqImport
 * @phpstan-import-type ProjectEstimatePayload from SaveProjectEstimate
 *
 * @phpstan-type ImportPreview array{rows: list<array<string, mixed>>, skipped: list<array<string, mixed>>, retained: list<array<string, mixed>>, existing: list<array<string, mixed>>, title?: string, notes?: string, currency_code?: string, preview_id?: string}
 * @phpstan-type ImportState array{name: string, sheets: list<BoqSheet>, preview?: ImportPreview, saved_id?: string, base_id?: string|null, target_id?: string|null, fingerprint?: string, preview_id?: string}
 */
final class ProjectEstimateImportController
{
    public function index(Request $request, Project $project): Response
    {
        Gate::authorize('create', [ProjectEstimate::class, $project]);
        $token = $request->query('import');
        $state = is_string($token) && Str::isUuid($token) ? $this->state($project, $token) : null;

        return Inertia::render('operations/projects/estimates/import', [
            'project' => $project->only(['id', 'name', 'reference', 'base_currency_code']),
            'token' => $state === null ? null : $token,
            'filename' => $state['name'] ?? null,
            'sheets' => collect($state['sheets'] ?? [])->map(fn (array $sheet): array => [
                'id' => $sheet['id'], 'name' => $sheet['name'], 'hidden' => $sheet['hidden'],
                'last_row' => $sheet['rows'] === [] ? 1 : max(array_keys($sheet['rows'])),
                'sample' => array_slice($sheet['rows'], 0, 12, true),
            ])->values(),
            'preview' => isset($state['preview']) ? collect($state['preview'])->except('existing')->all() : null,
            'drafts' => ProjectEstimate::query()->where('project_id', $project->id)->where('status', 'draft')->orderByDesc('version_number')->get(['id', 'title', 'version_number']),
            'units' => UnitOfMeasure::query()->where('is_active', true)
                ->where(fn (Builder $query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $project->tenant_id))
                ->orderBy('name')->get()->map(fn (UnitOfMeasure $unit): array => ['value' => $unit->id, 'label' => $unit->name.' ('.$unit->code.')', 'dimension' => $unit->quantity_dimension->value]),
            'itemTypes' => collect(BoqItemType::cases())->map(fn (BoqItemType $type): array => ['value' => $type->value, 'label' => $type->label()]),
        ]);
    }

    public function template(Project $project): BinaryFileResponse
    {
        Gate::authorize('create', [ProjectEstimate::class, $project]);

        return response()->download(resource_path('templates/boq-import-template.xlsx'), 'BOQ-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function upload(UploadBoqRequest $request, Project $project, BoqWorkbookReader $reader): RedirectResponse
    {
        $file = $request->file('file');
        $token = Str::uuid()->toString();
        $sheets = $reader->read($file->getPathname());
        Cache::store('file')->put($this->key($project, $token), ['name' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'sheets' => $sheets], now()->addHours(2));

        return to_route('project-estimates.import', ['project' => $project, 'import' => $token]);
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

            $state['preview'] = $action->handle($project, ['name' => $state['name'], 'sheets' => $state['sheets']], $request->validated('sheets'), $base);
            $state['preview']['title'] = $base->title ?? $project->name.' BOQ';
            $state['preview']['notes'] = $base->notes ?? '';
            $state['preview']['currency_code'] = $base->currency_code ?? $project->base_currency_code;
            $state['base_id'] = $base?->id;
            $state['target_id'] = $targetId;
            $state['fingerprint'] = $this->fingerprint($base, $action);
            $state['preview_id'] = Str::uuid()->toString();
            $state['preview']['preview_id'] = $state['preview_id'];
            Cache::store('file')->put($this->key($project, $import), $state, now()->addHours(2));

            return to_route('project-estimates.import', ['project' => $project, 'import' => $import]);
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

            if (! isset($state['preview']) || $request->input('preview_id') !== $state['preview_id']) {
                throw ValidationException::withMessages(['lines' => 'Generate a fresh preview before saving.']);
            }

            $estimate = DB::transaction(function () use ($request, $project, $state, $save, $preview, $actor): ProjectEstimate {
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
                $available = collect($state['preview']['rows'])->reject(fn (array $row): bool => (bool) $row['blocked'])->keyBy('line.work_item_key');
                $merged = collect($state['preview']['existing'])->keyBy('work_item_key');
                foreach ($data['lines'] as $line) {
                    $candidate = $available->get($line['work_item_key'] ?? '');
                    if ($candidate === null) {
                        throw ValidationException::withMessages(['lines' => 'Only unambiguous items from this preview can be imported.']);
                    }

                    $allowed = array_intersect_key($line, array_flip(['bill', 'section', 'element', 'item_type', 'name', 'description', 'unit_of_measure_id', 'planned_quantity', 'selling_rate', 'percentage_rate', 'percentage_base_keys', 'boq_reference']));
                    if ($candidate['line']['item_type'] === 'percentage_adjustment' && ($allowed['item_type'] ?? null) !== 'percentage_adjustment') {
                        throw ValidationException::withMessages(['lines' => 'Percentage adjustments must retain their calculation type.']);
                    }
                    if (in_array($candidate['line']['item_type'], ['preliminary_fixed', 'preliminary_time'], true)
                        && ! in_array($allowed['item_type'] ?? null, ['preliminary_fixed', 'preliminary_time'], true)) {
                        throw ValidationException::withMessages(['lines' => 'Keep preliminary entries as fixed or time-based preliminaries. Correct the source mapping if this classification is wrong.']);
                    }
                    $merged->put($line['work_item_key'], [...$candidate['line'], ...$allowed]);
                }

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

                return $save->handle($project, $payload, $actor, $state['target_id'] !== null ? $base : null);
            });
            $state['saved_id'] = $estimate->id;
            Cache::store('file')->put($this->key($project, $import), $state, now()->addHours(2));
            Inertia::flash('toast', ['type' => 'success', 'message' => 'BOQ imported into a draft. Review it before approving.']);

            return to_route('project-estimates.show', $estimate);
        });
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
        $state = Cache::store('file')->get($this->key($project, $token));
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
