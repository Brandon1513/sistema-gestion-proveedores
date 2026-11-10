<?php

namespace App\Mail;

use App\Models\NonConformity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Recordatorio al proveedor: su No Conformidad vence en 7 días o vence hoy */
class NonConformityReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public NonConformity $nonConformity,
        public int $daysLeft,
        public string $pendingTask,
    ) {
    }

    public function build()
    {
        $nc = $this->nonConformity->loadMissing(['provider:id,business_name', 'type:id,name', 'productService:id,name']);
        $dueToday = $this->daysLeft === 0;

        return $this->subject(($dueToday ? '🚨 Vence hoy' : "⚠️ Vence en {$this->daysLeft} días") . " — No Conformidad {$nc->folio} — SGP DASAVENA")
            ->view('emails.non-conformity-reminder')
            ->with([
                'dueToday'     => $dueToday,
                'daysLeft'     => $this->daysLeft,
                'folio'        => $nc->folio,
                'providerName' => $nc->provider?->business_name,
                'typeName'     => $nc->type?->name,
                'productName'  => $nc->productService?->name,
                'pendingTask'  => $this->pendingTask,
                'dueDate'      => $nc->current_due_date?->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY'),
                'portalUrl'    => rtrim((string) config('app.frontend_url'), '/') . '/provider/non-conformities',
            ]);
    }
}