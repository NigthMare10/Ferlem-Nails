<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

class CustomerResetPassword extends ResetPassword
{
    protected function resetUrl($notifiable)
    {
        return url('/clientes/restablecer-contrasena/'.$this->token).'?email='.urlencode($notifiable->getEmailForPasswordReset());
    }
}
