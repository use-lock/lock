<?php

declare(strict_types=1);

namespace App\Admin\Console\Commands;

use App\Admin\Actions\EnsureAdministrator;
use App\Admin\Enums\BootstrapOutcome;
use App\Admin\ManagementApi;
use App\Realms\Models\Realm;
use App\Shared\Clients\Contracts\ProvisionsConsoleClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The environment is the source of truth on every run and an unset variable
 * means unmanaged, which keeps this safe to repeat on every deploy.
 */
#[Description('Reconcile the management API, the protected roles, the console client and the first administrator.')]
#[Signature('app:bootstrap')]
final class BootstrapCommand extends Command
{
    public function handle(ProvisionsConsoleClient $clients, EnsureAdministrator $administrators, ManagementApi $api): int
    {
        try {
            $realm = Realm::master();
            $outcomes = DB::transaction(fn (): array => [
                'console client' => $this->provisionClient($clients, $realm),
                'management api' => $api->reconcile($realm),
                'protected roles' => $api->reconcileRoles($realm),
                'administrator' => $this->ensureAdministrator($administrators),
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($outcomes as $item => $outcome) {
            $this->report($item, $outcome);
        }

        return self::SUCCESS;
    }

    private function provisionClient(ProvisionsConsoleClient $clients, Realm $realm): ?BootstrapOutcome
    {
        $clientId = $this->configured('lock.console_client.id');
        $clientSecret = $this->configured('lock.console_client.secret');
        $appUrl = rtrim($this->configured('app.url'), '/');

        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        if ($appUrl === '') {
            throw new RuntimeException('APP_URL must be set before the console client can be provisioned.');
        }

        return $clients->handle(
            realm: $realm,
            name: $this->configured('app.name') ?: 'Lock',
            clientId: $clientId,
            clientSecret: $clientSecret,
            trusted: true,
            redirectUris: [$appUrl.'/login/callback'],
            postLogoutRedirectUris: [$appUrl],
        );
    }

    private function ensureAdministrator(EnsureAdministrator $administrators): ?BootstrapOutcome
    {
        $email = $this->configured('lock.admin.email');
        $password = $this->configured('lock.admin.password');

        if ($email === '') {
            return null;
        }

        return $administrators->handle(
            $email,
            $this->configured('lock.admin.name') ?: 'Administrator',
            $password === '' ? null : $password,
        );
    }

    private function report(string $item, ?BootstrapOutcome $outcome): void
    {
        $this->components->twoColumnDetail($item, $outcome instanceof BootstrapOutcome ? $outcome->value : '<fg=gray>skipped (unset)</>');
    }

    private function configured(string $key): string
    {
        $value = config($key);

        return is_string($value) ? trim($value) : '';
    }
}
