<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    /**
     * Sobrescribe el email de reset con el estilo de DASAVENA
     */
    public function toMail($notifiable): MailMessage
    {
        $resetUrl = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('Restablecer contraseña — SGP DASAVENA')
            ->view('emails.reset-password', [
                'resetUrl'  => $resetUrl,
                'userEmail' => $notifiable->email,
                'userName'  => $notifiable->name,
            ]);
    }
}