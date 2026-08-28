<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Billing;

use App\Shared\Http\Requests\ListRequest;

/**
 * Filtres du sélecteur de prestations facturables.
 *
 * La période porte sur la **date demandée** du service : c'est elle qui dit
 * quand la prestation devait avoir lieu, et une facture couvre une période.
 *
 * Les autres filtres suivent les colonnes de l'écran, une par une. Ils sont
 * déclarés ici et appliqués par le serveur pour la même raison que
 * l'éligibilité (§42) : filtrer la page affichée ne porterait que sur
 * vingt-cinq lignes, et donnerait un résultat faux dès la deuxième page.
 */
class ListBillableServiceRequest extends ListRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'periodFrom' => ['sometimes', 'date'],
            'periodTo' => ['sometimes', 'date', 'after_or_equal:periodFrom'],

            // Colonne « Prestation » : son numero, son code, son libelle.
            'service' => ['sometimes', 'string', 'max:255'],
            // Colonne « Commande » : son numero seul, la reference ayant
            // desormais sa propre colonne et son propre filtre.
            'order' => ['sometimes', 'string', 'max:255'],
            'reference' => ['sometimes', 'string', 'max:255'],
            // Colonne « Adresse » : localite, code postal ou nom du lieu.
            'address' => ['sometimes', 'string', 'max:255'],

            'quantityMin' => ['sometimes', 'numeric', 'min:0'],
            'quantityMax' => ['sometimes', 'numeric', 'min:0', 'gte:quantityMin'],
            'priceMin' => ['sometimes', 'numeric', 'min:0'],
            'priceMax' => ['sometimes', 'numeric', 'min:0', 'gte:priceMin'],
        ]);
    }
}
