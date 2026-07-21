<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppointmentEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $eventType,   // ej. 'appointment_rescheduled'
        protected string $title,       // ej. 'Cita reagendada'
        protected string $message,     // ej. 'Proveedora X fue reagendada para...'
        protected array $data = [],    // ej. ['appointment_id' => 16, 'link' => '/appointments']
    ) {}

    public function via($notifiable): array
    {
        // Solo canal de base de datos — sin email/SMS para estos eventos internos.
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'    => $this->eventType,
            'title'   => $this->title,
            'message' => $this->message,
            ...$this->data, // appointment_id, block_id, link, etc.
        ];
    }
}