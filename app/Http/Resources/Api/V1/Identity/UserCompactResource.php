<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Identity;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Utilisateur réduit à son identité.
 *
 * Sert partout où un compte n'est cité que comme auteur ou responsable : ni
 * statut, ni rôles, ni téléphone, ni aucune donnée sensible.
 *
 * @mixin User
 */
class UserCompactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'email' => $this->email,
        ];
    }
}
