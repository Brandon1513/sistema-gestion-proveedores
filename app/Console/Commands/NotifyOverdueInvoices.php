<?php

namespace App\Console\Commands;

use App\Models\NetsuiteVendorInvoice;
use App\Models\User;
use App\Notifications\OverdueInvoicesDigestNotification;
use Illuminate\Console\Command;

class NotifyOverdueInvoices extends Command
{
    protected $signature = 'netsuite:notify-overdue-invoices';
    protected $description = 'Envía un resumen diario de facturas vencidas a Compras y Finanzas';

    public function handle(): int
    {
        $today = now()->toDateString();

        $overdueInvoices = NetsuiteVendorInvoice::with('provider:id,business_name')
            ->whereDate('due_date', '<', $today)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'not ilike', '%pagado%');
            })
            ->get();

        if ($overdueInvoices->isEmpty()) {
            $this->info('No hay facturas vencidas. No se envía correo.');
            return self::SUCCESS;
        }

        // Marca la primera vez que cada factura fue detectada como vencida
        // (no afecta el envío del correo, solo lleva registro histórico).
        NetsuiteVendorInvoice::whereIn('id', $overdueInvoices->pluck('id'))
            ->whereNull('overdue_notified_at')
            ->update(['overdue_notified_at' => now()]);

        $staff = User::role(['finanzas', 'compras'])->get();

        if ($staff->isEmpty()) {
            $this->warn('No hay usuarios con rol finanzas o compras para notificar.');
            return self::SUCCESS;
        }

        foreach ($staff as $user) {
            $user->notify(new OverdueInvoicesDigestNotification($overdueInvoices));
        }

        $this->info("Notificadas {$overdueInvoices->count()} facturas vencidas a {$staff->count()} usuario(s).");

        return self::SUCCESS;
    }
}