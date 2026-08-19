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
    public function run(): void
    {
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
}
