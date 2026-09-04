<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Integrations;

use App\Modules\Integrations\Models\OrganizationMailConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * La boîte d'envoi telle qu'un écran a le droit de la voir.
 *
 * **Le mot de passe n'y figure pas**, et n'y figurera jamais : seul un booléen
 * dit qu'il en existe un. Un secret SMTP qui traverse une réponse JSON finit
 * dans un journal de requêtes, puis dans une capture d'écran de dépannage.
 *
 * @mixin OrganizationMailConfiguration
 */
class OrganizationMailConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'username' => $this->username,
            // Dire qu'un mot de passe existe, sans le rendre : l'écran doit
            // pouvoir afficher « inchangé » plutôt qu'un champ vide qui
            // laisserait croire qu'il n'y en a pas.
            'hasPassword' => $this->hasPassword(),
            'fromAddress' => $this->from_address,
            'fromName' => $this->from_name,
            'replyTo' => $this->reply_to,
            'isActive' => $this->is_active,
            'lastUsedAt' => $this->last_used_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
