<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductService;
use App\Models\ProductServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogImportController extends Controller
{
    /**
     * POST /api/catalog/import
     * Importa productos/servicios desde un archivo CSV o Excel (convertido a CSV).
     * Columnas esperadas: nombre, tipo (producto|servicio)
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ], [
            'file.required' => 'El archivo es requerido',
            'file.mimes'    => 'Solo se aceptan archivos CSV o Excel',
            'file.max'      => 'El archivo no debe superar 5MB',
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        // Leer contenido según extensión
        if (in_array($ext, ['xlsx', 'xls'])) {
            $rows = $this->readExcel($file->getRealPath());
        } else {
            $rows = $this->readCsv($file->getRealPath());
        }

        if (empty($rows)) {
            return response()->json(['message' => 'El archivo está vacío o no tiene el formato correcto'], 422);
        }

        // Obtener/crear categorías "Sin categorizar"
        $uncategorizedProduct = ProductServiceCategory::firstOrCreate(
            ['name' => 'Sin categorizar', 'type' => 'product'],
            ['sort_order' => 999, 'is_active' => true]
        );
        $uncategorizedService = ProductServiceCategory::firstOrCreate(
            ['name' => 'Sin categorizar', 'type' => 'service'],
            ['sort_order' => 999, 'is_active' => true]
        );

        $imported  = 0;
        $skipped   = 0;
        $errors    = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $rowNum => $row) {
                $name = trim($row['nombre'] ?? $row['name'] ?? $row['Name'] ?? $row['Nombre'] ?? '');
                $type = strtolower(trim($row['tipo'] ?? $row['type'] ?? $row['Type'] ?? $row['Tipo'] ?? ''));

                // Normalizar tipo
                if (in_array($type, ['producto', 'product', 'p'])) {
                    $type = 'product';
                } elseif (in_array($type, ['servicio', 'service', 's'])) {
                    $type = 'service';
                } else {
                    $errors[] = "Fila {$rowNum}: tipo '{$type}' no válido (use 'producto' o 'servicio')";
                    $skipped++;
                    continue;
                }

                if (empty($name)) {
                    $errors[] = "Fila {$rowNum}: nombre vacío";
                    $skipped++;
                    continue;
                }

                if (strlen($name) > 150) {
                    $errors[] = "Fila {$rowNum}: nombre demasiado largo (máx 150 caracteres)";
                    $skipped++;
                    continue;
                }

                $categoryId = $type === 'product'
                    ? $uncategorizedProduct->id
                    : $uncategorizedService->id;

                // Evitar duplicados por nombre+tipo
                $exists = ProductService::where('name', $name)->where('type', $type)->exists();
                if ($exists) {
                    $skipped++;
                    continue;
                }

                ProductService::create([
                    'name'        => $name,
                    'type'        => $type,
                    'category_id' => $categoryId,
                    'is_active'   => true,
                    'sort_order'  => 0,
                ]);

                $imported++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al importar: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message'  => "Importación completada: {$imported} ítem(s) importado(s), {$skipped} omitido(s).",
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => array_slice($errors, 0, 10), // máx 10 errores visibles
        ]);
    }

    // ─── Leer CSV ─────────────────────────────────────────────────────────────
    private function readCsv(string $path): array
    {
        $rows    = [];
        $handle  = fopen($path, 'r');
        $headers = null;

        while (($line = fgetcsv($handle, 1000, ',')) !== false) {
            // Intentar también con punto y coma (Excel español)
            if (count($line) === 1) {
                $line = str_getcsv($line[0], ';');
            }

            if ($headers === null) {
                // Normalizar headers: quitar BOM, espacios, lowercase
                $headers = array_map(fn($h) => strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h))), $line);
                continue;
            }

            if (count($line) < 2) continue;

            $row = array_combine($headers, array_pad($line, count($headers), ''));
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    // ─── Leer Excel con PhpSpreadsheet ───────────────────────────────────────
    private function readExcel(string $path): array
    {
        // Verificar si PhpSpreadsheet está disponible
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new \Exception('Para importar Excel instala: composer require phpoffice/phpspreadsheet');
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = [];
        $headers     = null;

        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getFormattedValue();
            }

            if ($headers === null) {
                $headers = array_map(fn($h) => strtolower(trim($h)), $cells);
                continue;
            }

            if (empty(array_filter($cells))) continue;

            $rows[] = array_combine($headers, array_pad($cells, count($headers), ''));
        }

        return $rows;
    }
}