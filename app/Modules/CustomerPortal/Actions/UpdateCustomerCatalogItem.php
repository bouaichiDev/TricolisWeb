<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Catalogs\Models\CustomerCatalog;
use App\Modules\Catalogs\Models\CustomerCatalogItem;
use App\Modules\CustomerPortal\Services\Catalog\CustomerCatalogScope;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Shared\Support\InputMapper;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Modifier, activer ou désactiver un article du catalogue depuis le portail
 * (`customer.articles.update`).
 *
 * **Isolation** : l'article n'est jamais lu par son seul identifiant. Il est
 * relu sous verrou par `CustomerCatalogScope::lockedItem()`, qui ne cherche
 * que dans les catalogues du client de la session et de son organisation :
 * l'article d'un autre client est introuvable (404), rien n'est écrit ni
 * audité. Le catalogue d'un article ne change jamais (la requête l'interdit).
 *
 * Mêmes règles que le back-office : code unique **dans son catalogue**,
 * vérifié sous verrou du catalogue ; audit `updated` + audit du portail.
 */
final readonly class UpdateCustomerCatalogItem
{
    private const array MAPPING = [
        'article_code' => 'articleCode', 'barcode' => 'barcode', 'name' => 'name', 'description' => 'description',
        'weight' => 'weight', 'volume' => 'volume', 'length' => 'length', 'width' => 'width', 'height' => 'height',
        'assembly_time_minutes' => 'assemblyTimeMinutes',
    ];

    /** Colonnes non nulles à défaut 0 : vidées, elles reviennent à 0. */
    private const array DEFAULTED = ['weight', 'volume'];

    public function __construct(
        private CustomerCatalogScope $scope,
        private CurrentCustomerContext $context,
        private WriteAuditLog $audit,
        private CustomerPortalAudit $portalAudit,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validé par `UpdateCustomerCatalogItemRequest`
     */
    public function update(string $itemId, array $input, Request $request): CustomerCatalogItem
    {
        try {
            [$item, $old] = DB::transaction(function () use ($itemId, $input): array {
                $item = $this->scope->lockedItem($itemId);
                $old = $item->toArray();

                if (isset($input['articleCode']) && $input['articleCode'] !== $item->article_code) {
                    CustomerCatalog::whereKey($item->catalog_id)->lockForUpdate()->firstOrFail();

                    if (CustomerCatalogItem::where('catalog_id', $item->catalog_id)->where('article_code', $input['articleCode'])->whereKeyNot($item->id)->exists()) {
                        throw self::duplicateCode();
                    }
                }

                // Un poids ou un volume vidé revient à 0 (la colonne refuse le vide) :
                // l'ignorer laisserait l'ancienne valeur sous un « Article modifié ».
                $attributes = InputMapper::map($input, self::MAPPING);
                foreach (self::DEFAULTED as $column) {
                    if (array_key_exists($column, $attributes) && $attributes[$column] === null) {
                        $attributes[$column] = 0;
                    }
                }

                $item->update($attributes);

                // Rien n'a changé (corps vide, mêmes valeurs) : pas d'audit.
                return [$item, $item->wasChanged() ? $old : null];
            });
        } catch (UniqueConstraintViolationException) {
            // Une écriture concurrente a pris ce code entre la vérification et
            // l'écriture : l'index unique tranche, le client reçoit l'erreur du champ.
            throw self::duplicateCode();
        }

        if ($old !== null) {
            $this->record($item, $old, 'catalog_item_updated', $request);
        }

        return $item->refresh();
    }

    /**
     * Active ou désactive. Déjà dans l'état demandé : rien n'est écrit ni audité.
     */
    public function setActive(string $itemId, bool $active, Request $request): CustomerCatalogItem
    {
        $status = $active ? 'active' : 'inactive';

        [$item, $old] = DB::transaction(function () use ($itemId, $status): array {
            $item = $this->scope->lockedItem($itemId);
            $old = $item->status === $status ? null : $item->toArray();

            if ($old !== null) {
                $item->update(['status' => $status]);
            }

            return [$item, $old];
        });

        if ($old !== null) {
            $this->record($item, $old, $active ? 'catalog_item_activated' : 'catalog_item_deactivated', $request);
        }

        return $item->refresh();
    }

    /**
     * @param  array<string, mixed>  $old
     */
    private function record(CustomerCatalogItem $item, array $old, string $action, Request $request): void
    {
        $this->audit->execute($this->context->organizationId(), $this->context->user(), 'updated', $item, $old, $item->fresh()->toArray(), $request);
        $this->portalAudit->record($this->context->customer(), $this->context->user(), $action, $item, null, [
            'catalogId' => $item->catalog_id,
            'articleCode' => $item->article_code,
            'status' => $item->status,
        ], $request->ip());
    }

    private static function duplicateCode(): ValidationException
    {
        return ValidationException::withMessages(['articleCode' => ['Ce code article existe déjà dans ce catalogue.']]);
    }
}
