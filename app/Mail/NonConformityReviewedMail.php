<?php

namespace App\Mail;

use App\Models\NonConformity;
use App\Models\NonConformityResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Aviso al proveedor: su plan de acción fue aceptado o regresado */
class NonConformityReviewedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public NonConformity $nonConformity, public NonConformityResponse $response)
    {
    }

    public function build()
    {
        $nc       = $this->nonConformity->loadMissing(['provider:id,business_name', 'type:id,name']);
        $accepted = $this->response->review_status === NonConformityResponse::REVIEW_ACCEPTED;
        $fmt      = fn ($d) => $d?->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

        return $this->subject($accepted
                ? "Plan de acción aceptado — No Conformidad {$nc->folio} — SGP DASAVENA"
                : "Se requieren correcciones — No Conformidad {$nc->folio} — SGP DASAVENA")
            ->view('emails.non-conformity-reviewed')
            ->with([
                'accepted'        => $accepted,
                'folio'           => $nc->folio,
                'providerName'    => $nc->provider?->business_name,
                'typeName'        => $nc->type?->name,
                'round'           => $this->response->round,
                'comment'         => $this->response->review_comment,
                'responseDueDate' => $fmt($nc->response_due_date),
                'evidenceDueDate' => $fmt($nc->evidence_due_date),
                'portalUrl'       => rtrim((string) config('app.frontend_url'), '/') . '/provider/non-conformities',
            ]);
    }
}