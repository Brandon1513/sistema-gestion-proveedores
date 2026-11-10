<?php

namespace App\Mail;

use App\Models\NonConformity;
use App\Models\NonConformityResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Aviso al ingeniero de alimentos: el proveedor envió su Sección 2 */
class NonConformityResponseSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public NonConformity $nonConformity, public NonConformityResponse $response)
    {
    }

    public function build()
    {
        $nc = $this->nonConformity->loadMissing(['provider:id,business_name', 'productService:id,name', 'type:id,name']);
        $r  = $this->response->loadMissing('actions');

        return $this->subject("Respuesta a la No Conformidad {$nc->folio}" . ($r->round > 1 ? " (vuelta {$r->round})" : '') . ' — SGP')
            ->view('emails.non-conformity-response-submitted')
            ->with([
                'folio'        => $nc->folio,
                'providerName' => $nc->provider?->business_name,
                'typeName'     => $nc->type?->name,
                'productName'  => $nc->productService?->name,
                'round'        => $r->round,
                'teamLeader'   => $r->team_leader,
                'area'         => $r->area,
                'actionsCount' => $r->actions->count(),
                'evidenceDue'  => $nc->evidence_due_date?->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
                'actionUrl'    => rtrim((string) config('app.frontend_url'), '/') . '/non-conformities',
            ]);
    }
}