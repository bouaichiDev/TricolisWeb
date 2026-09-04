<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Modules\Contacts\Models\Contact;
use InvalidArgumentException;

/**
 * Un client de démonstration et son carnet d'adresses.
 *
 * Les fabriques de commandes ont besoin de trois choses d'un client : son
 * identifiant, son code — qui préfixe désormais la référence de chaque commande
 * — et **quelle adresse sert à quoi**. Les passer en paramètres séparés faisait
 * six arguments de plus par appel, et rien n'empêchait d'y glisser l'adresse de
 * facturation là où la livraison était attendue.
 */
final readonly class SeededCustomer
{
    /**
     * @param  array<string, string>  $addressIds  rôle d'adresse → identifiant
     * @param  array<string, Contact>  $contacts  rôle d'adresse → contact rattaché
     */
    public function __construct(
        public string $id,
        public string $code,
        private array $addressIds,
        private array $contacts,
    ) {}

    /** L'adresse tenant ce rôle — `delivery`, `load` ou `billing`. */
    public function addressFor(string $role): string
    {
        return $this->addressIds[$role]
            ?? throw new InvalidArgumentException("Le client {$this->code} n'a pas d'adresse « {$role} ».");
    }

    /**
     * Le contact de cette adresse.
     *
     * Chaque adresse porte le sien : qui prévenir dépend du lieu, pas du client
     * dans l'absolu — le magasinier du quai n'est pas le comptable du siège.
     */
    public function contactFor(string $role): Contact
    {
        return $this->contacts[$role]
            ?? throw new InvalidArgumentException("Le client {$this->code} n'a pas de contact « {$role} ».");
    }
}
