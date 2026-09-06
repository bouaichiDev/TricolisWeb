<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Http\Requests\Api\V1\Orders\StoreOrderRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Le verdict de l'import, là où `ImportPreviewValidator` rend celui de l'essai.
 *
 * La prévisualisation dit ce qui manquerait ; ceci **refuse**. Et les règles ne
 * sont pas les mêmes : la prévisualisation retire les identifiants qu'aucun
 * fichier client ne porte, tandis qu'ici la résolution les a déjà écrits — le
 * jugement se fait donc sur `StoreOrderRequest` au complet, exactement comme
 * pour une commande saisie à la main.
 */
final readonly class ImportPayloadValidator
{
    /**
     * Refuse le fichier entier si une seule commande est invalide.
     *
     * Les erreurs sont préfixées par le rang de la commande dans le fichier —
     * `orders.1.services.0.unit` — sans quoi on saurait qu'il manque une unité
     * sans savoir laquelle des trente commandes la réclame.
     *
     * @param  list<array<string, mixed>>  $payloads
     *
     * @throws ValidationException
     */
    public function refuseIfInvalid(array $payloads): void
    {
        $rules = (new StoreOrderRequest)->rules();
        $errors = [];

        foreach ($payloads as $index => $payload) {
            $validator = Validator::make($payload, $rules);

            foreach ($validator->errors()->toArray() as $field => $messages) {
                $errors["orders.{$index}.{$field}"] = $messages;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
