<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Services\OrganizationMailer;
use App\Modules\ProviderPortal\Enums\ProviderInvitationStatus;
use App\Modules\ProviderPortal\Mail\ProviderPortalMail;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\ProviderPortal\Models\ProviderUserInvitation;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Émet une invitation au portail fournisseur et envoie son lien.
 *
 * Une seule invitation reste valable par membre : en émettre une nouvelle
 * annule les précédentes. Le jeton brut n'est jamais stocké, et le courriel
 * part **après** la validation de la transaction.
 */
final readonly class IssueProviderInvitation
{
    public function __construct(private OrganizationMailer $mailer) {}

    public function execute(ProviderUser $membership, ?string $roleId, ?User $inviter): ProviderUserInvitation
    {
        $membership->loadMissing(['provider.organization', 'user']);

        ProviderUserInvitation::where('provider_user_id', $membership->id)
            ->where('status', ProviderInvitationStatus::PENDING->value)
            ->update(['status' => ProviderInvitationStatus::CANCELLED->value]);

        $token = Str::random(64);
        $invitation = ProviderUserInvitation::create([
            'provider_id' => $membership->provider_id,
            'provider_user_id' => $membership->id,
            'email' => $membership->user->email,
            'role_id' => $roleId,
            'token_hash' => ProviderUserInvitation::hashToken($token),
            'status' => ProviderInvitationStatus::PENDING,
            'expires_at' => now()->addHours(Config::integer('provider_portal.invitation_expires_hours', 72)),
            'invited_by' => $inviter?->id,
        ]);

        DB::afterCommit(fn () => $this->send($membership, $invitation, $token, $inviter));

        return $invitation;
    }

    private function send(ProviderUser $membership, ProviderUserInvitation $invitation, string $token, ?User $inviter): void
    {
        $provider = $membership->provider;
        $url = sprintf('%s/activate-account?token=%s', rtrim(Config::string('provider_portal.url'), '/'), $token);

        $this->mailer->for($provider->organization_id)->to($invitation->email)->send(new ProviderPortalMail(
            ProviderPortalMail::INVITATION,
            'Invitation au portail fournisseur '.$provider->name,
            $url,
            [
                'providerName' => $provider->name,
                'organizationName' => $provider->organization?->name ?? 'Tricolis',
                'inviterName' => $inviter?->fullName() ?: 'Votre transporteur',
                'expiresAt' => $invitation->expires_at->format('d/m/Y H:i'),
            ],
        ));
    }
}
