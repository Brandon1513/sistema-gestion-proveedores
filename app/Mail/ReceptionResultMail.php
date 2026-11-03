<?php

namespace App\Mail;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso al proveedor cuando su entrega tuvo devoluciones o alguna
 * condición de la entrega no cumplió.
 * Solo incluye información visible para el proveedor (provider_notes),
 * nunca las observaciones internas de DASAVENA.
 */
class ReceptionResultMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Appointment $appointment)
    {
    }

    public function build()
    {
        $a = $this->appointment->loadMissing([
            'provider:id,business_name',
            'items.productService:id,name',
            'items.unit:id,abbreviation',
            'items.receivedUnit:id,abbreviation',
        ]);

        $hasReturns = $a->items->contains(fn ($i) => (float) ($i->quantity_rejected ?? 0) > 0);

        $statusLabel = match (true) {
            $hasReturns && $a->reception_status === 'rejected' => 'Rechazada',
            $hasReturns                                         => 'Parcial — con devoluciones',
            default                                             => 'Recibida con observaciones',
        };

        // ✅ Condiciones de la entrega evaluadas por DASAVENA
        $conditions = collect($a->delivery_conditions ?? [])->values();

        $items = $a->items->map(function ($item) {
            $notDelivered = (bool) $item->not_delivered;
            $received     = (float) $item->quantity_received;
            $rejected     = (float) ($item->quantity_rejected ?? 0);

            return [
                'name'          => $item->productService?->name ?? 'Producto',
                'status'        => $item->reception_status,
                'status_label'  => $item->reception_status_label,
                'not_delivered' => $notDelivered,
                'received'      => $notDelivered ? null : number_format($received, 2),
                'accepted'      => $notDelivered ? null : number_format(max(0, $received - $rejected), 2),
                'rejected'      => $notDelivered ? null : number_format($rejected, 2),
                'unit'          => $item->receivedUnit?->abbreviation ?? $item->unit?->abbreviation ?? '',
                'reason'        => $item->rejection_reason_label,
                'notes'         => $item->provider_notes,
                'has_return'    => $rejected > 0,
            ];
        })->values();

        $subject = $hasReturns
            ? '⚠️ Resultado de su entrega: ' . $statusLabel . ' — SGP DASAVENA'
            : '⚠️ Observaciones en su entrega — SGP DASAVENA';

        return $this->subject($subject)
            ->view('emails.reception-result')
            ->with([
                'providerName' => $a->provider?->business_name,
                'statusLabel'  => $statusLabel,
                'status'       => $a->reception_status,
                'date'         => Carbon::parse($a->appointment_date)->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY'),
                'time'         => substr((string) $a->appointment_time, 0, 5),
                'items'        => $items,
                'hasReturns'   => $hasReturns,
                'conditions'   => $conditions,
                'portalUrl'    => config('app.frontend_url') . '/provider/appointments',
            ]);
    }
}