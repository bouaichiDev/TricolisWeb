<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer\Stock;

use App\Modules\CustomerPortal\Services\Catalog\CustomerCatalogScope;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\Statuses\Services\DefaultStatuses;
use App\Modules\Statuses\Services\StatusMachine;
use App\Modules\Stock\Models\StockItem;
use App\Shared\Database\MorphMap;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Règles d'un article en stock saisi depuis le portail (§13-§14).
 *
 * Les quantités et l'emplacement sont **interdits**, pas ignorés : un client
 * qui envoie `quantity` reçoit un 422 qui le dit. Le stock physique ne naît
 * que d'un mouvement interne.
 *
 * Code article et code-barres sont uniques **par client** (index de
 * `stock_items`), comme à l'interne. Le statut vient du référentiel
 * (`stock_item`) ; sans statut envoyé, celui par défaut s'applique.
 */
final class CustomerStockItemRules
{
    public const array FORBIDDEN = [
        'customerId', 'quantity', 'reservedQuantity', 'availableQuantity',
        'stockLocationId', 'locationId', 'depotId', 'balances',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $creating, ?string $ignoreId = null): array
    {
        $customerId = app(CurrentCustomerContext::class)->customerId();
        $presence = $creating ? 'required' : 'sometimes';
        $statuses = app(StatusMachine::class)->statuses(MorphMap::STOCK_ITEM)->where('active', true)->keys()->all();
        $needsStatus = $creating
            && app(DefaultStatuses::class)->chosen(MorphMap::STOCK_ITEM) === null
            && DefaultStatuses::columnDefault('stock_items') === null;

        return [
            ...array_fill_keys(self::FORBIDDEN, ['prohibited']),
            'catalogItemId' => ['sometimes', 'nullable', 'string', 'ulid', self::activeCatalogItem($ignoreId)],
            'articleCode' => [$presence, 'string', 'max:64', Rule::unique('stock_items', 'article_code')->where('customer_id', $customerId)->ignore($ignoreId)],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:128', Rule::unique('stock_items', 'barcode')->where('customer_id', $customerId)->ignore($ignoreId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            // À la création, vide vaut « statut par défaut » ; en modification,
            // un statut ne s'efface pas — la colonne n'accepte pas de vide.
            'status' => $creating
                ? [Rule::requiredIf($needsStatus), 'sometimes', 'nullable', 'string', Rule::in($statuses)]
                : ['sometimes', 'string', Rule::in($statuses)],
        ];
    }

    /**
     * Un article de catalogue désactivé n'est plus proposé : on ne le
     * rattache pas. Le lien déjà posé avant sa désactivation reste accepté —
     * renvoyer la valeur actuelle ne doit pas faire échouer une correction.
     * L'appartenance au client reste vérifiée par l'action (404 / 422).
     */
    private static function activeCatalogItem(?string $stockItemId): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($stockItemId): void {
            if (! is_string($value) || ! Str::isUlid($value)) {
                return;
            }

            $current = $stockItemId === null ? null : StockItem::whereKey($stockItemId)->value('catalog_item_id');

            if ($value !== $current && ! app(CustomerCatalogScope::class)->items()->where('status', 'active')->whereKey($value)->exists()) {
                $fail('Cet article du catalogue est introuvable ou désactivé.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            '*.prohibited' => 'La quantité et l’emplacement sont gérés par votre transporteur.',
            'articleCode.required' => 'Le code article est obligatoire.',
            'articleCode.unique' => 'Vous avez déjà un article avec ce code.',
            'barcode.unique' => 'Vous avez déjà un article avec ce code-barres.',
            'status.in' => 'Ce statut n’existe pas pour un article en stock.',
        ];
    }
}
