<?php

declare(strict_types=1);

namespace App\Auth\Notifications;

use App\Auth\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Reads the recipient's realm rather than the current one, because the console
 * sends these for users of every realm.
 */
final class RealmVerifyEmail extends VerifyEmail
{
    /**
     * @param  User  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return $notifiable->realm->themeMail($this->buildMailMessage($this->verificationUrl($notifiable)));
    }
}
