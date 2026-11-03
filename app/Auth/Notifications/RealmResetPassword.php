<?php

declare(strict_types=1);

namespace App\Auth\Notifications;

use App\Auth\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Reads the recipient's realm rather than the current one, because the console
 * sends these for users of every realm.
 */
final class RealmResetPassword extends ResetPassword
{
    /**
     * @param  User  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return $notifiable->realm->themeMail($this->buildMailMessage($this->resetUrl($notifiable)));
    }
}
