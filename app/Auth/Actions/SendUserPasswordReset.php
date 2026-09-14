<?php
declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Enums\UserAdminEvent;
use App\Auth\Models\User;
use App\Shared\Audit\Audit;
use Illuminate\Contracts\Auth\PasswordBroker;
use Lock\Server\Authentication\Actions\SendPasswordResetLink;

final readonly class SendUserPasswordReset
{
    public function __construct(private SendPasswordResetLink $sendResetLink) {}

    public function handle(User $user): string
    {
        $status = $user->realm->runAsCurrent(fn (): string => ($this->sendResetLink)($user->email));

        if ($status === PasswordBroker::RESET_LINK_SENT) {
            Audit::record(UserAdminEvent::PasswordResetSent, $user);
        }

        return $status;
    }
}
