<?php
declare(strict_types=1);

namespace App\Auth\Enums;

use App\Shared\Audit\Concerns\IsAdminEventType;
use App\Shared\Audit\Contracts\AdminEventType;

enum UserAdminEvent: string implements AdminEventType
{
    use IsAdminEventType;

    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserBlocked = 'user.blocked';
    case UserUnblocked = 'user.unblocked';
    case UserDeleted = 'user.deleted';
    case UserMfaReset = 'user.mfa-reset';
    case UserSessionEnded = 'user.session-ended';
    case UserSessionsEnded = 'user.sessions-ended';
    case PasswordResetSent = 'user.password-reset.sent';
    case VerificationResent = 'user.verification.resent';
    case SuperAdminGranted = 'user.super-admin-granted';
}
