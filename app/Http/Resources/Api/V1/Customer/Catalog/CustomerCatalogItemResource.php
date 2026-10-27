<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer\Catalog;

use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un article du catalogue du client, tel que le portail le montre.
 *
 * Un article n'a pas de champ interne : tout ce qu'il décrit appartient au
 * client. Les dimensions sont rendues telles que stockées (chaînes décimales),
 * jamais converties en flottant. `usage` n'est fourni que sur la fiche.
 *
 * @mixin CustomerCatalogItem
 */
class CustomerCatalogItemResource extends JsonResource
{
    /**
     * @param  array{orderLines: int, stockItems: int}|null  $usage
     */
    public function __construct(CustomerCatalogItem $resource, private readonly ?array $usage = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'catalog' => $this->whenLoaded('catalog', fn (): array => [
                'id' => $this->catalog->id,
                'code' => $this->catalog->code,
                'name' => $this->catalog->name,
            ]),
            'articleCode' => $this->article_code,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'weight' => $this->weight,
            'volume' => $this->volume,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'assemblyTimeMinutes' => $this->assembly_time_minutes,
            'status' => $this->status,
            'isActive' => $this->isActive(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'usage' => $this->when($this->usage !== null, $this->usage),
            // Ce que l'utilisateur peut faire de l'article : le portail n'affiche
            // « Modifier » / « Désactiver » qu'à ce signal, l'API revérifie.
            'actions' => [
                'canUpdate' => app(CurrentCustomerContext::class)->can('customer.articles.update'),
            ],
        ];
    }
}
