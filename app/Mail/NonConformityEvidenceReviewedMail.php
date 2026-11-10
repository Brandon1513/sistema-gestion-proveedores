<?php

namespace App\Mail;

use App\Models\NonConformity;
use App\Models\NonConformityEvidence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Aviso al proveedor: su evidencia "Cumple" o "No cumple" */
class NonConformityEvidenceReviewedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public NonConformity $nonConformity, public NonConformityEvidence $evidence)
    {
    }

    public function build()
    {
        $nc       = $this->nonConformity->loadMissing(['provider:id,business_name']);
        $action   = $this->evidence->loadMissing('action')->action;
        $accepted = $this->evidence->review_status === NonConformityEvidence::REVIEW_ACCEPTED;

        return $this->subject(($accepted ? 'Evidencia aceptada' : 'Evidencia no cumple') . " — {$nc->folio}, acción {$action?->sort_order} — SGP DASAVENA")
            ->view('emails.non-conformity-evidence-reviewed')
            ->with([
                'accepted'        => $accepted,
                'folio'           => $nc->folio,
                'providerName'    => $nc->provider?->business_name,
                'actionNumber'    => $action?->sort_order,
                'actionText'      => $action?->action,
                'evidenceToGive'  => $action?->evidence_to_deliver,
                'comment'         => $this->evidence->review_comment,
                'evidenceDueDate' => $nc->evidence_due_date?->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
                'portalUrl'       => rtrim((string) config('app.frontend_url'), '/') . '/provider/non-conformities',
            ]);
    }
}