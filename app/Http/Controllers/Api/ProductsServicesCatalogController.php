<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductService;
use App\Models\ProductServiceCategory;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductsServicesCatalogController extends Controller
{
    // ── Catálogo completo agrupado por categoría ──────────────────────────────

    /**
     * GET /api/catalog
     * Devuelve el catálogo completo agrupado por tipo y categoría.
     * Incluye qué ítems tiene seleccionados el proveedor (si se pasa provider_id).
     */
    public function index(Request $request): JsonResponse
    {
        $providerId = $request->provider_id;

        // IDs seleccionados por el proveedor
        $selectedIds = [];
        if ($providerId) {
            $provider = Provider::find($providerId);
            if ($provider) {
                $selectedIds = $provider->productsServices()->pluck('products_services.id')->toArray();
            }
        }

        $categoriesWithItems = ProductServiceCategory::active()
            ->with(['items' => fn($q) => $q->active()])
            ->get()
            ->map(fn($cat) => [
                'id'          => $cat->id,
                'name'        => $cat->name,
                'type'        => $cat->type,
                'description' => $cat->description,
                'items'       => $cat->items->map(fn($item) => [
                    'id'          => $item->id,
                    'name'        => $item->name,
                    'description' => $item->description,
                    'type'        => $item->type,
                    'selected'    => in_array($item->id, $selectedIds),
                ]),
            ]);

        return response()->json([
            'products' => $categoriesWithItems->where('type', 'product')->values(),
            'services' => $categoriesWithItems->where('type', 'service')->values(),
        ]);
    }

    // ── Gestión de categorías (solo Compras/Admin) ────────────────────────────

    public function getCategories(): JsonResponse
    {
        $categories = ProductServiceCategory::orderBy('type')->orderBy('sort_order')->orderBy('name')->get();
        return response()->json(['categories' => $categories]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'type'        => 'required|in:product,service',
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'nullable|integer',
        ]);

        $category = ProductServiceCategory::create([...$validated, 'is_active' => true]);
        return response()->json(['message' => 'Categoría creada', 'category' => $category], 201);
    }

    public function updateCategory(Request $request, $id): JsonResponse
    {
        $category  = ProductServiceCategory::findOrFail($id);
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
            'sort_order'  => 'nullable|integer',
        ]);
        $category->update($validated);
        return response()->json(['message' => 'Categoría actualizada', 'category' => $category]);
    }

    // ── Gestión de ítems (solo Compras/Admin) ─────────────────────────────────

    public function getItems(Request $request): JsonResponse
    {
        $query = ProductService::with('category')->orderBy('type')->orderBy('name');

        if ($request->filled('type'))        $query->where('type', $request->type);
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        if ($request->filled('search'))      $query->where('name', 'like', "%{$request->search}%");

        $items = $query->get()->map(fn($item) => [
            'id'          => $item->id,
            'name'        => $item->name,
            'type'        => $item->type,
            'description' => $item->description,
            'is_active'   => $item->is_active,
            'category'    => ['id' => $item->category->id, 'name' => $item->category->name],
        ]);

        return response()->json(['items' => $items]);
    }

    public function storeItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:150',
            'type'        => 'required|in:product,service',
            'category_id' => 'required|exists:product_service_categories,id',
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'nullable|integer',
        ]);

        $item = ProductService::create([...$validated, 'is_active' => true]);
        $item->load('category');

        return response()->json(['message' => 'Ítem creado correctamente', 'item' => [
            'id'       => $item->id,
            'name'     => $item->name,
            'type'     => $item->type,
            'category' => ['id' => $item->category->id, 'name' => $item->category->name],
        ]], 201);
    }

    public function updateItem(Request $request, $id): JsonResponse
    {
        $item      = ProductService::findOrFail($id);
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:150',
            'category_id' => 'sometimes|exists:product_service_categories,id',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
            'sort_order'  => 'nullable|integer',
        ]);
        $item->update($validated);
        $item->load('category');
        return response()->json(['message' => 'Ítem actualizado', 'item' => $item]);
    }

    public function destroyItem($id): JsonResponse
    {
        $item = ProductService::findOrFail($id);
        // Soft delete: desactivar en lugar de eliminar para preservar historial
        $item->update(['is_active' => false]);
        return response()->json(['message' => 'Ítem desactivado correctamente']);
    }

    // ── Selección del proveedor ───────────────────────────────────────────────

    /**
     * GET /api/provider/products-services
     * Catálogo con los ítems seleccionados del proveedor autenticado.
     */
    public function providerGetCatalog(Request $request): JsonResponse
    {
        $user     = $request->user();
        $provider = Provider::where('email', $user->email)->first();
        if (!$provider) return response()->json(['message' => 'Proveedor no encontrado'], 404);

        $selectedIds = $provider->productsServices()->pluck('products_services.id')->toArray();

        $categories = ProductServiceCategory::active()
            ->with(['items' => fn($q) => $q->active()])
            ->get()
            ->map(fn($cat) => [
                'id'    => $cat->id,
                'name'  => $cat->name,
                'type'  => $cat->type,
                'items' => $cat->items->map(fn($item) => [
                    'id'       => $item->id,
                    'name'     => $item->name,
                    'type'     => $item->type,
                    'selected' => in_array($item->id, $selectedIds),
                ]),
            ]);

        return response()->json([
            'products'     => $categories->where('type', 'product')->values(),
            'services'     => $categories->where('type', 'service')->values(),
            'selected_ids' => $selectedIds,
        ]);
    }

    /**
     * PUT /api/provider/products-services
     * El proveedor actualiza su selección completa.
     */
    public function providerUpdateSelection(Request $request): JsonResponse
    {
        $user     = $request->user();
        $provider = Provider::where('email', $user->email)->first();
        if (!$provider) return response()->json(['message' => 'Proveedor no encontrado'], 404);

        $request->validate([
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products_services,id',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'exists:products_services,id',
        ]);

        $allIds = array_merge(
            $request->product_ids ?? [],
            $request->service_ids ?? []
        );

        // Sync — reemplaza la selección completa
        $provider->productsServices()->sync($allIds);

        return response()->json([
            'message'      => 'Selección actualizada correctamente',
            'total'        => count($allIds),
            'products'     => count($request->product_ids ?? []),
            'services'     => count($request->service_ids ?? []),
        ]);
    }

    /**
     * GET /api/providers/{id}/products-services
     * Ver selección de un proveedor específico (Compras/Admin).
     */
    public function providerItems($providerId): JsonResponse
    {
        $provider = Provider::with(['productsServices.category'])->findOrFail($providerId);

        $items = $provider->productsServices->map(fn($item) => [
            'id'       => $item->id,
            'name'     => $item->name,
            'type'     => $item->type,
            'category' => $item->category->name,
        ]);

        return response()->json([
            'provider'  => ['id' => $provider->id, 'name' => $provider->business_name],
            'products'  => $items->where('type', 'product')->values(),
            'services'  => $items->where('type', 'service')->values(),
        ]);
    }
    /**
     * PUT /api/providers/{id}/products-services-sync
     * Compras/Admin sincroniza la selección de un proveedor.
     */
    public function syncProviderItems(Request $request, $providerId): JsonResponse
    {
        $provider = Provider::findOrFail($providerId);
 
        $request->validate([
            'product_ids'   => 'nullable|array',
            'product_ids.*' => 'exists:products_services,id',
            'service_ids'   => 'nullable|array',
            'service_ids.*' => 'exists:products_services,id',
        ]);
 
        $allIds = array_merge(
            $request->product_ids ?? [],
            $request->service_ids ?? []
        );
 
        $provider->productsServices()->sync($allIds);
 
        return response()->json([
            'message'  => 'Selección actualizada correctamente',
            'total'    => count($allIds),
            'products' => count($request->product_ids ?? []),
            'services' => count($request->service_ids ?? []),
        ]);
    }
}