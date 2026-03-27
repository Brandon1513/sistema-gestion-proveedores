<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DocumentTypeSeeder — versión actualizada
 *
 * Cambios respecto a la versión anterior:
 *  - Vigencias fiscales: 2 meses → 3 meses (constancia + opinión + comprobante)
 *  - Nuevo tipo de proveedor: Control de Plagas (code: control_plagas)
 *  - allows_multiple = true en fichas técnicas y cartas garantía
 *  - product_name disponible en: MP y ME, Químicos, Insumos, Control de Plagas
 *  - Carátula Estado de Cuenta se conserva
 *  - Documentación fiscal marcada is_required = true en la tabla pivot para todos
 */
class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Documentos fiscales (comunes para todos los tipos) ───────────────
        $fiscalDocuments = [
            [
                'code'               => 'constancia_fiscal',
                'name'               => 'Constancia de Situación Fiscal',
                'description'        => 'No mayor a 3 meses',          // ← actualizado
                'category'           => 'fiscal',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 15,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 5,
            ],
            [
                'code'               => 'opinion_cumplimiento',
                'name'               => 'Opinión del Cumplimiento Fiscal (SAT)',
                'description'        => 'Opinión positiva del SAT no mayor a 3 meses', // ← actualizado
                'category'           => 'fiscal',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 15,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 5,
            ],
            [
                'code'               => 'comprobante_domicilio',
                'name'               => 'Comprobante de Domicilio',
                'description'        => 'Recibo de luz, agua, teléfono, etc. No mayor a 3 meses', // ← actualizado
                'category'           => 'fiscal',
                'requires_expiry'    => false,
                'expiry_alert_days'  => null,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf', 'jpg', 'png']),
                'max_file_size_mb'   => 5,
            ],
            [
                'code'               => 'caratula_cuenta',
                'name'               => 'Carátula Estado de Cuenta',
                'description'        => 'Estado de cuenta con cuenta CLABE visible',
                'category'           => 'fiscal',
                'requires_expiry'    => false,
                'expiry_alert_days'  => null,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf', 'jpg', 'png']),
                'max_file_size_mb'   => 5,
            ],
            [
                'code'               => 'identificacion_representante',
                'name'               => 'Identificación del Representante Legal',
                'description'        => 'INE o pasaporte vigente',
                'category'           => 'legal',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 30,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf', 'jpg', 'png']),
                'max_file_size_mb'   => 5,
            ],
            [
                'code'               => 'acta_constitutiva',
                'name'               => 'Acta Constitutiva',
                'description'        => 'Acta constitutiva de la empresa',
                'category'           => 'legal',
                'requires_expiry'    => false,
                'expiry_alert_days'  => null,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 10,
            ],
        ];

        // ─── Documentos técnicos — MP y ME ───────────────────────────────────
        $mpMeDocuments = [
            [
                'code'               => 'cedula_rfc',
                'name'               => 'Cédula del RFC',
                'description'        => 'Cédula de identificación fiscal',
                'category'           => 'fiscal',
                'requires_expiry'    => false,
                'expiry_alert_days'  => null,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 5,
            ],
            [
                'code'               => 'certificaciones_planta',
                'name'               => 'Certificaciones de Planta',
                'description'        => 'ISO, FSSC, HACCP, SQF, etc.',
                'category'           => 'quality',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 60,
                'is_required'        => false,  // opcional / recomendada
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 10,
            ],
            [
                'code'               => 'carta_garantia',
                'name'               => 'Carta Garantía',
                // allows_multiple = true: una por producto
                'description'        => 'Machote enviado por DASAVENA, según proveduría. Una por cada producto.',
                'category'           => 'quality',
                'requires_expiry'    => false,
                'expiry_alert_days'  => null,
                'is_required'        => true,
                'allows_multiple'    => true,   // ← múltiples (por producto)
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 5,
            ],
            [
                'code'               => 'fichas_tecnicas',
                'name'               => 'Fichas Técnicas',
                // allows_multiple = true: una por MP o ME suministrado
                'description'        => 'De cada materia prima o material de empaque suministrado.',
                'category'           => 'technical',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 90,
                'is_required'        => true,
                'allows_multiple'    => true,   // ← múltiples (por producto)
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 10,
            ],
            [
                'code'               => 'carta_revision_metales',
                'name'               => 'Carta de Revisión de Metales',
                'description'        => 'Detector de metales o trampas magnéticas',
                'category'           => 'quality',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 90,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 5,
            ],
            [
                'code'               => 'cartas_declaracion',
                'name'               => 'Cartas de Declaración',
                'description'        => 'Alérgenos, origen, ausencia de OGM, ingredientes',
                'category'           => 'quality',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 90,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 5,
            ],
            [
                'code'               => 'informacion_nutrimental',
                'name'               => 'Información Nutrimental',
                'description'        => 'Solo para materias primas',
                'category'           => 'technical',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 90,
                'is_required'        => false,  // opcional / recomendada
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 5,
            ],
            [
                'code'               => 'certificado_calidad',
                'name'               => 'Ejemplo de Certificado de Calidad',
                'description'        => 'Modelo de certificado de calidad que emiten',
                'category'           => 'quality',
                'requires_expiry'    => false,
                'expiry_alert_days'  => null,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 5,
            ],
        ];

        // ─── Documentos técnicos — Residuos ──────────────────────────────────
        $residuosDocuments = [
            [
                'code'               => 'permisos_semadet',
                'name'               => 'Permisos SEMADET/SEMARNAT',
                'description'        => 'Permisos ambientales vigentes',
                'category'           => 'legal',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 60,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 10,
            ],
            [
                'code'               => 'manifiestos',
                'name'               => 'Manifiestos',
                'description'        => 'Manifiestos de recolección',
                'category'           => 'technical',
                'requires_expiry'    => false,
                'expiry_alert_days'  => null,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 5,
            ],
        ];

        // ─── Documentos técnicos — Laboratorios ──────────────────────────────
        $laboratoriosDocuments = [
            [
                'code'               => 'certificados_acreditacion',
                'name'               => 'Certificados de Acreditación',
                'description'        => 'Certificados de acreditación del laboratorio',
                'category'           => 'quality',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 60,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 10,
            ],
            [
                'code'               => 'alcance_acreditacion',
                'name'               => 'Alcance de Acreditación',
                'description'        => 'Documento que indique métodos de ensayo',
                'category'           => 'technical',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 60,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 5,
            ],
            [
                'code'               => 'competencia_personal',
                'name'               => 'Competencia del Personal Técnico',
                'description'        => 'Certificados de competencia del personal',
                'category'           => 'technical',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 90,
                'is_required'        => true,
                'allows_multiple'    => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 5,
            ],
        ];

        // ─── Documentos técnicos — Sustancias Químicas ───────────────────────
        $sustanciasDocuments = [
            [
                'code'               => 'fichas_tecnicas_quimicas',
                'name'               => 'Fichas Técnicas',
                'description'        => 'De cada producto suministrado.',
                'category'           => 'technical',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 90,
                'is_required'        => true,
                'allows_multiple'    => true,   // ← múltiples (por producto)
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 10,
            ],
            [
                'code'               => 'hojas_seguridad',
                'name'               => 'Hojas de Seguridad',
                'description'        => 'MSDS/SDS de cada producto suministrado.',
                'category'           => 'technical',
                'requires_expiry'    => true,
                'expiry_alert_days'  => 90,
                'is_required'        => true,
                'allows_multiple'    => true,   // ← múltiples (por producto)
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 10,
            ],
        ];

        // ─── Documentos técnicos — Insumos Generales ─────────────────────────
        // Solo tiene documentación fiscal + fichas técnicas opcionales (recomendadas)
        $insumosDocuments = [
            [
                'code'               => 'fichas_tecnicas_insumos',
                'name'               => 'Fichas Técnicas',
                'description'        => 'Cuando aplique, de cada producto suministrado.',
                'category'           => 'technical',
                'requires_expiry'    => false,
                'expiry_alert_days'  => null,
                'is_required'        => false,  // opcional / recomendada
                'allows_multiple'    => true,   // ← múltiples (por producto)
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb'   => 10,
            ],
        ];

        // ─── Documentos técnicos — Control de Plagas (NUEVO) ─────────────────
        $controlPlagasDocuments = [
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
                'allows_multiple'    => true,   // ← múltiples (por sustancia)
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
                'allows_multiple'    => true,   // ← múltiples (por sustancia)
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

        // ─── Insertar todos los documentos ────────────────────────────────────
        $allDocuments = array_merge(
            $fiscalDocuments,
            $mpMeDocuments,
            $residuosDocuments,
            $laboratoriosDocuments,
            $sustanciasDocuments,
            $insumosDocuments,
            $controlPlagasDocuments,
        );

        $documentIds = [];
        foreach ($allDocuments as $document) {
            $id = DB::table('document_types')->insertGetId(array_merge($document, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $documentIds[$document['code']] = $id;
        }

        // ─── Insertar nuevo tipo de proveedor: Control de Plagas ─────────────
        DB::table('provider_types')->insert([
            'code'        => 'control_plagas',
            'name'        => 'Control de Plagas',
            'form_code'   => 'F-COM-12',
            'description' => 'Empresas de control y fumigación de plagas',
            'is_active'   => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // ─── Relaciones documentos ↔ tipos de proveedor ───────────────────────
        // Nota: is_required en el pivot refleja si ES OBLIGATORIO para ese tipo.
        // Los docs con is_required=false en document_types son "recomendados"
        // pero se incluyen en el pivot para que el portal los muestre en la
        // sección "Documentación Recomendada".
        $fiscalCodes = array_column($fiscalDocuments, 'code');

        $relations = [
            'mp_me' => [
                'required' => array_merge($fiscalCodes, [
                    'cedula_rfc', 'carta_garantia', 'fichas_tecnicas',
                    'carta_revision_metales', 'cartas_declaracion', 'certificado_calidad',
                ]),
                'recommended' => ['certificaciones_planta', 'informacion_nutrimental'],
            ],
            'residuos' => [
                'required'    => array_merge($fiscalCodes, ['permisos_semadet', 'manifiestos']),
                'recommended' => [],
            ],
            'laboratorios' => [
                'required'    => array_merge($fiscalCodes, [
                    'certificados_acreditacion', 'alcance_acreditacion', 'competencia_personal',
                ]),
                'recommended' => [],
            ],
            'sustancias_quimicas' => [
                'required'    => array_merge($fiscalCodes, [
                    'fichas_tecnicas_quimicas', 'hojas_seguridad',
                ]),
                'recommended' => [],
            ],
            'insumos' => [
                'required'    => $fiscalCodes,
                'recommended' => ['fichas_tecnicas_insumos'],
            ],
            'control_plagas' => [
                'required' => array_merge($fiscalCodes, [
                    'licencia_sanitaria', 'hojas_seguridad_plagas',
                    'fichas_tecnicas_plagas', 'registro_sanitario',
                    'competencia_personal_plagas',
                ]),
                'recommended' => [],
            ],
        ];

        $providerTypes = DB::table('provider_types')->get()->keyBy('code');

        foreach ($relations as $typeCode => $groups) {
            if (!isset($providerTypes[$typeCode])) continue;

            $providerTypeId = $providerTypes[$typeCode]->id;

            foreach ($groups['required'] as $docCode) {
                if (!isset($documentIds[$docCode])) continue;
                DB::table('document_type_provider_type')->insert([
                    'document_type_id' => $documentIds[$docCode],
                    'provider_type_id' => $providerTypeId,
                    'is_required'      => true,   // obligatorio
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }

            foreach ($groups['recommended'] as $docCode) {
                if (!isset($documentIds[$docCode])) continue;
                DB::table('document_type_provider_type')->insert([
                    'document_type_id' => $documentIds[$docCode],
                    'provider_type_id' => $providerTypeId,
                    'is_required'      => false,  // recomendado (no bloqueante)
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
        }
    }
}