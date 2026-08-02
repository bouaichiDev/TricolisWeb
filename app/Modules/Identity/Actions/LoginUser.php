<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\OrganizationUser;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class LoginUser
{
    public function __construct(
        private RateLimiter $limiter,
        private WriteAuditLog $audit,
    ) {}

    /**
     * @param  array{email: string, password: string, device_name?: string|null}  $credentials
     *
     * @throws ValidationException
     */
    public function execute(Request $request, array $credentials): User
    {
        $email = strtolower($credentials['email']);
        $key = 'login:'.$request->ip().'|'.$email;

        if ($this->limiter->tooManyAttempts($key, 5)) {
            event(new Lockout($request));
            throw ValidationException::withMessages([
                'email' => [__('auth.throttle', ['seconds' => $this->limiter->availableIn($key)])],
            ]);
        }

        $user = User::where('email', $email)->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            $this->limiter->hit($key, 60);
            $this->recordFailure($request, $user, 'invalid_credentials', $email);
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->status->allowsLogin()) {
            $this->limiter->hit($key, 60);
            $this->recordFailure($request, $user, 'account_'.$user->status->value, $email);
            throw ValidationException::withMessages([
                'email' => ['Ce compte est inactif ou suspendu.'],
            ]);
        }

        $this->limiter->clear($key);

        $user->last_login_at = now();
        $user->save();

        $membership = $this->primaryMembership($user);
        if ($membership !== null) {
            $this->audit->execute($membership->organization_id, $user, 'login', $user, null, ['last_login_at' => $user->last_login_at], $request);
        }

        return $user;
    }

    /**
     * Journalise une tentative de connexion échouée.
     *
     * L'audit est rattaché à une organisation : une tentative sur un email
     * inconnu n'a donc pas d'organisation à laquelle s'accrocher et ne laisse
     * qu'une trace applicative, avec l'email haché et jamais le mot de passe.
     */
    private function recordFailure(Request $request, ?User $user, string $reason, string $email): void
    {
        Log::warning('Authentication failed.', [
            'email_hash' => hash('sha256', $email),
            'ip' => $request->ip(),
            'reason' => $reason,
        ]);

        if ($user === null) {
            return;
        }

        $membership = $this->primaryMembership($user);

        if ($membership !== null) {
            $this->audit->execute($membership->organization_id, $user, 'login_failed', $user, null, ['reason' => $reason], $request);
        }
    }

    private function primaryMembership(User $user): ?OrganizationUser
    {
        return $user->organizationUsers()->where('is_primary', true)->first()
            ?? $user->organizationUsers()->first();
    }
}
