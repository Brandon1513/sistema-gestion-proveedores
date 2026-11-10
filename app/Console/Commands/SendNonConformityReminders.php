<?php

namespace App\Console\Commands;

use App\Mail\NonConformityReminderMail;
use App\Models\NonConformity;
use App\Models\NonConformityEvidence;
use App\Models\Provider;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Recordatorios de plazos de No Conformidades (F-CAL-24).
 *
 * Se envían 7 días antes y el día que vence la fecha límite vigente
 * (respuesta mientras no haya plan; evidencias después). El aviso va a
 * quien tiene pendiente la siguiente acción: el proveedor o el ingeniero.
 * Cada NC guarda el último recordatorio para no repetirlo el mismo día.
 */
class SendNonConformityReminders extends Command
{
    protected $signature = 'non-conformities:send-reminders {--dry-run : Solo muestra lo que se enviaría}';
    protected $description = 'Envía recordatorios de vencimiento de las No Conformidades abiertas';

    public function handle(): int
    {
        $today  = Carbon::today();
        $dryRun = (bool) $this->option('dry-run');
        $sent   = 0;

        $ncs = NonConformity::with([
                'provider:id,business_name,email',
                'type:id,name',
                'productService:id,name',
                'acceptedResponse.actions.evidences',
            ])
            ->where('status', '!=', NonConformity::STATUS_CLOSED)
            ->get();

        foreach ($ncs as $nc) {
            $due = $nc->current_due_date;
            if (!$due) continue;

            $daysLeft = (int) $today->diffInDays($due, false);
            if (!in_array($daysLeft, [NonConformity::REMINDER_DAYS_BEFORE, 0], true)) continue;

            $key = $due->format('Y-m-d') . ':' . $daysLeft;
            if ($nc->last_reminder_key === $key) continue; // ya se envió hoy

            [$providerTask, $internalTask] = $this->pendingTasks($nc);
            $isDueToday = $daysLeft === 0;

            $this->line(sprintf('%s · vence %s (%s) · proveedor: %s · interno: %s',
                $nc->folio, $due->format('Y-m-d'), $isDueToday ? 'hoy' : "en {$daysLeft} días",
                $providerTask ?? '—', $internalTask ?? '—'));

            if ($dryRun) continue;

            try {
                // Al proveedor, si la siguiente acción es suya
                if ($providerTask && ($user = $this->providerUser($nc))) {
                    Mail::to($user->email, $user->name)->queue(new NonConformityReminderMail($nc, $daysLeft, $providerTask));
                }

                // Al ingeniero: si la siguiente acción es suya, o el día que vence (para que dé seguimiento)
                if ($internalTask || $isDueToday) {
                    $what = $internalTask ?? 'el proveedor tiene pendiente: ' . $providerTask;
                    NotificationDispatcher::notifyRoles(
                        ['ingeniero_alimentos'],
                        null,
                        'nc_due_reminder',
                        $isDueToday ? "{$nc->folio} vence hoy" : "{$nc->folio} vence en {$daysLeft} días",
                        ($nc->provider?->business_name ?? 'Proveedor') . " — {$what}",
                        ['non_conformity_id' => $nc->id, 'link' => '/non-conformities']
                    );
                }

                $nc->forceFill(['last_reminder_key' => $key])->saveQuietly();
                $sent++;
            } catch (\Exception $e) {
                Log::error("Recordatorio de {$nc->folio} falló: " . $e->getMessage());
                $this->error("{$nc->folio}: " . $e->getMessage());
            }
        }

        $this->info($dryRun ? 'Simulación terminada (no se envió nada).' : "Recordatorios enviados: {$sent}");
        return self::SUCCESS;
    }

    /**
     * Qué tiene pendiente cada parte.
     * @return array{0: ?string, 1: ?string} [pendiente del proveedor, pendiente del ingeniero]
     */
    private function pendingTasks(NonConformity $nc): array
    {
        return match ($nc->status) {
            NonConformity::STATUS_AWAITING_RESPONSE => ['enviar su análisis de causa raíz y acciones correctivas', null],
            NonConformity::STATUS_PLAN_RETURNED     => ['enviar la corrección de su plan de acción', null],
            NonConformity::STATUS_PLAN_IN_REVIEW    => [null, 'revisar el plan de acción que envió el proveedor'],
            NonConformity::STATUS_AWAITING_EVIDENCE => $this->evidenceTasks($nc),
            default                                 => [null, null],
        };
    }

    private function evidenceTasks(NonConformity $nc): array
    {
        $p = $nc->evidence_progress;
        if (!$p) return [null, null];

        $toUpload = $p['missing'] + $p['rejected'];
        if ($toUpload > 0) {
            return ['entregar la evidencia de ' . ($toUpload > 1 ? "{$toUpload} acciones correctivas" : '1 acción correctiva'), null];
        }
        if ($p['pending'] > 0) {
            return [null, "revisar {$p['pending']} evidencia" . ($p['pending'] > 1 ? 's' : '') . ' del proveedor'];
        }
        return [null, 'cerrar la No Conformidad (todas las evidencias cumplen)'];
    }

    private function providerUser(NonConformity $nc): ?User
    {
        $email = $nc->provider?->email ?? Provider::whereKey($nc->provider_id)->value('email');
        return $email ? User::where('email', $email)->first() : null;
    }
}