<?php

namespace App\Mail;

use App\Models\NonConformity;
use App\Models\NonConformityAction;
use App\Models\NonConformityEvidence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Aviso al ingeniero de alimentos: el proveedor subió una evidencia */
class NonConformityEvidenceSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public NonConformity $nonConformity,
        public NonConformityAction $action,
        public NonConformityEvidence $evidence,
    ) {
    }

    public function build()
    {
        $nc = $this->nonConformity->loadMissing(['provider:id,business_name', 'type:id,name']);

        return $this->subject("Evidencia recibida — {$nc->folio}, acción {$this->action->sort_order} — SGP")
            ->view('emails.non-conformity-evidence-submitted')
            ->with([
                'folio'           => $nc->folio,
                'providerName'    => $nc->provider?->business_name,
                'actionNumber'    => $this->action->sort_order,
                'actionText'      => $this->action->action,
                'evidenceToGive'  => $this->action->evidence_to_deliver,
                'fileName'        => $this->evidence->original_name,
                'attempt'         => $this->evidence->attempt,
                'providerComment' => $this->evidence->provider_comment,
                'actionUrl'       => rtrim((string) config('app.frontend_url'), '/') . '/non-conformities',
            ]);
    }
}