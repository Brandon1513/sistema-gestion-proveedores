<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use App\Models\ProviderType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\DocumentGroup;

class DocumentTypeController extends Controller
{
    // ─── Listar todos los tipos de documentos agrupados por tipo de proveedor ──
    public function index(Request $request)
    {
        $providerTypes = ProviderType::orderBy('name')->get();

        $result = $providerTypes->map(function ($pt) {
            $docs = DocumentType::whereHas('providerTypes', fn($q) => $q->where('provider_types.id', $pt->id))
                ->with(['providerTypes' => fn($q) => $q->withPivot(['is_required','sort_order','applies_to_existing'])])
                ->orderBy('sort_order')
                ->orderBy('group_name')
                ->orderBy('name')
                ->get()
                ->map(function ($doc) use ($pt) {
                    $pivot = $doc->providerTypes->firstWhere('id', $pt->id)?->pivot;
                    return [
                        'id'                  => $doc->id,
                        'code'                => $doc->code,
                        'name'                => $doc->name,
                        'description'         => $doc->description,
                        'category'            => $doc->category,
                        'group_name'          => $doc->group_name,
                        'sort_order'          => $doc->sort_order,
                        'is_active'           => $doc->is_active,
                        'requires_expiry'     => $doc->requires_expiry,
                        'expiry_alert_days'   => $doc->expiry_alert_days,
                        'allows_multiple'     => $doc->allows_multiple,
                        'allowed_extensions'  => $doc->allowed_extensions,
                        'max_file_size_mb'    => $doc->max_file_size_mb,
                        'is_required'         => $pivot?->is_required ?? false,
                        'pivot_sort_order'    => $pivot?->sort_order ?? 0,
                        'applies_to_existing' => $pivot?->applies_to_existing ?? true,
                        'expiry_months'       => $doc->expiry_months,
                        'is_product_specific' => $doc->is_product_specific,
                        'provider_types'      => $doc->providerTypes->map(fn($ptype) => [
                            'id'   => $ptype->id,
                            'name' => $ptype->name,
                            'pivot' => [
                                'is_required'         => $ptype->pivot->is_required,
                                'sort_order'          => $ptype->pivot->sort_order,
                                'applies_to_existing' => $ptype->pivot->applies_to_existing,
                            ],
                        ])->values()->toArray(),
                    ];
                });

            return [
                'provider_type'   => $pt,
                'documents'       => $docs,
                'documents_count' => $docs->count(),
            ];
        });

        $unassigned = DocumentType::whereDoesntHave('providerTypes')
            ->with('providerTypes')
            ->orderBy('group_name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn($doc) => array_merge($doc->toArray(), ['provider_types' => []]));

        return response()->json([
            'provider_types' => $result,
            'unassigned'     => $unassigned,
        ]);
    }

    // ─── Crear nuevo tipo de documento ───────────────────────────────────────
    public function store(Request $request)
    {
        // ✅ Convertir string vacío a null antes de validar
        if ($request->has('code') && trim((string) $request->code) === '') {
            $request->merge(['code' => null]);
        }

        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'code'                => 'nullable|string|max:50|unique:document_types,code',
            'description'         => 'nullable|string|max:1000',
            'category'            => ['required', Rule::in(['fiscal', 'tecnico', 'legal', 'otro'])],
            'group_name'          => 'nullable|string|max:100',
            'requires_expiry'     => 'boolean',
            'expiry_alert_days'   => 'nullable|integer|min:1|max:365',
            'allows_multiple'     => 'boolean',
            'allowed_extensions'  => 'nullable',
            'max_file_size_mb'    => 'nullable|integer|min:1|max:100',
            'provider_type_ids'   => 'nullable|array',
            'provider_type_ids.*' => 'exists:provider_types,id',
            'is_required_map'     => 'nullable|array',
            'applies_to_existing' => 'boolean',
            'expiry_months'       => 'nullable|integer|min:1|max:120',
            'is_product_specific' => 'boolean',
        ]);

        // ✅ Auto-generar código único si no viene
        if (empty($validated['code'])) {
            $base = Str::slug($validated['name'], '_') ?: 'doc';
            $code = $base;
            $i    = 1;
            while (DocumentType::where('code', $code)->exists()) {
                $code = $base . '_' . $i++;
            }
            $validated['code'] = $code;
        }

        DB::beginTransaction();
        try {
            $doc = DocumentType::create([
                'name'               => $validated['name'],
                'code'               => $validated['code'],
                'description'        => $validated['description'] ?? null,
                'category'           => $validated['category'],
                'group_name'         => $validated['group_name'] ?? null,
                'requires_expiry'    => $validated['requires_expiry'] ?? false,
                'expiry_alert_days'  => $validated['expiry_alert_days'] ?? 30,
                'allows_multiple'    => $validated['allows_multiple'] ?? false,
                'allowed_extensions' => is_array($validated['allowed_extensions'] ?? null)
                    ? implode(',', $validated['allowed_extensions'])
                    : ($validated['allowed_extensions'] ?? null),
                'max_file_size_mb'   => $validated['max_file_size_mb'] ?? 10,
                'is_active'          => true,
                'is_required'        => false,
                'expiry_months'      => $validated['expiry_months'] ?? null,
                'is_product_specific' => $validated['is_product_specific'] ?? false,
            ]);

            if (!empty($validated['provider_type_ids'])) {
                $pivotData = [];
                foreach ($validated['provider_type_ids'] as $ptId) {
                    $isReq = $validated['is_required_map'][$ptId] ?? false;
                    $pivotData[$ptId] = [
                        'is_required'         => $isReq,
                        'sort_order'          => 0,
                        'applies_to_existing' => $validated['applies_to_existing'] ?? true,
                    ];
                }
                $doc->providerTypes()->sync($pivotData);
            }

            DB::commit();
            return response()->json([
                'message'  => 'Documento creado correctamente',
                'document' => $doc->load('providerTypes'),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear: ' . $e->getMessage()], 500);
        }
    }

    // ─── Actualizar tipo de documento ─────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $doc = DocumentType::findOrFail($id);

        // ✅ Convertir string vacío a null antes de validar
        if ($request->has('code') && trim((string) $request->code) === '') {
            $request->merge(['code' => null]);
        }

        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'code'                => ['nullable', 'string', 'max:50', Rule::unique('document_types', 'code')->ignore($id)],
            'description'         => 'nullable|string|max:1000',
            'category'            => ['required', Rule::in(['fiscal', 'tecnico', 'legal', 'otro'])],
            'group_name'          => 'nullable|string|max:100',
            'requires_expiry'     => 'boolean',
            'expiry_alert_days'   => 'nullable|integer|min:1|max:365',
            'allows_multiple'     => 'boolean',
            'allowed_extensions'  => 'nullable',
            'max_file_size_mb'    => 'nullable|integer|min:1|max:100',
            'is_active'           => 'boolean',
            'provider_type_ids'   => 'nullable|array',
            'provider_type_ids.*' => 'exists:provider_types,id',
            'is_required_map'     => 'nullable|array',
            'applies_to_existing' => 'boolean',
            'expiry_months'       => 'nullable|integer|min:1|max:120',
            'is_product_specific' => 'boolean',
        ]);

        // ✅ Auto-generar código único si no viene (al actualizar)
        if (empty($validated['code'])) {
            $base = Str::slug($validated['name'], '_') ?: 'doc';
            $code = $base;
            $i    = 1;
            while (DocumentType::where('code', $code)->where('id', '!=', $id)->exists()) {
                $code = $base . '_' . $i++;
            }
            $validated['code'] = $code;
        }

        DB::beginTransaction();
        try {
            $doc->update([
                'name'               => $validated['name'],
                'code'               => $validated['code'],
                'description'        => $validated['description'] ?? null,
                'category'           => $validated['category'],
                'group_name'         => $validated['group_name'] ?? null,
                'requires_expiry'    => $validated['requires_expiry'] ?? false,
                'expiry_alert_days'  => $validated['expiry_alert_days'] ?? 30,
                'allows_multiple'    => $validated['allows_multiple'] ?? false,
                'allowed_extensions' => is_array($validated['allowed_extensions'] ?? null)
                    ? implode(',', $validated['allowed_extensions'])
                    : ($validated['allowed_extensions'] ?? null),
                'max_file_size_mb'   => $validated['max_file_size_mb'] ?? 10,
                'is_active'          => $validated['is_active'] ?? true,
                'expiry_months'      => $validated['expiry_months'] ?? null,
                'is_product_specific' => $validated['is_product_specific'] ?? false,
            ]);

            if (isset($validated['provider_type_ids'])) {
                $pivotData = [];
                foreach ($validated['provider_type_ids'] as $ptId) {
                    $isReq    = $validated['is_required_map'][$ptId] ?? false;
                    $existing = DB::table('document_type_provider_type')
                        ->where('document_type_id', $id)
                        ->where('provider_type_id', $ptId)
                        ->first();
                    $pivotData[$ptId] = [
                        'is_required'         => $isReq,
                        'sort_order'          => $existing->sort_order ?? 0,
                        'applies_to_existing' => $validated['applies_to_existing'] ?? true,
                    ];
                }
                $doc->providerTypes()->sync($pivotData);
            }

            DB::commit();
            return response()->json([
                'message'  => 'Documento actualizado correctamente',
                'document' => $doc->load('providerTypes'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    // ─── Activar / Desactivar ─────────────────────────────────────────────────
    public function toggleActive($id)
    {
        $doc = DocumentType::findOrFail($id);
        $doc->update(['is_active' => !$doc->is_active]);
        return response()->json([
            'message'   => $doc->is_active ? 'Documento activado' : 'Documento desactivado',
            'is_active' => $doc->is_active,
        ]);
    }

    // ─── Reordenar documentos dentro de un tipo de proveedor ─────────────────
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'provider_type_id' => 'required|exists:provider_types,id',
            'ordered_ids'      => 'required|array',
            'ordered_ids.*'    => 'integer|exists:document_types,id',
        ]);

        DB::beginTransaction();
        try {
            foreach ($validated['ordered_ids'] as $index => $docId) {
                DB::table('document_type_provider_type')
                    ->where('document_type_id', $docId)
                    ->where('provider_type_id', $validated['provider_type_id'])
                    ->update(['sort_order' => $index]);
            }
            DB::commit();
            return response()->json(['message' => 'Orden actualizado correctamente']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al reordenar: ' . $e->getMessage()], 500);
        }
    }

    // ─── Eliminar asignación de documento a tipo de proveedor ────────────────
    public function removeFromProviderType(Request $request, $id)
    {
        $validated = $request->validate([
            'provider_type_id' => 'required|exists:provider_types,id',
        ]);

        DB::table('document_type_provider_type')
            ->where('document_type_id', $id)
            ->where('provider_type_id', $validated['provider_type_id'])
            ->delete();

        return response()->json(['message' => 'Documento removido del tipo de proveedor']);
    }

    // ─── Obtener tipos de proveedor disponibles ───────────────────────────────
    public function providerTypes()
    {
        $types = ProviderType::orderBy('name')->get(['id', 'name', 'code']);
        return response()->json(['provider_types' => $types]);
    }

    // ─── Listar grupos ────────────────────────────────────────────────────────
    public function getGroups()
    {
        $groups = DocumentGroup::orderBy('sort_order')->orderBy('name')->get();
        return response()->json(['groups' => $groups]);
    }

    // ─── Crear grupo ──────────────────────────────────────────────────────────
    public function storeGroup(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100|unique:document_groups,name',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $group = DocumentGroup::create([
            'name'       => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? DocumentGroup::max('sort_order') + 1,
            'is_active'  => true,
        ]);

        return response()->json(['message' => 'Grupo creado correctamente', 'group' => $group], 201);
    }

    // ─── Actualizar grupo ─────────────────────────────────────────────────────
    public function updateGroup(Request $request, $id)
    {
        $group = DocumentGroup::findOrFail($id);

        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:100', Rule::unique('document_groups', 'name')->ignore($id)],
            'is_active' => 'boolean',
        ]);

        if (isset($validated['is_active']) && !$validated['is_active'] && $group->is_active) {
            DocumentType::where('group_name', $group->name)->update(['group_name' => null]);
        }

        if ($validated['name'] !== $group->name) {
            DocumentType::where('group_name', $group->name)->update(['group_name' => $validated['name']]);
        }

        $group->update($validated);

        return response()->json(['message' => 'Grupo actualizado correctamente', 'group' => $group]);
    }

    // ─── Eliminar grupo ───────────────────────────────────────────────────────
    public function destroyGroup($id)
    {
        $group = DocumentGroup::findOrFail($id);
        DocumentType::where('group_name', $group->name)->update(['group_name' => null]);
        $group->delete();
        return response()->json(['message' => 'Grupo eliminado correctamente']);
    }

    // ─── Reordenar grupos ─────────────────────────────────────────────────────
    public function reorderGroups(Request $request)
    {
        $validated = $request->validate([
            'ordered_ids'   => 'required|array',
            'ordered_ids.*' => 'integer|exists:document_groups,id',
        ]);

        foreach ($validated['ordered_ids'] as $index => $groupId) {
            DocumentGroup::where('id', $groupId)->update(['sort_order' => $index]);
        }

        return response()->json(['message' => 'Orden de grupos actualizado']);
    }
}