<?php

namespace Modules\MasterData\Presentation\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Audit\Application\AuditLogger;

/**
 * Shared CRUD for the master-data lookups that are all shaped the same way
 * (name, unique code, is_active): activity types, product categories,
 * positions, work locations. Products is the only master-data resource with
 * a real relationship (a category), so it gets its own controller — it
 * still inherits store/update/destroy (and their audit logging) from here.
 */
abstract class SimpleMasterDataController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    public function __construct(protected readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        $query = ($this->model)::query();

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => ($this->model)::findOrFail($id)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $record = ($this->model)::create($data);

        $this->auditLogger->log($request->user(), 'created', $this->model, $record->id, newValues: $data);

        return response()->json(['data' => $record], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        /** @var Model $record */
        $record = ($this->model)::findOrFail($id);

        $data = $request->validate($this->rules($record->id));
        $oldValues = $record->only(array_keys($data));

        $record->update($data);

        $this->auditLogger->log($request->user(), 'updated', $this->model, $record->id, $oldValues, $data);

        return response()->json(['data' => $record]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var Model $record */
        $record = ($this->model)::findOrFail($id);
        $oldValues = $record->getAttributes();
        $record->delete();

        $this->auditLogger->log($request->user(), 'deleted', $this->model, $id, oldValues: $oldValues);

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(?int $ignoreId = null): array
    {
        $table = (new ($this->model))->getTable();

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', Rule::unique($table, 'code')->ignore($ignoreId)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
