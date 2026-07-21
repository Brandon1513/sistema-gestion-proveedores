<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProviderType;
use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ProviderTypeController extends Controller
{
    /**
     * Lista de tipos de proveedores
     */
    public function index(): JsonResponse
    {
        $providerTypes = ProviderType::withCount('providers')
            ->orderBy('name')
            ->get();

        return response()->json([
            'provider_types' => $providerTypes,
        ]);
    }

    /**
     * Crear tipo de proveedor
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:provider_types,name',
            'code'        => 'nullable|string|max:50|unique:provider_types,code',
            'form_code'   => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
        ]);

        if (empty($validated['code'])) {
            $validated['code'] = Str::slug($validated['name'], '_');
        }

        $providerType = ProviderType::create([
            'name'        => $validated['name'],
            'code'        => $validated['code'],
            'form_code'   => $validated['form_code'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active'   => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message'       => 'Tipo de proveedor creado correctamente',
            'provider_type' => $providerType->loadCount('providers'),
        ], 201);
    }

    /**
     * Mostrar tipo de proveedor con sus documentos
     */
    public function show(ProviderType $providerType): JsonResponse
    {
        $providerType->load(['documentTypes' => function ($query) {
            $query->withPivot(['is_required', 'sort_order', 'applies_to_existing', 'applies_to_persona'])
                  ->orderBy('category')
                  ->orderBy('name');
        }]);

        $providerType->loadCount('providers');

        return response()->json([
            'provider_type' => $providerType,
        ]);
    }

    /**
     * Actualizar tipo de proveedor
     */
    public function update(Request $request, ProviderType $providerType): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:provider_types,name,' . $providerType->id,
            'code'        => 'nullable|string|max:50|unique:provider_types,code,' . $providerType->id,
            'form_code'   => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
        ]);

        $providerType->update($validated);

        return response()->json([
            'message'       => 'Tipo de proveedor actualizado correctamente',
            'provider_type' => $providerType->loadCount('providers'),
        ]);
    }

    /**
     * Activar / Desactivar tipo de proveedor
     */
    public function toggleActive(ProviderType $providerType): JsonResponse
    {
        $providerType->update(['is_active' => !$providerType->is_active]);

        return response()->json([
            'message'   => $providerType->is_active ? 'Tipo activado' : 'Tipo desactivado',
            'is_active' => $providerType->is_active,
        ]);
    }

    /**
     * Eliminar tipo de proveedor (solo si no tiene proveedores)
     */
    public function destroy(ProviderType $providerType): JsonResponse
    {
        if ($providerType->providers()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un tipo de proveedor que tiene proveedores asignados.',
            ], 422);
        }

        $providerType->documentTypes()->detach();
        $providerType->delete();

        return response()->json(['message' => 'Tipo de proveedor eliminado correctamente']);
    }

    /**
     * Documentos requeridos por tipo de proveedor (agrupados por categoría)
     */
    public function requiredDocuments(ProviderType $providerType): JsonResponse
    {
        $documents = $providerType->documentTypes()
            ->wherePivot('is_required', true)
            ->get()
            ->groupBy('category');

        return response()->json([
            'required_documents' => $documents,
            'total'              => $providerType->documentTypes()->wherePivot('is_required', true)->count(),
        ]);
    }

    /**
     * Todos los documentos del tipo (para modal de subida admin)
     * Incluye applies_to_persona para que el frontend pueda filtrar
     */
    public function allDocuments(ProviderType $providerType): JsonResponse
    {
        $documents = $providerType->documentTypes()
            ->withPivot(['is_required', 'applies_to_persona'])
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn($doc) => [
                'id'              => $doc->id,
                'code'            => $doc->code,
                'name'            => $doc->name,
                'description'     => $doc->description,
                'category'        => $doc->category,
                'requires_expiry' => $doc->requires_expiry,
                'expiry_months'   => $doc->expiry_months,
                'allows_multiple' => $doc->allows_multiple,
                'pivot'           => [
                    'is_required'        => $doc->pivot->is_required,
                    'applies_to_persona' => $doc->pivot->applies_to_persona ?? 'all',
                ],
            ]);

        return response()->json([
            'document_types' => $documents,
            'total'          => $documents->count(),
        ]);
    }

    /**
     * Documentos asignados con detalle completo (para gestión)
     * GET /provider-types/{id}/documents
     */
    public function documents(ProviderType $providerType): JsonResponse
    {
        $assigned = $providerType->documentTypes()
            ->withPivot(['is_required', 'sort_order', 'applies_to_existing', 'applies_to_persona'])
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn($doc) => [
                'id'                 => $doc->id,
                'code'               => $doc->code,
                'name'               => $doc->name,
                'description'        => $doc->description,
                'category'           => $doc->category,
                'requires_expiry'    => $doc->requires_expiry,
                'expiry_months'      => $doc->expiry_months,
                'allows_multiple'    => $doc->allows_multiple,
                'is_active'          => $doc->is_active,
                'is_required'        => (bool) $doc->pivot->is_required,
                'sort_order'         => $doc->pivot->sort_order ?? 0,
                'applies_to_persona' => $doc->pivot->applies_to_persona ?? 'all',
            ]);

        $assignedIds = $assigned->pluck('id');
        $available   = DocumentType::where('is_active', true)
            ->whereNotIn('id', $assignedIds)
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'category', 'requires_expiry', 'expiry_months', 'allows_multiple', 'description']);

        return response()->json([
            'assigned'  => $assigned,
            'available' => $available,
        ]);
    }

    /**
     * Asignar documento a tipo de proveedor
     * POST /provider-types/{id}/documents
     */
    public function assignDocument(Request $request, ProviderType $providerType): JsonResponse
    {
        $validated = $request->validate([
            'document_type_id'   => 'required|exists:document_types,id',
            'is_required'        => 'boolean',
            'applies_to_persona' => 'nullable|in:all,moral,fisica',
        ]);

        $exists = DB::table('document_type_provider_type')
            ->where('document_type_id', $validated['document_type_id'])
            ->where('provider_type_id', $providerType->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Este documento ya está asignado a este tipo de proveedor',
            ], 422);
        }

        DB::table('document_type_provider_type')->insert([
            'document_type_id'   => $validated['document_type_id'],
            'provider_type_id'   => $providerType->id,
            'is_required'        => $validated['is_required'] ?? true,
            'applies_to_persona' => $validated['applies_to_persona'] ?? 'all',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $doc = DocumentType::find($validated['document_type_id']);

        return response()->json([
            'message'  => "'{$doc->name}' asignado correctamente",
            'document' => [
                'id'                 => $doc->id,
                'code'               => $doc->code,
                'name'               => $doc->name,
                'category'           => $doc->category,
                'is_required'        => $validated['is_required'] ?? true,
                'applies_to_persona' => $validated['applies_to_persona'] ?? 'all',
            ],
        ], 201);
    }

    /**
     * Cambiar obligatorio/opcional de un documento asignado
     * PATCH /provider-types/{id}/documents/{docId}/toggle-required
     */
    public function toggleRequired(ProviderType $providerType, $docId): JsonResponse
    {
        $pivot = DB::table('document_type_provider_type')
            ->where('document_type_id', $docId)
            ->where('provider_type_id', $providerType->id)
            ->first();

        if (!$pivot) {
            return response()->json(['message' => 'Documento no encontrado en este tipo'], 404);
        }

        $newRequired = !$pivot->is_required;

        DB::table('document_type_provider_type')
            ->where('document_type_id', $docId)
            ->where('provider_type_id', $providerType->id)
            ->update(['is_required' => $newRequired, 'updated_at' => now()]);

        return response()->json([
            'message'     => $newRequired ? 'Marcado como obligatorio' : 'Marcado como opcional',
            'is_required' => $newRequired,
        ]);
    }

    /**
     * Actualizar a quién aplica el documento (persona física/moral/todos)
     * PATCH /provider-types/{id}/documents/{docId}/persona
     */
    public function updateDocumentPersona(Request $request, ProviderType $providerType, $docId): JsonResponse
    {
        $validated = $request->validate([
            'applies_to_persona' => 'required|in:all,moral,fisica',
        ]);

        $updated = DB::table('document_type_provider_type')
            ->where('document_type_id', $docId)
            ->where('provider_type_id', $providerType->id)
            ->update([
                'applies_to_persona' => $validated['applies_to_persona'],
                'updated_at'         => now(),
            ]);

        if (!$updated) {
            return response()->json(['message' => 'Documento no encontrado en este tipo'], 404);
        }

        $labels = [
            'all'   => 'Aplica a todos',
            'moral' => 'Solo Persona Moral',
            'fisica' => 'Solo Persona Física',
        ];

        return response()->json([
            'message'            => $labels[$validated['applies_to_persona']],
            'applies_to_persona' => $validated['applies_to_persona'],
        ]);
    }

    /**
     * Desasignar documento de tipo de proveedor
     * DELETE /provider-types/{id}/documents/{docId}
     */
    public function removeDocument(ProviderType $providerType, $docId): JsonResponse
    {
        DB::table('document_type_provider_type')
            ->where('document_type_id', $docId)
            ->where('provider_type_id', $providerType->id)
            ->delete();

        return response()->json(['message' => 'Documento removido correctamente']);
    }
}