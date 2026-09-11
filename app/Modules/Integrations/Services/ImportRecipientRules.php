<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use Illuminate\Support\Facades\Validator;

/**
 * Ce que le fichier doit dire du **destinataire final** d'une prestation.
 *
 * Deux parties ne se décrivent pas de la même façon :
 *
 * - le **donneur d'ordre** est connu de Tricolis. Son code suffit —
 *   `services[].addressCode` désigne l'un de ses points enregistrés, et tout le
 *   reste se lit en base ;
 * - le **client final** ne l'est pas. Il n'existe nulle part avant le fichier :
 *   celui-ci doit donc le décrire en entier, sous `services[].recipient`.
 *
 * Une prestation va chez l'un **ou** chez l'autre, jamais les deux : les deux
 * renseignés, on ne saurait pas où livrer, et choisir en silence produirait une
 * commande fausse que personne ne relirait.
 *
 * Les règles servent **à l'essai comme à l'import** : un essai « valide » suivi
 * d'un refus à l'import est le pire des verdicts.
 */
final readonly class ImportRecipientRules
{
    /**
     * Ce qu'on ne peut pas laisser de côté : sans nom, sans téléphone ou sans
     * adresse complète, le chauffeur ne trouve pas le client ni ne le prévient.
     *
     * @var array<string, list<string>>
     */
    public const array RULES = [
        'firstName' => ['required', 'string', 'max:255'],
        'lastName' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255'],
        'phone' => ['required', 'string', 'max:255'],
        'mobile' => ['nullable', 'string', 'max:255'],
        'company' => ['nullable', 'string', 'max:255'],
        'addressLine1' => ['required', 'string', 'max:255'],
        'addressLine2' => ['nullable', 'string', 'max:255'],
        'postalCode' => ['required', 'string', 'max:64'],
        'city' => ['required', 'string', 'max:255'],
        'country' => ['required', 'string', 'size:2', 'alpha'],
        'instructions' => ['nullable', 'string'],
    ];

    /** @var array<string, string> */
    private const array ATTRIBUTES = [
        'firstName' => 'le prénom',
        'lastName' => 'le nom',
        'email' => 'le courriel',
        'phone' => 'le téléphone',
        'mobile' => 'le mobile',
        'company' => 'la société',
        'addressLine1' => 'la rue',
        'addressLine2' => 'le complément d’adresse',
        'postalCode' => 'le code postal',
        'city' => 'la ville',
        'country' => 'le pays',
        'instructions' => 'la consigne',
    ];

    /** @var array<string, string> */
    private const array MESSAGES = [
        'required' => 'Le destinataire final doit porter :attribute.',
        'email' => 'Pour le destinataire final, :attribute n’est pas une adresse valide.',
        'size' => 'Pour le destinataire final, :attribute s’écrit en :size lettres (CH, FR…).',
        'alpha' => 'Pour le destinataire final, :attribute s’écrit en lettres (CH, FR…).',
        'max' => 'Pour le destinataire final, :attribute dépasse :max caractères.',
        'string' => 'Pour le destinataire final, :attribute doit être un texte.',
    ];

    /**
     * Ce qui empêche une prestation d'aller chez son destinataire final.
     *
     * Les clés sont préfixées par `$prefix` — le chemin de la prestation — pour
     * que le refus nomme la colonne fautive, et la commande qui la porte.
     *
     * @param  array<string, mixed>  $service
     * @return array<string, list<string>>
     */
    public function errors(array $service, string $prefix): array
    {
        if (array_key_exists('address', $service)) {
            return ["{$prefix}.address" => [
                '« address » est remplacé par « recipient » : le client final porte désormais son identité, son téléphone et son courriel avec son adresse.',
            ]];
        }

        if (! array_key_exists('recipient', $service)) {
            return [];
        }

        $recipient = $service['recipient'];

        if (isset($service['addressCode'])) {
            return ["{$prefix}.recipient" => [
                'Une prestation va soit à un point du donneur d’ordre (addressCode), soit chez le client final (recipient) : pas les deux.',
            ]];
        }

        if (! is_array($recipient)) {
            return ["{$prefix}.recipient" => ['Le destinataire final se décrit champ par champ : prénom, nom, rue, ville…']];
        }

        $errors = [];
        $validator = Validator::make($recipient, self::RULES, self::MESSAGES, self::ATTRIBUTES);

        foreach ($validator->errors()->toArray() as $field => $messages) {
            $errors["{$prefix}.recipient.{$field}"] = array_values($messages);
        }

        return $errors;
    }
}
