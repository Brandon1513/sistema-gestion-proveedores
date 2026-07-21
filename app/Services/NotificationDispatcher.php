<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AppointmentEventNotification;
use Illuminate\Support\Facades\Notification;

class NotificationDispatcher
{
    /**
     * Notifica a todos los usuarios que tengan alguno de los roles indicados,
     * excluyendo siempre al usuario que ejecutó la acción (para que no se
     * autonotifique).
     *
     * @param string[] $roles       Slugs de rol de Spatie (ej. ['seguridad','admin'])
     * @param int|null $actorId     ID del usuario que disparó el evento (se excluye)
     * @param string   $eventType   Identificador del tipo de evento
     * @param string   $title       Título corto para la campana
     * @param string   $message     Mensaje descriptivo
     * @param array    $data        Datos extra (appointment_id, block_id, link, etc.)
     */
    public static function notifyRoles(
        array $roles,
        ?int $actorId,
        string $eventType,
        string $title,
        string $message,
        array $data = []
    ): void {
        $query = User::role($roles);

        if ($actorId) {
            $query->where('id', '!=', $actorId);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new AppointmentEventNotification($eventType, $title, $message, $data));
    }
}