<?php

namespace App\Mail;

use App\Models\NonConformity;
use App\Support\RichText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso al proveedor de una nueva No Conformidad (F-CAL-24).
 * No incluye la acción inmediata (seguimiento interno de DASAVENA).
 */
class NonConformityIssuedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public NonConformity $nonConformity)
    {
    }

    public function build()
    {
        $nc = $this->nonConformity->loadMissing(['provider:id,business_name', 'productService:id,name', 'unit:id,abbreviation', 'type:id,name']);
        $fmt = fn ($d) => $d?->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

        return $this->subject("No Conformidad {$nc->folio} — {$nc->type?->name} — SGP DASAVENA")
            ->view('emails.non-conformity-issued')
            ->with([
                'folio'           => $nc->folio,
                'providerName'    => $nc->provider?->business_name,
                'typeName'        => $nc->type?->name,
                'productName'     => $nc->productService?->name,
                'lot'             => $nc->lot,
                'deviationDate'   => $fmt($nc->deviation_date),
                'receptionDate'   => $fmt($nc->reception_date),
                'quantity'        => $nc->quantity_involved !== null ? rtrim(rtrim(number_format((float) $nc->quantity_involved, 2), '0'), '.') . ' ' . ($nc->unit?->abbreviation ?? '') : null,
                // HTML limpio (o texto plano escapado) para mostrarlo con {!! !!}
                'descriptionHtml' => RichText::toHtml($nc->description),
                'responseDueDate' => $fmt($nc->response_due_date),
                'responseDays'    => NonConformity::RESPONSE_DAYS,
                'evidenceDays'    => NonConformity::EVIDENCE_DAYS,
                'portalUrl'       => rtrim((string) config('app.frontend_url'), '/') . '/provider/non-conformities',
            ]);
    }
}