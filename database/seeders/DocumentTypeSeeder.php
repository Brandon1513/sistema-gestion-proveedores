<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Documentos fiscales (comunes para todos)
        $fiscalDocuments = [
            [
                'code' => 'constancia_fiscal',
                'name' => 'Constancia de Situación Fiscal',
                'description' => 'No mayor a 2 meses',
                'category' => 'fiscal',
                'requires_expiry' => true,
                'expiry_alert_days' => 15,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 5,
            ],
            [
                'code' => 'opinion_cumplimiento',
                'name' => 'Opinión del Cumplimiento Fiscal (SAT)',
                'description' => 'Opinión positiva del SAT no mayor a 2 meses',
                'category' => 'fiscal',
                'requires_expiry' => true,
                'expiry_alert_days' => 15,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 5,
            ],
            [
                'code' => 'comprobante_domicilio',
                'name' => 'Comprobante de Domicilio',
                'description' => 'Recibo de luz, agua, teléfono, etc.',
                'category' => 'fiscal',
                'requires_expiry' => false,
                'expiry_alert_days' => null,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf', 'jpg', 'png']),
                'max_file_size_mb' => 5,
            ],
            [
                'code' => 'caratula_cuenta',
                'name' => 'Carátula Estado de Cuenta',
                'description' => 'Estado de cuenta con cuenta CLABE visible',
                'category' => 'fiscal',
                'requires_expiry' => false,
                'expiry_alert_days' => null,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf', 'jpg', 'png']),
                'max_file_size_mb' => 5,
            ],
            [
                'code' => 'identificacion_representante',
                'name' => 'Identificación del Representante Legal',
                'description' => 'INE o pasaporte vigente',
                'category' => 'legal',
                'requires_expiry' => true,
                'expiry_alert_days' => 30,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf', 'jpg', 'png']),
                'max_file_size_mb' => 5,
            ],
            [
                'code' => 'acta_constitutiva',
                'name' => 'Acta Constitutiva',
                'description' => 'Acta constitutiva de la empresa',
                'category' => 'legal',
                'requires_expiry' => false,
                'expiry_alert_days' => null,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 10,
            ],
        ];

        // Documentos técnicos para MP y ME
        $mpMeDocuments = [
            [
                'code' => 'cedula_rfc',
                'name' => 'Cédula del RFC',
                'description' => 'Cédula de identificación fiscal',
                'category' => 'fiscal',
                'requires_expiry' => false,
                'expiry_alert_days' => null,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 5,
            ],
            [
                'code' => 'certificaciones_planta',
                'name' => 'Certificaciones de Planta',
                'description' => 'ISO, FSSC, HACCP, etc.',
                'category' => 'quality',
                'requires_expiry' => true,
                'expiry_alert_days' => 60,
                'is_required' => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 10,
            ],
            [
                'code' => 'carta_garantia',
                'name' => 'Carta Garantía',
                'description' => 'Carta de garantía de productos',
                'category' => 'quality',
                'requires_expiry' => false,
                'expiry_alert_days' => null,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 5,
            ],
            [
                'code' => 'fichas_tecnicas',
                'name' => 'Fichas Técnicas',
                'description' => 'Fichas técnicas de productos',
                'category' => 'technical',
                'requires_expiry' => true,
                'expiry_alert_days' => 90,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 10,
            ],
            [
                'code' => 'carta_revision_metales',
                'name' => 'Carta de Revisión de Metales',
                'description' => 'Detector de metales o trampas magnéticas',
                'category' => 'quality',
                'requires_expiry' => true,
                'expiry_alert_days' => 90,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 5,
            ],
            [
                'code' => 'cartas_declaracion',
                'name' => 'Cartas de Declaración',
                'description' => 'Alérgenos, origen, ausencia de OGM, ingredientes',
                'category' => 'quality',
                'requires_expiry' => true,
                'expiry_alert_days' => 90,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 5,
            ],
            [
                'code' => 'informacion_nutrimental',
                'name' => 'Información Nutrimental',
                'description' => 'Solo para materias primas',
                'category' => 'technical',
                'requires_expiry' => true,
                'expiry_alert_days' => 90,
                'is_required' => false,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 5,
            ],
            [
                'code' => 'certificado_calidad',
                'name' => 'Ejemplo de Certificado de Calidad',
                'description' => 'Modelo de certificado de calidad que emiten',
                'category' => 'quality',
                'requires_expiry' => false,
                'expiry_alert_days' => null,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 5,
            ],
        ];

        // Documentos para residuos
        $residuosDocuments = [
            [
                'code' => 'permisos_semadet',
                'name' => 'Permisos SEMADET/SEMARNAT',
                'description' => 'Permisos ambientales vigentes',
                'category' => 'legal',
                'requires_expiry' => true,
                'expiry_alert_days' => 60,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 10,
            ],
            [
                'code' => 'manifiestos',
                'name' => 'Manifiestos',
                'description' => 'Manifiestos de recolección',
                'category' => 'technical',
                'requires_expiry' => false,
                'expiry_alert_days' => null,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 5,
            ],
        ];

        // Documentos para laboratorios
        $laboratoriosDocuments = [
            [
                'code' => 'certificados_acreditacion',
                'name' => 'Certificados de Acreditación',
                'description' => 'Certificados de acreditación del laboratorio',
                'category' => 'quality',
                'requires_expiry' => true,
                'expiry_alert_days' => 60,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 10,
            ],
            [
                'code' => 'alcance_acreditacion',
                'name' => 'Alcance de Acreditación',
                'description' => 'Documento que indique el alcance',
                'category' => 'technical',
                'requires_expiry' => true,
                'expiry_alert_days' => 60,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 5,
            ],
            [
                'code' => 'competencia_personal',
                'name' => 'Competencia del Personal Técnico',
                'description' => 'Certificados de competencia del personal',
                'category' => 'technical',
                'requires_expiry' => true,
                'expiry_alert_days' => 90,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 5,
            ],
        ];

        // Documentos para sustancias químicas
        $sustanciasDocuments = [
            [
                'code' => 'fichas_tecnicas_quimicas',
                'name' => 'Fichas Técnicas',
                'description' => 'Fichas técnicas de sustancias químicas',
                'category' => 'technical',
                'requires_expiry' => true,
                'expiry_alert_days' => 90,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 10,
            ],
            [
                'code' => 'hojas_seguridad',
                'name' => 'Hojas de Seguridad',
                'description' => 'Hojas de seguridad (MSDS/SDS)',
                'category' => 'technical',
                'requires_expiry' => true,
                'expiry_alert_days' => 90,
                'is_required' => true,
                'allowed_extensions' => json_encode(['pdf']),
                'max_file_size_mb' => 10,
            ],
        ];

        // Insertar todos los documentos
        $allDocuments = array_merge(
            $fiscalDocuments,
            $mpMeDocuments,
            $residuosDocuments,
            $laboratoriosDocuments,
            $sustanciasDocuments
        );

        $documentIds = [];
        foreach ($allDocuments as $document) {
            $id = DB::table('document_types')->insertGetId(array_merge($document, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $documentIds[$document['code']] = $id;
        }

        // Relacionar documentos con tipos de proveedores
        $relations = [
            'mp_me' => array_merge(
                array_keys(array_column($fiscalDocuments, 'code', 'code')),
                array_keys(array_column($mpMeDocuments, 'code', 'code'))
            ),
            'residuos' => array_merge(
                array_keys(array_column($fiscalDocuments, 'code', 'code')),
                array_keys(array_column($residuosDocuments, 'code', 'code'))
            ),
            'laboratorios' => array_merge(
                array_keys(array_column($fiscalDocuments, 'code', 'code')),
                array_keys(array_column($laboratoriosDocuments, 'code', 'code'))
            ),
            'sustancias_quimicas' => array_merge(
                array_keys(array_column($fiscalDocuments, 'code', 'code')),
                array_keys(array_column($sustanciasDocuments, 'code', 'code'))
            ),
            'insumos' => array_keys(array_column($fiscalDocuments, 'code', 'code')),
        ];

        $providerTypes = DB::table('provider_types')->get();

        foreach ($providerTypes as $providerType) {
            if (isset($relations[$providerType->code])) {
                foreach ($relations[$providerType->code] as $docCode) {
                    if (isset($documentIds[$docCode])) {
                        DB::table('document_type_provider_type')->insert([
                            'document_type_id' => $documentIds[$docCode],
                            'provider_type_id' => $providerType->id,
                            'is_required' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }
}