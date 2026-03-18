<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    /**
     * Construye la URL del frontend manualmente — evita el error
     * "Route [password.reset] not defined" de Laravel
     */
    protected function resetUrl($notifiable): string
    {
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5175'));

        return $frontendUrl
            . '/reset-password'
            . '?token=' . $this->token
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
    }

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