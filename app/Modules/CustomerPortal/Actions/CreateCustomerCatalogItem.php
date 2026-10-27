<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Shared\Support\InputMapper;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ajouter un article au catalogue du client, depuis le portail
 * (`customer.articles.create`).
 *
 * Mêmes règles que l'ajout interne (`CustomerCatalogItemController::store`) :
 * code article unique **dans son catalogue**, mêmes champs, même audit
 * `created`. Le statut n'est pas choisi : l'article naît actif (défaut de la
 * colonne), comme tout article ajouté sans précision.
 *
 * L'unicité est revérifiée sous verrou du catalogue : deux ajouts simultanés
 * du même code ne passent pas tous deux.
 */
final readonly class CreateCustomerCatalogItem
{
    private const array MAPPING = [
        'article_code' => 'articleCode', 'barcode' => 'barcode', 'name' => 'name', 'description' => 'description',
        'weight' => 'weight', 'volume' => 'volume', 'length' => 'length', 'width' => 'width', 'height' => 'height',
        'assembly_time_minutes' => 'assemblyTimeMinutes',
    ];

    /** Colonnes non nulles à défaut 0 : une valeur vide n'y est pas écrite. */
    private const array DEFAULTED = ['weight', 'volume'];

    public function __construct(
        private CurrentCustomerContext $context,
        private WriteAuditLog $audit,
        private CustomerPortalAudit $portalAudit,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validé par `StoreCustomerCatalogItemRequest`
     */
    public function execute(CustomerCatalog $catalog, array $input, Request $request): CustomerCatalogItem
    {
        try {
            $item = DB::transaction(function () use ($catalog, $input): CustomerCatalogItem {
                CustomerCatalog::whereKey($catalog->id)->lockForUpdate()->firstOrFail();

                if ($catalog->items()->where('article_code', $input['articleCode'])->exists()) {
                    throw self::duplicateCode();
                }

                // Poids et volume sont `NOT NULL DEFAULT 0` : laissés vides, ils
                // prennent le défaut de la colonne au lieu d'un `null` refusé.
                $attributes = InputMapper::map($input, self::MAPPING);
                foreach (self::DEFAULTED as $column) {
                    if (array_key_exists($column, $attributes) && $attributes[$column] === null) {
                        unset($attributes[$column]);
                    }
                }

                return $catalog->items()->create($attributes);
            });
        } catch (UniqueConstraintViolationException) {
            // Une écriture concurrente a pris ce code entre la vérification et
            // l'écriture : l'index unique tranche, le client reçoit l'erreur du champ.
            throw self::duplicateCode();
        }

        $this->audit->execute($this->context->organizationId(), $this->context->user(), 'created', $item, null, $item->toArray(), $request);
        $this->portalAudit->record($this->context->customer(), $this->context->user(), 'catalog_item_created', $item, null, [
            'catalogId' => $catalog->id,
            'articleCode' => $item->article_code,
        ], $request->ip());

        return $item->refresh();
    }

    private static function duplicateCode(): ValidationException
    {
        return ValidationException::withMessages(['articleCode' => ['Ce code article existe déjà dans ce catalogue.']]);
    }
}
