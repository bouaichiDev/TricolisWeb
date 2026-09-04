<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Models\StatusTransition;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Seeder;

/**
 * Sème la machine à états des commandes dans `status_transitions`.
 *
 * `OrderStatus` est la **seule** entité à déclarer un cycle de vie. Les autres
 * portent un statut sans transitions définies : leur en inventer produirait des
 * règles que personne n'a décidées, alors que le référentiel existe justement
 * pour qu'un administrateur les décide.
 *
 * L'énumération sert ici de **source d'amorçage**, une fois. Passé ce point,
 * c'est la table qui fait foi — `StatusMachine` ne lit plus qu'elle.
 *
 * `is_manual` se déduit de l'ancienne règle : la transition était posable à la
 * main si le statut cible figurait dans `manuallyAssignable()`. Portée par la
 * transition, la distinction devient plus fine — c'est ce qui permettra un jour
 * à la planification de poser « planifiée » sans que l'écran ne le propose.
 */
class StatusTransitionSeeder extends Seeder
{
    /**
     * Cycle de vie d'une tournee, decide par le proprietaire du projet le
     * 26 aout 2026.
     *
     * Il n'est pas deduit d'une enumeration : `TourStatus` enumere les valeurs
     * sans dire lesquelles s'enchainent. La suite retenue est celle du metier —
     * on confirme la tournee preparee, puis on la planifie, puis elle roule.
     *
     * L'annulation reste ouverte tant que la tournee n'est pas terminee : une
     * tournee achevee ne s'annule pas, elle se conteste.
     *
     * « Livree » et « effectuee » ne sont pas deux statuts : c'est le meme
     * `completed`, dont le libelle affiche au client vient du parcours de suivi,
     * ou chaque organisme nomme ses etapes.
     *
     * @var array<string, list<string>>
     */
    private const array TOUR_LIFECYCLE = [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['planned', 'cancelled'],
        'planned' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
    ];

    /**
     * Cycle de vie d'une facture, et d'un décompte fournisseur.
     *
     * Une seule transition, et elle ne revient pas : le §23 refuse la
     * réouverture, et une facture transmise à un système externe doit rester
     * historiquement stable. Ce qu'on veut défaire se corrige par un avoir, que
     * le §168 renvoie à une évolution de conception.
     *
     * @var array<string, list<string>>
     */
    private const array BILLING_LIFECYCLE = ['draft' => ['closed']];

    public function run(): void
    {
        $this->seedTours();
        $this->seedLifecycle(MorphMap::INVOICE, self::BILLING_LIFECYCLE);
        $this->seedLifecycle(MorphMap::PROVIDER_SETTLEMENT, self::BILLING_LIFECYCLE);

        $statuses = Status::where('source', MorphMap::ORDER)->get()->keyBy('code');

        if ($statuses->isEmpty()) {
            return;
        }

        $manual = array_map(
            static fn (OrderStatus $status): string => $status->value,
            OrderStatus::manuallyAssignable(),
        );

        foreach (OrderStatus::cases() as $from) {
            $source = $statuses->get($from->value);

            if ($source === null) {
                continue;
            }

            foreach ($from->allowedTransitions() as $to) {
                $target = $statuses->get($to->value);

                if ($target === null) {
                    continue;
                }

                StatusTransition::firstOrCreate(
                    ['from_status_id' => $source->id, 'to_status_id' => $target->id],
                    ['is_manual' => in_array($to->value, $manual, true)],
                );
            }
        }
    }

    /**
     * Sème le cycle de vie des tournées.
     *
     * Toutes ces transitions sont posables à la main : c'est le planificateur
     * qui confirme, planifie et lance une tournée depuis le back-office. Le
     * jour où le terminal du chauffeur posera « terminée » tout seul, la
     * distinction se réglera sur la ligne, sans toucher au code.
     */
    private function seedTours(): void
    {
        $this->seedLifecycle(MorphMap::TOUR, self::TOUR_LIFECYCLE);
    }

    /**
     * Sème un cycle de vie pour une source du référentiel.
     *
     * Les codes absents sont ignorés : un cycle qui nomme un statut non semé
     * décrit une intention, pas une erreur — il prendra effet le jour où le code
     * existera.
     *
     * @param  array<string, list<string>>  $lifecycle
     */
    private function seedLifecycle(string $source, array $lifecycle): void
    {
        $statuses = Status::where('source', $source)->get()->keyBy('code');

        if ($statuses->isEmpty()) {
            return;
        }

        foreach ($lifecycle as $from => $targets) {
            $source = $statuses->get($from);

            if ($source === null) {
                continue;
            }

            foreach ($targets as $to) {
                $target = $statuses->get($to);

                if ($target === null) {
                    continue;
                }

                StatusTransition::firstOrCreate(
                    ['from_status_id' => $source->id, 'to_status_id' => $target->id],
                    ['is_manual' => true],
                );
            }
        }
    }
}
