<?php

declare(strict_types=1);

namespace App\Modules\Statuses\Services;

use App\Modules\Statuses\Models\StatusPropagation;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Fait suivre un changement de statut aux entités voisines.
 *
 * Une commande, ses services, ses colis et ses lignes avancent ensemble. Quand
 * tous les colis d'un service sont chargés, le service l'est ; quand le service
 * passe à « effectué », ses colis le sont aussi. Cela se faisait à la main,
 * entité par entité, et la moindre étape oubliée laissait une commande dont le
 * statut mentait.
 *
 * **Les règles sont en base** (`status_propagations`), pas ici : c'est
 * l'administrateur qui dit ce qu'un statut entraîne, comme il dessine déjà les
 * transitions. Ce service n'en connaît que le sens de lecture :
 *
 * ```
 * la cible contient l'entité   → remontée : tous (ou l'un) des enfants y sont ?
 * l'entité contient la cible   → descente : chaque enfant suit
 * ```
 *
 * **Les chaînes sont voulues, les boucles non.** Colis → service → commande doit
 * se faire d'un seul mouvement ; une règle qui se répondrait — service → colis →
 * service — tournerait sans fin. Chaque entité n'est donc touchée qu'une fois
 * par chaîne, et la profondeur est bornée.
 */
final class StatusPropagator
{
    /** Assez pour colis → service → commande, et pour couper une boucle. */
    private const int MAX_DEPTH = 6;

    /** @var array<string, true> entités déjà touchées par la chaîne en cours */
    private array $visited = [];

    private int $depth = 0;

    public function __construct(
        private readonly StatusRelations $relations,
        private readonly ApplyPropagatedStatus $apply,
    ) {}

    public function handle(Model $model): void
    {
        $source = $model->getMorphClass();

        if (! $this->relations->knows($source) || ! $model->wasChanged('status')) {
            return;
        }

        $code = self::codeOf($model);

        if ($code === null || $this->depth >= self::MAX_DEPTH) {
            return;
        }

        $this->visited[$this->key($model)] = true;
        $this->depth++;

        try {
            foreach ($this->rulesFor($source, $code) as $rule) {
                $this->follow($model, $source, $code, $rule);
            }
        } finally {
            $this->depth--;

            // La chaîne est finie : une prochaine modification repart à neuf.
            if ($this->depth === 0) {
                $this->visited = [];
            }
        }
    }

    /**
     * @return Collection<int, StatusPropagation>
     */
    private function rulesFor(string $source, string $code): Collection
    {
        return StatusPropagation::query()
            ->active()
            ->with(['fromStatus', 'toStatus'])
            ->whereHas('fromStatus', fn ($query) => $query->where('source', $source)->where('code', $code))
            ->get()
            ->filter(fn (StatusPropagation $rule): bool => $rule->toStatus !== null && $rule->toStatus->active);
    }

    private function follow(Model $model, string $source, string $code, StatusPropagation $rule): void
    {
        $target = $rule->toStatus->source;

        if ($this->relations->isChildOf($source, $target)) {
            $this->climb($model, $source, $code, $target, $rule);

            return;
        }

        if ($this->relations->isChildOf($target, $source)) {
            foreach ($this->relations->childrenOf($model, $target) as $child) {
                $this->push($child, $rule, $model);
            }
        }
    }

    /**
     * Vers le contenant : il ne suit que si ses enfants y sont — tous, ou l'un.
     */
    private function climb(Model $model, string $source, string $code, string $target, StatusPropagation $rule): void
    {
        foreach ($this->relations->parentsOf($model, $target) as $parent) {
            $siblings = $this->relations->childrenOf($parent, $source);

            $satisfied = $rule->mode === StatusPropagation::MODE_ANY
                ? $siblings->contains(fn (Model $child): bool => self::codeOf($child) === $code)
                : $siblings->isNotEmpty()
                    && $siblings->every(fn (Model $child): bool => self::codeOf($child) === $code);

            if ($satisfied) {
                $this->push($parent, $rule, $model);
            }
        }
    }

    private function push(Model $entity, StatusPropagation $rule, Model $from): void
    {
        if (isset($this->visited[$this->key($entity)])) {
            return;
        }

        $this->visited[$this->key($entity)] = true;
        $this->apply->execute($entity, $rule->toStatus->code, $from, $rule->fromStatus->code);
    }

    private function key(Model $model): string
    {
        return $model->getMorphClass().':'.$model->getKey();
    }

    /** Le code du statut, que la colonne soit une chaîne ou une énumération. */
    public static function codeOf(Model $model): ?string
    {
        $status = $model->getAttribute('status');
        $status = $status instanceof BackedEnum ? $status->value : $status;

        return is_string($status) && $status !== '' ? $status : null;
    }
}
