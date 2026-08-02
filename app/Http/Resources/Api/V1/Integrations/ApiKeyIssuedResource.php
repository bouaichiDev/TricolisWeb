<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Integrations;

use App\Modules\Integrations\Models\CustomerApiConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réponse d'émission d'une clé API — **le seul endroit où la clé apparaît**.
 *
 * Elle est retournée à la création et à la rotation, jamais ailleurs : aucune
 * lecture ultérieure ne peut la restituer, puisque seule son empreinte est
 * stockée.
 *
 * Le champ `warning` n'est pas décoratif : sans lui, un intégrateur suppose
 * pouvoir relire la clé plus tard et ne la note pas.
 */
class ApiKeyIssuedResource extends JsonResource
{
    public function __construct(
        private readonly CustomerApiConfiguration $configuration,
        private readonly string $apiKey,
    ) {
        parent::__construct($configuration);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'configuration' => (new ApiConfigurationResource($this->configuration))->toArray($request),
            'apiKey' => $this->apiKey,
            'warning' => 'Cette clé n’est affichée qu’une seule fois : conservez-la maintenant, elle ne pourra pas être relue.',
        ];
    }
}
