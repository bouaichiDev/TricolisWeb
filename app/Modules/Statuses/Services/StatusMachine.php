<?php

declare(strict_types=1);

namespace App\Modules\Statuses\Services;

use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Models\StatusTransition;
use Illuminate\Support\Collection;

/**
 * La machine à états, lue en base.
 *
 * Le cycle de vie était figé dans `OrderStatus::allowedTransitions()`. Un
 * référentiel que l'administrateur gère et des règles dans le code ne peuvent
 * pas coexister : un statut créé à l'écran n'était atteignable par aucune
 * transition. C'est donc `status_transitions` qui décide désormais, et l'énumération
 * ne sert plus qu'à nommer les statuts que le code désigne explicitement.
 *
 * Tout est chargé en une requête par entité, puis mémorisé pour la durée du
 * processus : la fiche d'une commande interroge la machine plusieurs fois, et
 * une requête par appel serait du gaspillage.
 */
final class StatusMachine
{
    /** @var array<string, Collection<string, Status>> statuts par entité, indexés par code */
    private array $statuses = [];

    /** @var array<string, array<string, array<string, bool>>> from → to → is_manual */
    private array $transitions = [];

    /**
     * Statuts atteignables depuis un code, dans l'ordre d'affichage.
     *
     * @return list<Status>
     */
    public function transitionsFrom(string $source, ?string $code, bool $manualOnly = true): array
    {
        if ($code === null) {
            return [];
        }

        $edges = $this->edges($source)[$code] ?? [];
        $statuses = $this->statuses($source);

        $reachable = [];

        foreach ($edges as $target => $isManual) {
            if ($manualOnly && ! $isManual) {
                continue;
            }

            $status = $statuses->get($target);

            if ($status !== null && $status->active) {
                $reachable[] = $status;
            }
        }

        usort($reachable, static fn (Status $a, Status $b): int => ($a->position ?? $a->status) <=> ($b->position ?? $b->status));

        return $reachable;
    }

    /** La transition existe-t-elle, quelle que soit son origine ? */
    public function allows(string $source, ?string $from, string $to): bool
    {
        return isset($this->edges($source)[$from ?? ''][$to]);
    }

    /** Un opérateur peut-il poser cette transition lui-même ? */
    public function allowsManually(string $source, ?string $from, string $to): bool
    {
        return ($this->edges($source)[$from ?? ''][$to] ?? false) === true;
    }

    /** Le contenu reste-t-il modifiable dans ce statut ? */
    public function allowsContentChanges(string $source, ?string $code): bool
    {
        return $this->status($source, $code)?->allows_content_changes === true;
    }

    /** Ce statut exige-t-il un motif pour être posé ? */
    public function requiresReason(string $source, ?string $code): bool
    {
        return $this->status($source, $code)?->requires_reason === true;
    }

    public function status(string $source, ?string $code): ?Status
    {
        return $code === null ? null : $this->statuses($source)->get($code);
    }

    /** @return Collection<string, Status> */
    public function statuses(string $source): Collection
    {
        return $this->statuses[$source] ??= Status::where('source', $source)
            ->orderBy('position')
            ->get()
            ->keyBy('code');
    }

    /**
     * Arêtes du graphe pour une entité : `from → to → is_manual`.
     *
     * @return array<string, array<string, bool>>
     */
    private function edges(string $source): array
    {
        if (isset($this->transitions[$source])) {
            return $this->transitions[$source];
        }

        $statuses = $this->statuses($source);
        $byId = $statuses->keyBy('id');
        $edges = [];

        $rows = StatusTransition::whereIn('from_status_id', $byId->keys())->get();

        foreach ($rows as $row) {
            $from = $byId->get($row->from_status_id)?->code;
            $to = $byId->get($row->to_status_id)?->code;

            if ($from === null || $to === null) {
                continue;
            }

            $edges[$from][$to] = $row->is_manual;
        }

        return $this->transitions[$source] = $edges;
    }

    /** Vide la mémorisation — utile entre deux tests qui réécrivent le référentiel. */
    public function flush(): void
    {
        $this->statuses = [];
        $this->transitions = [];
    }
}
