<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Customers\Enums\CustomerStatus;
use App\Modules\Orders\Enums\OrderServiceStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Organizations\Enums\SubscriptionStatus;
use App\Modules\Statuses\Models\Status;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Enums\TourStopStatus;
use App\Shared\Database\MorphMap;
use App\Shared\Enums\OrganizationStatus;
use App\Shared\Enums\UserStatus;
use Illuminate\Database\Seeder;

/**
 * Remplit le référentiel à partir des énumérations qui existent déjà.
 *
 * **Rien n'est inventé.** Seules les entités dont le statut est gouverné par une
 * énumération PHP sont semées : ce sont les seules dont on connaisse la liste
 * exacte. Les autres colonnes `status` sont des chaînes libres, et deviner leurs
 * valeurs produirait un référentiel faux dès la première commande.
 *
 * **Une ligne existante n'est jamais réécrite.** Libellé, icône, rang et
 * comportement sont réglables depuis l'écran ; rejouer le seeder ne doit pas
 * effacer ce qu'un administrateur a décidé. Seules les lignes manquantes sont
 * créées.
 */
class StatusSeeder extends Seeder
{
    /**
     * Énumérations connues, par entité.
     *
     * @var array<string, class-string>
     */
    private array $enums = [
        MorphMap::ORDER => OrderStatus::class,
        MorphMap::ORDER_SERVICE => OrderServiceStatus::class,
        MorphMap::ORDER_COMMUNICATION => CommunicationStatus::class,
        MorphMap::CUSTOMER => CustomerStatus::class,
        MorphMap::USER => UserStatus::class,
        MorphMap::ORGANIZATION => OrganizationStatus::class,
        MorphMap::SUBSCRIPTION => SubscriptionStatus::class,
        MorphMap::TOUR => TourStatus::class,
        MorphMap::TOUR_STOP => TourStopStatus::class,
    ];

    /**
     * Entités dont le statut n'est gouverné par aucune énumération.
     *
     * Ressources et référentiels n'ont pas de cycle de vie : ils servent ou ne
     * servent plus. Les deux codes repris ici sont ceux que le projet emploie
     * déjà partout — `active` est la seule valeur présente en base, `inactive`
     * son pendant, connu de l'interface et des anciens référentiels. Aucun
     * autre n'est inventé : un administrateur en ajoute depuis l'écran des
     * statuts, ce qui est précisément la raison d'être du référentiel.
     *
     * Deux exceptions, tirées du domaine lui-même : un véhicule peut être en
     * maintenance et un fournisseur bloqué. Ces codes n'ont pas été devinés,
     * ils sont déjà employés par la suite de tests — les omettre aurait fait
     * refuser une écriture que le projet pratique.
     *
     * @var array<string, array<string, string>>
     */
    private array $resources = [
        MorphMap::PROVIDER => ['active' => 'Actif', 'inactive' => 'Inactif', 'blocked' => 'Bloqué'],
        MorphMap::DRIVER => ['active' => 'Actif', 'inactive' => 'Inactif'],
        MorphMap::VEHICLE => ['active' => 'Actif', 'inactive' => 'Inactif', 'maintenance' => 'En maintenance'],
        MorphMap::TYPE => ['active' => 'Actif', 'inactive' => 'Inactif'],
        MorphMap::TYPE_ITEM => ['active' => 'Actif', 'inactive' => 'Inactif'],
        // Planification : `tour` et `tour_stop` tiennent leurs valeurs de leurs
        // enumerations. Ces deux-la n'en ont pas, et leurs codes sont ceux que
        // le projet emploie deja — `replanned` distingue une affectation qui
        // remplace une precedente.
        MorphMap::TOUR_STOP_SERVICE => [
            'planned' => 'Planifié',
            'replanned' => 'Replanifié',
            'done' => 'Effectué',
        ],
        MorphMap::TOUR_PERIOD => [
            'planned' => 'Planifié',
            'done' => 'Effectué',
        ],
        // Facturation. Aucun de ces codes n'était déclaré : seules les fabriques
        // de test les employaient, ce qui en faisait la convention réelle du
        // projet sans que le référentiel ne la connaisse. Ils sont en minuscules
        // comme partout ailleurs, contrairement aux exemples du document.
        MorphMap::INVOICE => [
            'draft' => 'Brouillon',
            // Le code de la clôture, celui qui déclenche les exports client.
            'closed' => 'Clôturée',
        ],
        MorphMap::INVOICE_LINE => ['billable' => 'Facturable'],
        MorphMap::PROVIDER_SETTLEMENT => [
            'draft' => 'Brouillon',
            'closed' => 'Clôturé',
        ],
        // `processing` s'ajoute aux trois codes des fabriques : sans lui, une
        // reprise ne saurait pas distinguer un envoi en vol d'un envoi jamais
        // tenté.
        MorphMap::EXPORT_JOB => [
            'pending' => 'En attente',
            'processing' => 'En cours d’envoi',
            'sent' => 'Envoyé',
            'failed' => 'Échoué',
        ],
    ];

    public function run(): void
    {
        foreach ($this->enums as $source => $enum) {
            $this->seedSource((string) $source, $enum);
        }

        foreach ($this->resources as $source => $codes) {
            $this->seedCodes((string) $source, $codes);
        }
    }

    /**
     * @param  array<string, string>  $codes  code => libellé
     */
    private function seedCodes(string $source, array $codes): void
    {
        $rank = 0;

        foreach ($codes as $code => $label) {
            $rank++;

            $status = Status::firstOrNew(['source' => $source, 'code' => $code]);

            // Une ligne existante n'est jamais reecrite : le libelle et le rang
            // se reglent depuis l'ecran, et rejouer le seeder ne doit pas
            // effacer ce qu'un administrateur a decide.
            if ($status->exists) {
                continue;
            }

            $status->fill([
                'status' => $rank,
                'label' => $label,
                'position' => $rank * 10,
            ])->save();
        }
    }

    /**
     * @param  class-string  $enum
     */
    private function seedSource(string $source, string $enum): void
    {
        if (! enum_exists($enum)) {
            return;
        }

        $rank = 0;

        foreach ($enum::cases() as $case) {
            $rank++;

            $status = Status::firstOrNew(['source' => $source, 'code' => $case->value]);

            if ($status->exists) {
                continue;
            }

            $status->fill([
                'status' => $rank,
                'label' => method_exists($case, 'label') ? $case->label() : $case->name,
                'position' => $rank * 10,
                // Les deux comportements viennent de l'énumération quand elle
                // les définit ; ailleurs ils restent au défaut de la colonne.
                'allows_content_changes' => method_exists($case, 'allowsContentChanges')
                    && $case->allowsContentChanges(),
                'requires_reason' => method_exists($case, 'requiresReason') && $case->requiresReason(),
            ])->save();
        }
    }
}
