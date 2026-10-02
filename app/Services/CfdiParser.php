<?php

namespace App\Services;

use Carbon\Carbon;

class CfdiParser
{
    /**
     * Lee un CFDI (3.3 o 4.0) y regresa sus datos clave.
     *
     * @throws \InvalidArgumentException si el XML no es un CFDI timbrado legible.
     */
    public function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        // LIBXML_NONET evita cargar recursos externos; no se usa LIBXML_NOENT a propósito.
        $loaded = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \InvalidArgumentException('El archivo XML está dañado o no tiene un formato válido.');
        }

        $comprobante = $this->first($doc, 'Comprobante');
        $emisor = $this->first($doc, 'Emisor');
        $receptor = $this->first($doc, 'Receptor');
        $timbre = $this->first($doc, 'TimbreFiscalDigital');

        if (!$comprobante || !$emisor || !$receptor) {
            throw new \InvalidArgumentException('El XML no corresponde a un CFDI.');
        }

        $uuid = strtoupper(trim($timbre?->getAttribute('UUID') ?? ''));
        if ($uuid === '') {
            throw new \InvalidArgumentException('El CFDI no está timbrado (no contiene UUID).');
        }

        return [
            'uuid' => $uuid,
            'version' => $comprobante->getAttribute('Version') ?: $comprobante->getAttribute('version'),
            'tipo' => strtoupper($comprobante->getAttribute('TipoDeComprobante')),
            'serie' => $comprobante->getAttribute('Serie') ?: null,
            'folio' => $comprobante->getAttribute('Folio') ?: null,
            'issued_at' => $this->parseDate($comprobante->getAttribute('Fecha')),
            'total' => (float) $comprobante->getAttribute('Total'),
            'currency' => $comprobante->getAttribute('Moneda') ?: 'MXN',
            'payment_method' => strtoupper($comprobante->getAttribute('MetodoPago')) ?: null,
            'issuer_rfc' => strtoupper(trim($emisor->getAttribute('Rfc'))),
            'receiver_rfc' => strtoupper(trim($receptor->getAttribute('Rfc'))),
        ];
    }

    protected function first(\DOMDocument $doc, string $name): ?\DOMElement
    {
        $node = $doc->getElementsByTagNameNS('*', $name)->item(0);

        return $node instanceof \DOMElement ? $node : null;
    }

    protected function parseDate(?string $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}