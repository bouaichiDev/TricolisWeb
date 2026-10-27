<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Actions;

use App\Http\Middleware\EnsureProviderPortalContext;
use App\Modules\CustomerPortal\Support\PortalOrganizationGate;
use App\Modules\Identity\Models\User;
use App\Modules\ProviderPortal\Exceptions\ProviderSelectionRequired;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Services\ProviderPermissions;
use App\Modules\ProviderPortal\Services\ProviderPortalAudit;
use App\Shared\Enums\UserStatus;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Connexion au portail fournisseur.
 *
 * Mêmes garde-fous que le portail client (débit limité, compte actif), et la
 * condition propre au portail : un rattachement actif, à un fournisseur
 * actif, d'une organisation ouverte, avec l'accès au portail. Un collaborateur
 * interne sans rattachement n'entre pas, même avec le bon mot de passe.
 *
 * Tous les refus après mot de passe vérifié portent le même message : il ne
 * dit pas quel maillon manque.
 */
final readonly class LoginProviderUser
{
    public const string NO_ACCESS = 'Ce compte n’a pas d’accès actif au portail fournisseur.';

    public function __construct(
        private RateLimiter $limiter,
        private ProviderPortalAudit $audit,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(Request $request, string $email, string $password, ?string $providerId): ProviderUser
    {
        $email = strtolower($email);
        $key = 'provider-login:'.$request->ip().'|'.$email;

        if ($this->limiter->tooManyAttempts($key, 5)) {
            event(new Lockout($request));
            $this->fail('email', sprintf('Trop de tentatives. Réessayez dans %d secondes.', $this->limiter->availableIn($key)));
        }

        $user = User::where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->refuse($key, $request, $user, 'invalid_credentials', 'Adresse e-mail ou mot de passe incorrect.');
        }

        if (! $user->status->allowsLogin()) {
            $this->refuse($key, $request, $user, 'account_'.$user->status->value, 'Ce compte est inactif ou suspendu.');
        }

        $memberships = $this->eligibleMemberships($user);

        if ($memberships->isEmpty()) {
            $this->refuse($key, $request, $user, 'no_active_provider', self::NO_ACCESS);
        }

        $membership = $this->choose($memberships, $providerId);
        $this->limiter->clear($key);
        $user->forceFill(['last_login_at' => now()])->save();
        $this->audit->record($membership->provider, $user, 'login', $membership, null, [], $request->ip());

        return $membership;
    }

    /**
     * @return Collection<int, ProviderUser>
     */
    private function eligibleMemberships(User $user): Collection
    {
        return ProviderUser::with('provider')
            ->where('user_id', $user->id)
            ->where('status', UserStatus::ACTIVE->value)
            ->whereHas('provider', static fn ($query) => $query->where('status', EnsureProviderPortalContext::ACTIVE_PROVIDER)
                ->whereHas('organization', static fn ($organization) => $organization->whereNotIn('status', PortalOrganizationGate::closedStatuses())))
            ->get()
            ->filter(static fn (ProviderUser $membership): bool => in_array('provider.portal.access', ProviderPermissions::codesFor($membership), true))
            ->values();
    }

    /**
     * @param  Collection<int, ProviderUser>  $memberships
     */
    private function choose(Collection $memberships, ?string $providerId): ProviderUser
    {
        if ($providerId !== null) {
            return $memberships->firstWhere('provider_id', $providerId)
                ?? $this->fail('providerId', 'Ce compte n’a pas accès à ce fournisseur.');
        }

        if ($memberships->count() > 1) {
            throw new ProviderSelectionRequired($memberships->map(static fn (ProviderUser $m): array => [
                'id' => $m->provider_id,
                'name' => $m->provider->name,
            ])->values()->all());
        }

        return $memberships->first();
    }

    private function refuse(string $key, Request $request, ?User $user, string $reason, string $message): never
    {
        $this->limiter->hit($key, 60);

        Log::warning('Provider portal authentication failed.', [
            'email_hash' => hash('sha256', (string) $user?->email),
            'ip' => $request->ip(),
            'reason' => $reason,
        ]);

        $membership = $user === null ? null : ProviderUser::with('provider')->where('user_id', $user->id)->first();

        if ($membership !== null) {
            $this->audit->record($membership->provider, $user, 'login_failed', $membership, null, ['reason' => $reason], $request->ip());
        }

        $this->fail('email', $message);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
