<?php

declare(strict_types=1);

namespace App\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PlatformAccess;
use App\Modules\Pricing\Models\PricingVariable;

/**
 * Deux niveaux d'autorité sur le catalogue des variables tarifaires.
 *
 * **Tout membre le lit.** Écrire un barème sans savoir quelles variables
 * existent est impossible : le refuser rendrait l'éditeur de formules muet.
 *
 * **Seule la plateforme l'écrit.** La source d'une variable est un chemin vers
 * la base ; la laisser choisir localement reviendrait à ouvrir les colonnes des
 * autres domaines à qui saurait nommer une table. Et une variable définie
 * organisme par organisme ferait qu'une même formule ne voudrait plus dire la
 * même chose d'un client à l'autre.
 *
 * Le contrôle est double — portée **et** permission — comme pour les statuts :
 * un rôle plateforme incomplet ne suffit pas.
 */
class PricingVariablePolicy
{
    public function __construct(private readonly PlatformAccess $platform) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PricingVariable $variable): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->platform->hasPlatformPermission($user, 'pricing_variables.manage');
    }

    public function update(User $user, PricingVariable $variable): bool
    {
        return $this->platform->hasPlatformPermission($user, 'pricing_variables.manage');
    }

    /**
     * Supprimer une variable.
     *
     * Rien n'empêche techniquement de retirer une variable employée par une
     * formule ; le contrôleur s'en charge, parce que c'est une question de
     * données et non de droit.
     */
    public function delete(User $user, PricingVariable $variable): bool
    {
        return $this->platform->hasPlatformPermission($user, 'pricing_variables.manage');
    }
}
