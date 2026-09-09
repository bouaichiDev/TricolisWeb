<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use Carbon\CarbonImmutable;

/**
 * Ce dont un résolveur a besoin, et rien de plus.
 *
 * Il reçoit l'organisation, le jour et — s'il en existe une — la période
 * choisie. Pas l'utilisateur : l'autorisation est déjà tranchée quand un
 * résolveur s'exécute, et lui donner de quoi la rejuger inviterait à la rejuger
 * différemment. Un résolveur qui aurait accès aux permissions finirait par en
 * tenir compte, et la règle cesserait d'être appliquée à un seul endroit.
 *
 * Le jour est figé à la construction. Une requête qui traverse minuit ferait
 * sinon compter « aujourd'hui » deux jours différents selon le widget, et les
 * chiffres d'une même page ne se recouperaient pas.
 *
 * **La période, elle, n'est pas toujours là**, et son absence est la règle. Un
 * contexte sans période est celui d'un widget qui n'en a pas déclaré —
 * `DashboardDataSources` le fabrique par `withoutPeriod()` avant d'appeler la
 * source. C'est ce qui rend impossible qu'un compteur « du jour » se retrouve
 * filtré sur un trimestre parce qu'une branche a lu `$context->period` sans y
 * avoir droit.
 */
final readonly class DashboardContext
{
    public function __construct(
        public string $organizationId,
        public CarbonImmutable $today,
        public ?DashboardPeriod $period = null,
    ) {}

    public static function forOrganization(string $organizationId, ?DashboardPeriod $period = null): self
    {
        return new self($organizationId, CarbonImmutable::now()->startOfDay(), $period);
    }

    /** Le même contexte, pour un widget qui ne suit pas la période. */
    public function withoutPeriod(): self
    {
        return $this->period === null ? $this : new self($this->organizationId, $this->today);
    }

    /**
     * Restreint une requête à la période, quand il y en a une.
     *
     * Le nom de colonne est écrit à l'appel, jamais reçu d'ailleurs : chaque
     * source sait sur quelle date **la sienne** se lit — `order_date` pour une
     * commande, `tour_date` pour une tournée, `sent_at` pour un envoi — et une
     * colonne choisie à l'extérieur serait une injection.
     *
     * Sans période, la requête repart telle quelle : le widget montre alors ce
     * qu'il montrait avant que le filtre existe.
     *
     * @template TQuery of object
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function restrict(object $query, string $column): object
    {
        if ($this->period !== null) {
            $query->whereBetween($column, $this->period->bounds());
        }

        return $query;
    }

    /**
     * Bornes d'une fenêtre de N jours **finissant aujourd'hui**, aujourd'hui
     * compris — ou celles de la période, quand elle est là.
     *
     * Le premier jour est donc `today - (days - 1)` : une fenêtre de sept jours
     * qui commencerait à `today - 7` en compterait huit, et le graphe montrerait
     * une colonne de plus que son titre n'en annonce.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function window(int $days): array
    {
        if ($this->period !== null) {
            return $this->period->bounds();
        }

        return [$this->today->subDays($days - 1), $this->today->endOfDay()];
    }

    public function windowStart(int $days): CarbonImmutable
    {
        return $this->period?->from ?? $this->today->subDays($days - 1);
    }

    /**
     * Nombre de colonnes que le graphe portera.
     *
     * La période l'emporte sur la fenêtre par défaut du widget : demander une
     * semaine et recevoir quatorze colonnes, dont sept vides, dirait que rien
     * ne s'est passé sur des jours qu'on n'a pas demandés.
     */
    public function windowDays(int $days): int
    {
        return $this->period?->days() ?? $days;
    }

    /**
     * Bornes d'un compteur daté : **la période, sinon la journée**.
     *
     * C'est ce qui permet à « Commandes du jour » de devenir « Commandes de la
     * période » sans changer de branche : la requête est la même, seules les
     * bornes bougent. Le libellé, lui, suit — `periodLabelKey` existe pour
     * cela, faute de quoi la carte annoncerait le jour en comptant le mois.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function bounds(): array
    {
        return $this->period?->bounds() ?? $this->dayBounds();
    }

    /**
     * Bornes de la journée, telles qu'un `whereBetween` les attend.
     *
     * `whereDate()` aurait été plus court, et aurait écarté l'index : la
     * fonction s'applique à la colonne, et MySQL ne peut plus s'en servir. Sur
     * une table de commandes qui grossit, la différence n'est pas théorique.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function dayBounds(): array
    {
        return [$this->today, $this->today->endOfDay()];
    }
}
