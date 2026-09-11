<?php

declare(strict_types=1);

namespace App\Modules\Statuses\Services;

use App\Modules\Statuses\Models\Status;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Le statut qu'une entité reçoit à sa création.
 *
 * **Pour toutes les entités, sans en nommer aucune.** L'écoute porte sur la
 * création de n'importe quel modèle ; seuls ceux que `StatusSources` reconnaît —
 * morph map et colonne `status` — sont concernés. Un module ajouté demain est
 * servi sans une ligne de plus, comme il apparaît déjà dans le référentiel.
 *
 * L'ordre de décision :
 *
 * ```
 * statut fourni à la création        → gardé tel quel
 * sinon, statut par défaut du référentiel (actif) → appliqué
 * sinon, valeur par défaut de la colonne          → appliquée
 * ```
 *
 * La dernière branche n'est pas décorative : la base remplirait la colonne
 * d'elle-même, mais le modèle en mémoire resterait sans statut, et le code qui
 * le lit juste après la création — un audit, un événement — lirait `null`.
 *
 * **Portée requête.** Les choix sont lus une fois par entité, puis gardés le
 * temps de la requête : une commande crée ses lignes, ses colis et ses services
 * dans la foulée, et une requête par modèle serait du gaspillage. Une mémoire
 * de processus survivrait en revanche au changement de réglage.
 */
final class DefaultStatuses
{
    /** @var array<string, string|null> code choisi, par entité */
    private array $chosen = [];

    /** @var array<string, string|null> valeur par défaut de la colonne, par table */
    private static array $columnDefaults = [];

    public function apply(Model $model): void
    {
        $source = $model->getMorphClass();

        if (! StatusSources::supports($source)) {
            return;
        }

        $current = $model->getAttributes()['status'] ?? null;

        if ($current !== null && $current !== '') {
            return;
        }

        $code = $this->assignable($model, $this->chosen($source))
            ?? $this->assignable($model, self::columnDefault($model->getTable()));

        if ($code !== null) {
            $model->setAttribute('status', $code);
        }
    }

    /** Le code du statut par défaut choisi pour une entité, s'il y en a un. */
    public function chosen(string $source): ?string
    {
        if (! array_key_exists($source, $this->chosen)) {
            $this->chosen[$source] = Status::where('source', $source)
                ->where('is_default', true)
                ->where('active', true)
                ->value('code');
        }

        return $this->chosen[$source];
    }

    /** Ce que la base mettrait d'elle-même, faute de choix. */
    public static function columnDefault(string $table): ?string
    {
        if (! array_key_exists($table, self::$columnDefaults)) {
            $column = collect(Schema::getColumns($table))->firstWhere('name', 'status');
            $default = is_array($column) && is_string($column['default'] ?? null) ? $column['default'] : null;

            // SQLite et PostgreSQL rendent la valeur entre apostrophes.
            self::$columnDefaults[$table] = $default === null ? null : trim($default, "'\"");
        }

        return self::$columnDefaults[$table];
    }

    public function flush(): void
    {
        $this->chosen = [];
    }

    /**
     * Un code que le modèle peut réellement porter.
     *
     * Une entité dont `status` est une énumération PHP refuserait un code
     * inconnu d'elle : l'assigner ferait échouer la création entière. Mieux vaut
     * alors retomber sur la valeur de la colonne que casser une commande.
     */
    private function assignable(Model $model, ?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        $cast = $model->getCasts()['status'] ?? null;

        if (is_string($cast) && is_subclass_of($cast, BackedEnum::class) && $cast::tryFrom($code) === null) {
            return null;
        }

        return $code;
    }
}
