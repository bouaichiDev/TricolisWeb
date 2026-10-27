<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services;

use App\Modules\CustomerPortal\Enums\CustomerInvitationStatus;
use App\Modules\CustomerPortal\Mail\CustomerPortalMail;
use App\Modules\CustomerPortal\Models\CustomerUser;
use App\Modules\CustomerPortal\Models\CustomerUserInvitation;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Services\OrganizationMailer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Émet une invitation et envoie son lien.
 *
 * Une seule invitation reste valable par membre : en émettre une nouvelle
 * annule les précédentes. Un lien renvoyé rend donc l'ancien inutilisable,
 * ce qui est ce qu'on attend quand on renvoie parce qu'il a fuité.
 *
 * Le jeton brut n'est jamais stocké : il ne vit que dans le courriel.
 * Le courriel part **après** la validation de la transaction — une invitation
 * annulée par un rollback ne doit pas avoir déjà atterri dans une boîte.
 */
final readonly class IssueCustomerInvitation
{
    public function __construct(private OrganizationMailer $mailer) {}

    public function execute(CustomerUser $membership, ?string $roleId, ?User $inviter): CustomerUserInvitation
    {
        $membership->loadMissing(['customer.organization', 'user']);

        CustomerUserInvitation::where('customer_user_id', $membership->id)
            ->where('status', CustomerInvitationStatus::PENDING->value)
            ->update(['status' => CustomerInvitationStatus::CANCELLED->value]);

        $token = Str::random(64);

        $invitation = CustomerUserInvitation::create([
            'customer_id' => $membership->customer_id,
            'customer_user_id' => $membership->id,
            'email' => $membership->user->email,
            'role_id' => $roleId,
            'token_hash' => CustomerUserInvitation::hashToken($token),
            'status' => CustomerInvitationStatus::PENDING,
            'expires_at' => now()->addHours(Config::integer('customer_portal.invitation_expires_hours', 72)),
            'invited_by' => $inviter?->id,
        ]);

        DB::afterCommit(fn () => $this->send($membership, $invitation, $token, $inviter));

        return $invitation;
    }

    private function send(CustomerUser $membership, CustomerUserInvitation $invitation, string $token, ?User $inviter): void
    {
        $customer = $membership->customer;
        $url = sprintf('%s/activate-account?token=%s', rtrim(Config::string('customer_portal.url'), '/'), $token);

        $this->mailer->for($customer->organization_id)->to($invitation->email)->send(new CustomerPortalMail(
            CustomerPortalMail::INVITATION,
            'Invitation au portail client '.$customer->name,
            $url,
            [
                'customerName' => $customer->name,
                'organizationName' => $customer->organization?->name ?? 'Tricolis',
                'inviterName' => $inviter?->fullName() ?: 'Votre transporteur',
                'expiresAt' => $invitation->expires_at->format('d/m/Y H:i'),
            ],
        ));
    }
}
