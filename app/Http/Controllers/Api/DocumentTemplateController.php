<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentTemplateController extends Controller
{
    // ─── Listar templates por tipo de documento ───────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = DocumentTemplate::with('documentType:id,name,code')
            ->orderBy('template_name')
            ->orderBy('product_name');

        if ($request->filled('document_type_id')) {
            $query->where('document_type_id', $request->document_type_id);
        }

        return response()->json(['templates' => $query->get()]);
    }

    // ─── Subir template con múltiples productos ───────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'template_name'    => 'required|string|max:100',
            'product_names'    => 'required|array|min:1',
            'product_names.*'  => 'required|string|max:255',
            'file'             => 'required|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $file         = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $fileName     = 'template_' . Str::slug($request->template_name) . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path         = $file->storeAs('document_templates', $fileName, 'documents');

        $created = 0;
        $updated = 0;

        foreach ($request->product_names as $productName) {
            $existing = DocumentTemplate::where('document_type_id', $request->document_type_id)
                ->where('product_name', $productName)
                ->first();

            if ($existing) {
                // Eliminar archivo anterior solo si es diferente al nuevo
                if ($existing->file_path && $existing->file_path !== $path) {
                    // No eliminar si otros productos comparten el mismo archivo
                    $sharedCount = DocumentTemplate::where('file_path', $existing->file_path)
                        ->where('id', '!=', $existing->id)
                        ->count();
                    if ($sharedCount === 0) {
                        Storage::disk('documents')->delete($existing->file_path);
                    }
                }
                $existing->update([
                    'template_name'    => $request->template_name,
                    'file_path'        => $path,
                    'original_filename'=> $originalName,
                    'is_active'        => true,
                ]);
                $updated++;
            } else {
                DocumentTemplate::create([
                    'document_type_id' => $request->document_type_id,
                    'template_name'    => $request->template_name,
                    'product_name'     => $productName,
                    'file_path'        => $path,
                    'original_filename'=> $originalName,
                    'is_active'        => true,
                ]);
                $created++;
            }
        }

        $message = [];
        if ($created > 0) $message[] = "{$created} producto" . ($created > 1 ? 's' : '') . " agregado" . ($created > 1 ? 's' : '');
        if ($updated > 0) $message[] = "{$updated} producto" . ($updated > 1 ? 's' : '') . " actualizado" . ($updated > 1 ? 's' : '');

        return response()->json([
            'message'  => implode(', ', $message) . ' correctamente',
            'created'  => $created,
            'updated'  => $updated,
        ], 201);
    }

    // ─── Eliminar template ────────────────────────────────────────────────────
    public function destroy($id): JsonResponse
    {
        $template = DocumentTemplate::findOrFail($id);

        // Solo eliminar el archivo si ningún otro template lo usa
        if ($template->file_path) {
            $sharedCount = DocumentTemplate::where('file_path', $template->file_path)
                ->where('id', '!=', $template->id)
                ->count();
            if ($sharedCount === 0) {
                Storage::disk('documents')->delete($template->file_path);
            }
        }
        $template->delete();

        return response()->json(['message' => 'Formato eliminado correctamente']);
    }

    // ─── Descargar template ───────────────────────────────────────────────────
    public function download($id)
    {
        $template = DocumentTemplate::findOrFail($id);

        if (!$template->is_active) {
            return response()->json(['message' => 'Template no disponible'], 404);
        }
        if (!Storage::disk('documents')->exists($template->file_path)) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        return Storage::disk('documents')->download($template->file_path, $template->original_filename);
    }

    // ─── Obtener template por producto (para el proveedor al subir) ───────────
    public function getByProduct(Request $request): JsonResponse
    {
        $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'product_name'     => 'required|string',
        ]);

        $template = DocumentTemplate::where('document_type_id', $request->document_type_id)
            ->where('product_name', $request->product_name)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            return response()->json(['template' => null]);
        }

        return response()->json([
            'template' => [
                'id'            => $template->id,
                'template_name' => $template->template_name,
                'product_name'  => $template->product_name,
                'filename'      => $template->original_filename,
            ],
        ]);
    }

    // ─── NUEVO: Adjuntar plantillas genéricas (sin producto) — hasta varias por documento ──
    public function storeGeneric(Request $request): JsonResponse
    {
        $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'files'             => 'required|array|min:1|max:5',
            'files.*'           => 'file|mimes:pdf,doc,docx,xls,xlsx|max:10240', 
        ]);

        $created = [];
        foreach ($request->file('files') as $file) {
            $originalName = $file->getClientOriginalName();
            $baseName     = pathinfo($originalName, PATHINFO_FILENAME);
            $fileName     = 'template_' . Str::slug($baseName) . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path         = $file->storeAs('document_templates', $fileName, 'documents');

            $created[] = DocumentTemplate::create([
                'document_type_id'  => $request->document_type_id,
                'template_name'     => $baseName,
                'product_name'      => 'general', // ✅ mismo centinela que ya usa el flujo sin selector de producto
                'file_path'         => $path,
                'original_filename' => $originalName,
                'is_active'         => true,
            ]);
        }

        return response()->json([
            'message'   => count($created) . ' plantilla(s) adjuntada(s) correctamente',
            'templates' => $created,
        ], 201);
    }

    // ─── NUEVO: Listar TODAS las plantillas genéricas de un tipo de documento ──
    public function getGenericTemplates($documentTypeId): JsonResponse
    {
        $templates = DocumentTemplate::where('document_type_id', $documentTypeId)
            ->where('product_name', 'general')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'template_name', 'original_filename']);

        return response()->json(['templates' => $templates]);
    }

    // ─── Obtener productos del catálogo ───────────────────────────────────────
    public function getCatalogProducts(): JsonResponse
    {
        $products = \App\Models\ProductService::where('type', 'product')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['products' => $products]);
    }
}