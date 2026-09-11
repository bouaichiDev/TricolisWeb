<?php

declare(strict_types=1);

namespace App\Modules\Statuses\Services;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Packages\Models\Package;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Qui contient qui, dans une commande.
 *
 * La propagation d'un statut a besoin de savoir où aller : le service d'un
 * colis, les colis d'un service, la commande d'une ligne. Ces liens sont
 * **structurels** — ils viennent du modèle, pas d'un réglage — et les faire
 * saisir à l'administrateur reviendrait à lui demander de redécrire le schéma.
 *
 * ```
 * commande ─┬─ service ── colis ── ligne
 *           ├─ colis
 *           └─ ligne
 * ```
 *
 * Un colis appartient à sa commande **et** aux services qui le traitent : le
 * même colis est livré puis monté. C'est pourquoi un parent est une collection,
 * jamais un seul modèle.
 *
 * Les entités sans lien déclaré ici — un véhicule, une facture — n'entrent pas
 * dans la propagation : leur cycle de vie ne dépend d'aucun contenant.
 */
final readonly class StatusRelations
{
    /**
     * Enfants directs de chaque entité.
     *
     * @var array<string, list<string>>
     */
    private const array CHILDREN = [
        MorphMap::ORDER => [MorphMap::ORDER_SERVICE, MorphMap::PACKAGE, MorphMap::ORDER_LINE],
        MorphMap::ORDER_SERVICE => [MorphMap::PACKAGE],
        MorphMap::PACKAGE => [MorphMap::ORDER_LINE],
    ];

    /**
     * La hiérarchie elle-même, pour l'écran qui déclare les règles.
     *
     * L'interface doit savoir qui contient qui — pour ne proposer que des
     * paires qui mènent quelque part, et pour n'afficher « tous / un seul » que
     * lorsque la question se pose. La recopier côté navigateur la ferait
     * diverger au premier lien ajouté.
     *
     * @return array<string, list<string>>
     */
    public function hierarchy(): array
    {
        return self::CHILDREN;
    }

    /** Cette entité entre-t-elle dans la hiérarchie d'une commande ? */
    public function knows(string $alias): bool
    {
        foreach (self::CHILDREN as $parent => $children) {
            if ($parent === $alias || in_array($alias, $children, true)) {
                return true;
            }
        }

        return false;
    }

    public function isChildOf(string $child, string $parent): bool
    {
        return in_array($child, self::CHILDREN[$parent] ?? [], true);
    }

    /** Les deux entités sont-elles reliées, dans un sens ou dans l'autre ? */
    public function related(string $one, string $other): bool
    {
        return $this->isChildOf($one, $other) || $this->isChildOf($other, $one);
    }

    /**
     * @return Collection<int, Model>
     */
    public function childrenOf(Model $model, string $alias): Collection
    {
        $relation = match (true) {
            $model instanceof Order && $alias === MorphMap::ORDER_SERVICE => $model->orderServices(),
            $model instanceof Order && $alias === MorphMap::PACKAGE => $model->packages(),
            $model instanceof Order && $alias === MorphMap::ORDER_LINE => $model->lines(),
            $model instanceof OrderService && $alias === MorphMap::PACKAGE => $model->packages(),
            $model instanceof Package && $alias === MorphMap::ORDER_LINE => $model->orderLines(),
            default => null,
        };

        return $relation === null ? collect() : $relation->get();
    }

    /**
     * Contenants d'une entité — plusieurs, quand un colis sert deux services.
     *
     * @return Collection<int, Model>
     */
    public function parentsOf(Model $model, string $alias): Collection
    {
        $parent = match (true) {
            $model instanceof OrderService && $alias === MorphMap::ORDER => $model->order,
            $model instanceof Package && $alias === MorphMap::ORDER => $model->order,
            $model instanceof Package && $alias === MorphMap::ORDER_SERVICE => $model->orderServices()->get(),
            $model instanceof OrderLine && $alias === MorphMap::ORDER => $model->order,
            $model instanceof OrderLine && $alias === MorphMap::PACKAGE => $model->packages()->get(),
            default => null,
        };

        return match (true) {
            $parent === null => collect(),
            $parent instanceof Collection => $parent,
            default => collect([$parent]),
        };
    }
}
