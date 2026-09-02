<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class OverdueInvoicesDigestNotification extends Notification
{
    use Queueable;

    protected Collection $invoices;

    public function __construct(Collection $invoices)
    {
        $this->invoices = $invoices;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $totalAmount = $this->invoices->sum('amount');
        $count = $this->invoices->count();

        $rows = $this->invoices
            ->sortByDesc('amount')
            ->take(15)
            ->map(function ($invoice) {
                $daysOverdue = now()->diffInDays($invoice->due_date);
                $provider = $invoice->provider->business_name ?? '—';
                return "{$provider} — {$invoice->tran_id} — \$" . number_format($invoice->amount, 2) . " — {$daysOverdue} días vencida";
            });

        $mail = (new MailMessage)
            ->subject("Resumen diario: {$count} facturas vencidas — \$" . number_format($totalAmount, 2))
            ->view('emails.overdue-invoices-digest', [
                'count' => $count,
                'totalAmount' => $totalAmount,
                'rows' => $rows,
                'hasMore' => $this->invoices->count() > 15,
                'remainingCount' => max(0, $this->invoices->count() - 15),
                'actionUrl' => url('/finance/account-statement'),
            ]);

        return $mail;
    }
}