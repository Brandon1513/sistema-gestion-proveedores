<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductsServicesSeeder extends Seeder
{
    public function run(): void
    {
        // ── Categorías de PRODUCTOS ───────────────────────────────────────────
        $productCategories = [
            ['name' => 'Materias Primas',        'type' => 'product', 'sort_order' => 1],
            ['name' => 'Material de Empaque',     'type' => 'product', 'sort_order' => 2],
            ['name' => 'Insumos Generales',       'type' => 'product', 'sort_order' => 3],
            ['name' => 'Sustancias Químicas',     'type' => 'product', 'sort_order' => 4],
            ['name' => 'Residuos y Desechos',     'type' => 'product', 'sort_order' => 5],
        ];

        // ── Categorías de SERVICIOS ───────────────────────────────────────────
        $serviceCategories = [
            ['name' => 'Laboratorio y Calibración',   'type' => 'service', 'sort_order' => 1],
            ['name' => 'Mantenimiento',                'type' => 'service', 'sort_order' => 2],
            ['name' => 'Control de Plagas',            'type' => 'service', 'sort_order' => 3],
            ['name' => 'Transporte y Logística',       'type' => 'service', 'sort_order' => 4],
            ['name' => 'Limpieza y Sanitización',      'type' => 'service', 'sort_order' => 5],
        ];

        $allCategories = array_merge($productCategories, $serviceCategories);
        foreach ($allCategories as $cat) {
            DB::table('product_service_categories')->insertOrIgnore([
                ...$cat,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── Productos iniciales ───────────────────────────────────────────────
        $products = [
            // Materias Primas (cat 1)
            ['category' => 'Materias Primas',    'name' => 'Avena en hojuela'],
            ['category' => 'Materias Primas',    'name' => 'Harina de trigo'],
            ['category' => 'Materias Primas',    'name' => 'Azúcar'],
            ['category' => 'Materias Primas',    'name' => 'Aceite vegetal'],
            ['category' => 'Materias Primas',    'name' => 'Sal'],
            // Material de Empaque (cat 2)
            ['category' => 'Material de Empaque','name' => 'Bolsas de polietileno'],
            ['category' => 'Material de Empaque','name' => 'Cajas de cartón'],
            ['category' => 'Material de Empaque','name' => 'Etiquetas adhesivas'],
            ['category' => 'Material de Empaque','name' => 'Film stretch'],
            // Insumos Generales (cat 3)
            ['category' => 'Insumos Generales',  'name' => 'Uniformes de trabajo'],
            ['category' => 'Insumos Generales',  'name' => 'Papelería y consumibles'],
            ['category' => 'Insumos Generales',  'name' => 'Artículos de limpieza'],
            ['category' => 'Insumos Generales',  'name' => 'Herramientas manuales'],
            // Sustancias Químicas (cat 4)
            ['category' => 'Sustancias Químicas','name' => 'Desinfectantes industriales'],
            ['category' => 'Sustancias Químicas','name' => 'Lubricantes alimenticios'],
            ['category' => 'Sustancias Químicas','name' => 'Sanitizantes'],
            // Residuos (cat 5)
            ['category' => 'Residuos y Desechos','name' => 'Recolección de residuos sólidos'],
            ['category' => 'Residuos y Desechos','name' => 'Recolección de residuos peligrosos'],
            ['category' => 'Residuos y Desechos','name' => 'Recolección de aceites usados'],
        ];

        // ── Servicios iniciales ───────────────────────────────────────────────
        $services = [
            ['category' => 'Laboratorio y Calibración', 'name' => 'Análisis microbiológico'],
            ['category' => 'Laboratorio y Calibración', 'name' => 'Análisis fisicoquímico'],
            ['category' => 'Laboratorio y Calibración', 'name' => 'Calibración de balanzas'],
            ['category' => 'Laboratorio y Calibración', 'name' => 'Calibración de termómetros'],
            ['category' => 'Mantenimiento',              'name' => 'Mantenimiento preventivo de maquinaria'],
            ['category' => 'Mantenimiento',              'name' => 'Mantenimiento correctivo'],
            ['category' => 'Mantenimiento',              'name' => 'Mantenimiento de equipos de refrigeración'],
            ['category' => 'Control de Plagas',          'name' => 'Fumigación general'],
            ['category' => 'Control de Plagas',          'name' => 'Control de roedores'],
            ['category' => 'Control de Plagas',          'name' => 'Control de insectos voladores'],
            ['category' => 'Transporte y Logística',     'name' => 'Transporte de mercancía refrigerada'],
            ['category' => 'Transporte y Logística',     'name' => 'Transporte de carga general'],
            ['category' => 'Limpieza y Sanitización',    'name' => 'Limpieza profunda de instalaciones'],
            ['category' => 'Limpieza y Sanitización',    'name' => 'Sanitización de áreas de producción'],
        ];

        // Obtener IDs de categorías
        $categoryIds = DB::table('product_service_categories')
            ->pluck('id', 'name');

        // Insertar productos
        foreach ($products as $p) {
            if (!isset($categoryIds[$p['category']])) continue;
            DB::table('products_services')->insertOrIgnore([
                'category_id' => $categoryIds[$p['category']],
                'type'        => 'product',
                'name'        => $p['name'],
                'is_active'   => true,
                'sort_order'  => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // Insertar servicios
        foreach ($services as $s) {
            if (!isset($categoryIds[$s['category']])) continue;
            DB::table('products_services')->insertOrIgnore([
                'category_id' => $categoryIds[$s['category']],
                'type'        => 'service',
                'name'        => $s['name'],
                'is_active'   => true,
                'sort_order'  => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        $total = count($products) + count($services);
        $this->command->info("✅ Catálogo creado: {$total} productos/servicios en " . count($allCategories) . " categorías");
        
        DB::table('product_service_categories')->insertOrIgnore([
            ['name' => 'Sin categorizar', 'type' => 'product', 'sort_order' => 999, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sin categorizar', 'type' => 'service', 'sort_order' => 999, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }



}