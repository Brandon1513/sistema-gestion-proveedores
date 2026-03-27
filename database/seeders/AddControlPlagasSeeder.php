<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * AddControlPlagasSeeder
 *
 * Agrega el tipo de proveedor "Control de Plagas" y sus documentos técnicos
 * SIN tocar ningún dato existente en la base de datos.
 *
 * También actualiza:
 *  - Vigencias fiscales: descripción de 2 meses → 3 meses
 *  - allows_multiple en fichas técnicas y cartas garantía existentes
 *
 * Uso:
 *   php artisan db:seed --class=AddControlPlagasSeeder
 */
class AddControlPlagasSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 1. Actualizar vigencias fiscales (2 meses → 3 meses) ─────────────
        DB::table('document_types')
            ->where('code', 'constancia_fiscal')
            ->update([
                'description' => 'No mayor a 3 meses',
                'updated_at'  => now(),
            ]);

        DB::table('document_types')
            ->where('code', 'opinion_cumplimiento')
            ->update([
                'description' => 'Opinión positiva del SAT no mayor a 3 meses',
                'updated_at'  => now(),
            ]);

        DB::table('document_types')
            ->where('code', 'comprobante_domicilio')
            ->update([
                'description' => 'Recibo de luz, agua, teléfono, etc. No mayor a 3 meses',
                'updated_at'  => now(),
            ]);

        $this->command->info('✅ Vigencias fiscales actualizadas a 3 meses');

        // ─── 2. Activar allows_multiple en documentos que lo requieren ─────────
        // fichas_tecnicas (MP y ME)
        DB::table('document_types')
            ->where('code', 'fichas_tecnicas')
            ->update(['allows_multiple' => true, 'updated_at' => now()]);

        // carta_garantia (MP y ME)
        DB::table('document_types')
            ->where('code', 'carta_garantia')
            ->update(['allows_multiple' => true, 'updated_at' => now()]);

        // fichas_tecnicas_quimicas (Sustancias Químicas)
        DB::table('document_types')
            ->where('code', 'fichas_tecnicas_quimicas')
            ->update(['allows_multiple' => true, 'updated_at' => now()]);

        // hojas_seguridad (Sustancias Químicas)
        DB::table('document_types')
            ->where('code', 'hojas_seguridad')
            ->update(['allows_multiple' => true, 'updated_at' => now()]);

        // fichas_tecnicas_insumos — si existe (Insumos Generales)
        DB::table('document_types')
            ->where('code', 'fichas_tecnicas_insumos')
            ->update(['allows_multiple' => true, 'updated_at' => now()]);

        $this->command->info('✅ allows_multiple activado en fichas técnicas, cartas garantía y hojas de seguridad');

        // ─── 3. Agregar ficha técnica para Insumos si no existe ───────────────
        $existsInsumosDoc = DB::table('document_types')
            ->where('code', 'fichas_tecnicas_insumos')
            ->exists();

        if (!$existsInsumosDoc) {
            $insumosFichaId = DB::table('document_types')->insertGetId([
                'code'               => 'fichas_tecnicas_insumos',
                'name'               => 'Fichas Técnicas',
                'description'        => 'Cuando aplique, de cada producto suministrado.',
                'category'           => 'technical',
                'requires_expiry'    => false,
                'expiry_alert_days'  => null,
                'is_required'        => false,
                'allows_multiple'    => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 10,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            // Relacionar con tipo "insumos" en el pivot como recomendado
            $insumosType = DB::table('provider_types')->where('code', 'insumos')->first();
            if ($insumosType) {
                DB::table('document_type_provider_type')->insert([
                    'document_type_id' => $insumosFichaId,
                    'provider_type_id' => $insumosType->id,
                    'is_required'      => false,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
                $this->command->info('✅ Fichas Técnicas (recomendada) agregada a Insumos Generales');
            }
        } else {
            $this->command->info('ℹ️  Fichas Técnicas para Insumos ya existe — sin cambios');
        }

        // ─── 4. Verificar que Control de Plagas no exista ya ──────────────────
        $exists = DB::table('provider_types')->where('code', 'control_plagas')->exists();

        if ($exists) {
            $this->command->warn('⚠️  El tipo "Control de Plagas" ya existe — abortando para evitar duplicados.');
            return;
        }

        // ─── 5. Insertar tipo de proveedor ────────────────────────────────────
        $providerTypeId = DB::table('provider_types')->insertGetId([
            'code'        => 'control_plagas',
            'name'        => 'Control de Plagas',
            'form_code'   => 'F-COM-12',
            'description' => 'Empresas de control y fumigación de plagas',
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->command->info('✅ Tipo de proveedor "Control de Plagas" creado');

        // ─── 6. Documentos técnicos específicos de Control de Plagas ──────────
        $technicalDocs = [
            [
                'code'               => 'licencia_sanitaria',
                'name'               => 'Licencia o Permiso Sanitario',
                'description'        => 'Licencia o permiso sanitario vigente',
                'category'           => 'legal',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 60,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 10,
            ],
            [
                'code'               => 'hojas_seguridad_plagas',
                'name'               => 'Hojas de Seguridad',
                'description'        => 'De cada sustancia aplicada.',
                'category'           => 'technical',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 90,
                'is_required'        => true,
                'allows_multiple'    => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 10,
            ],
            [
                'code'               => 'fichas_tecnicas_plagas',
                'name'               => 'Fichas Técnicas',
                'description'        => 'De cada sustancia aplicada.',
                'category'           => 'technical',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 90,
                'is_required'        => true,
                'allows_multiple'    => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 10,
            ],
            [
                'code'               => 'registro_sanitario',
                'name'               => 'Registro Sanitario',
                'description'        => 'De los productos utilizados',
                'category'           => 'legal',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 60,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 10,
            ],
            [
                'code'               => 'competencia_personal_plagas',
                'name'               => 'Competencia del Personal Técnico',
                'description'        => 'Según aplique',
                'category'           => 'technical',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 90,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 5,
            ],
        ];

        $technicalDocIds = [];
        foreach ($technicalDocs as $doc) {
            // Verificar que no exista ya (por si se corrió parcialmente antes)
            $existingId = DB::table('document_types')->where('code', $doc['code'])->value('id');

            if ($existingId) {
                $technicalDocIds[$doc['code']] = $existingId;
                $this->command->warn("ℹ️  Documento '{$doc['code']}' ya existe — reutilizando");
            } else {
                $id = DB::table('document_types')->insertGetId(array_merge($doc, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
                $technicalDocIds[$doc['code']] = $id;
            }
        }

        $this->command->info('✅ Documentos técnicos de Control de Plagas creados');

        // ─── 7. Obtener IDs de documentos fiscales (comunes para todos) ───────
        $fiscalCodes = [
            'constancia_fiscal',
            'opinion_cumplimiento',
            'comprobante_domicilio',
            'caratula_cuenta',
            'identificacion_representante',
            'acta_constitutiva',
        ];

        $fiscalDocIds = DB::table('document_types')
            ->whereIn('code', $fiscalCodes)
            ->pluck('id', 'code');

        // ─── 8. Insertar relaciones en el pivot ───────────────────────────────
        $pivotRows = [];

        // Fiscales — obligatorios
        foreach ($fiscalDocIds as $code => $docId) {
            $pivotRows[] = [
                'document_type_id' => $docId,
                'provider_type_id' => $providerTypeId,
                'is_required'      => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        }

        // Técnicos — obligatorios
        foreach ($technicalDocIds as $code => $docId) {
            $pivotRows[] = [
                'document_type_id' => $docId,
                'provider_type_id' => $providerTypeId,
                'is_required'      => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        }

        DB::table('document_type_provider_type')->insert($pivotRows);

        $this->command->info('✅ Relaciones pivot insertadas (' . count($pivotRows) . ' documentos asignados)');
        $this->command->info('');
        $this->command->info('🎉 Control de Plagas configurado correctamente.');
        $this->command->info('   Tipo ID: ' . $providerTypeId);
        $this->command->info('   Documentos: ' . count($fiscalDocIds) . ' fiscales + ' . count($technicalDocIds) . ' técnicos');
    }
}