<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Provider;

use App\Modules\Drivers\Models\Driver;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Modules\ProviderPortal\Services\DriverAccountOwnership;
use App\Modules\ProviderPortal\Services\ProviderStatusLabels;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un chauffeur, tel que son fournisseur le voit. Les actions offertes sont
 * calculées ici : l'interface les affiche, le serveur les revérifie.
 *
 * @mixin Driver
 */
class ProviderDriverResource extends JsonResource
{
    /**
     * @param  bool|null  $ownsAccount  précalculé pour une page (DriverAccountOwnership::ownedAmong) ; calculé ici sinon
     */
    public function __construct(Driver $resource, private readonly ?bool $ownsAccount = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $context = app(CurrentProviderContext::class);
        $canUpdate = $context->can('provider.drivers.update');
        $user = $this->user;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'firstName' => $user?->first_name,
            'lastName' => $user?->last_name,
            'email' => $user?->email,
            'phone' => $user?->phone,
            'status' => $this->status,
            'statusLabel' => app(ProviderStatusLabels::class)->label('driver', $this->status),
            'actions' => [
                'canUpdate' => $canUpdate,
                // Prénom, nom, téléphone : seulement si le compte est propre au chauffeur.
                'canEditAccount' => $canUpdate && ($user === null || ($this->ownsAccount ?? DriverAccountOwnership::owns($this->resource))),
                'canDeactivate' => $canUpdate && $this->status === 'active',
                'canActivate' => $canUpdate && $this->status === 'inactive',
            ],
        ];
    }
}
