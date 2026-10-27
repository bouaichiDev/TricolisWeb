<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\CustomerPortal\Exceptions\CustomerSelectionRequired;
use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\CustomerPortal\Support\PortalOrganizationGate;
use App\Modules\Customers\Enums\CustomerStatus;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Connexion au portail client.
 *
 * Mêmes garde-fous que la connexion interne (débit limité, compte actif), et
 * une condition de plus : le compte doit être **membre actif d'un client
 * actif**. Un collaborateur de transporteur sans appartenance cliente n'entre
 * pas ici, même avec le bon mot de passe.
 *
 * Un compte membre de plusieurs clients doit choisir : `customerId` désigne
 * lequel, parmi ceux auxquels il a réellement accès. Sans choix, la liste lui
 * est rendue — après vérification du mot de passe seulement.
 */
final readonly class LoginCustomerUser
{
    public function __construct(
        private RateLimiter $limiter,
        private CustomerPortalAudit $audit,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(Request $request, string $email, string $password, ?string $customerId): CustomerUser
    {
        $email = strtolower($email);
        $key = 'customer-login:'.$request->ip().'|'.$email;

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
            $this->refuse($key, $request, $user, 'no_active_customer', 'Ce compte n’a pas d’accès actif au portail client.');
        }

        $membership = $this->choose($memberships, $customerId);
        $this->limiter->clear($key);

        $user->forceFill(['last_login_at' => now()])->save();
        $this->audit->record($membership->customer, $user, 'login', $membership, null, [], $request->ip());

        return $membership;
    }

    /**
     * @return Collection<int, CustomerUser>
     */
    private function eligibleMemberships(User $user): Collection
    {
        return CustomerUser::with('customer')
            ->where('user_id', $user->id)
            ->where('status', UserStatus::ACTIVE->value)
            ->whereHas('customer', static fn ($query) => $query->where('status', CustomerStatus::ACTIVE->value)
                ->whereHas('organization', static fn ($organization) => $organization->whereNotIn('status', PortalOrganizationGate::closedStatuses())))
            ->get();
    }

    /**
     * @param  Collection<int, CustomerUser>  $memberships
     */
    private function choose(Collection $memberships, ?string $customerId): CustomerUser
    {
        if ($customerId !== null) {
            return $memberships->firstWhere('customer_id', $customerId)
                ?? $this->fail('customerId', 'Ce compte n’a pas accès à ce client.');
        }

        if ($memberships->count() > 1) {
            throw new CustomerSelectionRequired($memberships->map(static fn (CustomerUser $m): array => [
                'id' => $m->customer_id,
                'name' => $m->customer->name,
            ])->values()->all());
        }

        return $memberships->first();
    }

    private function refuse(string $key, Request $request, ?User $user, string $reason, string $message): never
    {
        $this->limiter->hit($key, 60);

        Log::warning('Customer portal authentication failed.', [
            'email_hash' => hash('sha256', (string) $user?->email),
            'ip' => $request->ip(),
            'reason' => $reason,
        ]);

        $membership = $user === null ? null : CustomerUser::with('customer')->where('user_id', $user->id)->first();

        if ($membership !== null) {
            $this->audit->record($membership->customer, $user, 'login_failed', $membership, null, ['reason' => $reason], $request->ip());
        }

        $this->fail('email', $message);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
