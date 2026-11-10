<?php

namespace App\Mail;

use App\Models\NonConformity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Aviso al proveedor: la No Conformidad quedó cerrada */
class NonConformityClosedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public NonConformity $nonConformity)
    {
    }

    public function build()
    {
        $nc = $this->nonConformity->loadMissing(['provider:id,business_name', 'type:id,name', 'productService:id,name']);

        return $this->subject("No Conformidad {$nc->folio} cerrada — SGP DASAVENA")
            ->view('emails.non-conformity-closed')
            ->with([
                'folio'         => $nc->folio,
                'providerName'  => $nc->provider?->business_name,
                'typeName'      => $nc->type?->name,
                'productName'   => $nc->productService?->name,
                'displayStatus' => $nc->display_status,
                'isLate'        => $nc->display_status === NonConformity::DISPLAY_CLOSED_LATE,
                'closedAt'      => $nc->closed_at?->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
                'comment'       => $nc->closing_comment,
                'portalUrl'     => rtrim((string) config('app.frontend_url'), '/') . '/provider/non-conformities',
            ]);
    }
}