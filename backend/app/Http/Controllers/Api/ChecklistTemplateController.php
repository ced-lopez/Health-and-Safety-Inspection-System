<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ChecklistResource;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChecklistTemplateController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Checklist::query()->withCount('items');

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $checklists = $query->orderBy('category')->orderBy('name')->get();

        return $this->success([
            'checklist_templates' => ChecklistResource::collection($checklists),
        ], 'Checklist templates retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'items' => ['nullable', 'array'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.is_required' => ['boolean'],
        ]);

        $checklist = DB::transaction(function () use ($validated, $request) {
            $checklist = Checklist::query()->create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'category' => $validated['category'],
                'version' => 1,
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => $request->user()->id,
            ]);

            if (! empty($validated['items'])) {
                foreach ($validated['items'] as $i => $item) {
                    ChecklistItem::query()->create([
                        'checklist_id' => $checklist->id,
                        'title' => $item['title'],
                        'description' => $item['description'] ?? null,
                        'sort_order' => $i + 1,
                        'is_required' => $item['is_required'] ?? true,
                    ]);
                }
            }

            return $checklist;
        });

        AuditLogger::log(
            $request->user(),
            'Checklist Templates',
            'Created',
            "Created checklist template {$checklist->name}",
            $checklist,
            $request,
        );

        return $this->success(
            new ChecklistResource($checklist->load('items')),
            'Checklist template created successfully',
            201
        );
    }

    public function show(Checklist $checklist): JsonResponse
    {
        return $this->success(
            new ChecklistResource($checklist->load('items')),
            'Checklist template retrieved successfully'
        );
    }

    public function update(Request $request, Checklist $checklist): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'exists:checklist_items,id'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.is_required' => ['boolean'],
        ]);

        $checklist = DB::transaction(function () use ($checklist, $validated) {
            $checklist->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? $checklist->description,
                'category' => $validated['category'],
                'is_active' => $validated['is_active'] ?? $checklist->is_active,
            ]);

            if (isset($validated['items'])) {
                $submittedIds = [];
                foreach ($validated['items'] as $i => $item) {
                    if (! empty($item['id'])) {
                        ChecklistItem::query()->where('id', $item['id'])
                            ->where('checklist_id', $checklist->id)
                            ->update([
                                'title' => $item['title'],
                                'description' => $item['description'] ?? null,
                                'sort_order' => $i + 1,
                                'is_required' => $item['is_required'] ?? true,
                            ]);
                        $submittedIds[] = $item['id'];
                    } else {
                        $created = ChecklistItem::query()->create([
                            'checklist_id' => $checklist->id,
                            'title' => $item['title'],
                            'description' => $item['description'] ?? null,
                            'sort_order' => $i + 1,
                            'is_required' => $item['is_required'] ?? true,
                        ]);
                        $submittedIds[] = $created->id;
                    }
                }
                ChecklistItem::query()
                    ->where('checklist_id', $checklist->id)
                    ->whereNotIn('id', $submittedIds)
                    ->delete();
            }

            return $checklist->fresh();
        });

        AuditLogger::log(
            $request->user(),
            'Checklist Templates',
            'Updated',
            "Updated checklist template {$checklist->name}",
            $checklist,
            $request,
        );

        return $this->success(
            new ChecklistResource($checklist->load('items')),
            'Checklist template updated successfully'
        );
    }

    public function destroy(Request $request, Checklist $checklist): JsonResponse
    {
        $checklist->items()->delete();
        $checklist->delete();

        AuditLogger::log(
            $request->user(),
            'Checklist Templates',
            'Deleted',
            "Deleted checklist template {$checklist->name} (ID {$checklist->id})",
            $checklist,
            $request,
        );

        return $this->success(null, 'Checklist template deleted successfully');
    }
}
