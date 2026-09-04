<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

use Carbon\CarbonImmutable;

/**
 * Ce dont un résolveur a besoin, et rien de plus.
 *
 * Il reçoit l'organisation et le jour — pas l'utilisateur. C'est délibéré :
 * l'autorisation est déjà tranchée quand un résolveur s'exécute, et lui donner
 * de quoi la rejuger inviterait à la rejuger différemment. Un résolveur qui
 * aurait accès aux permissions finirait par en tenir compte, et la règle
 * cesserait d'être appliquée à un seul endroit.
 *
 * Le jour est figé à la construction. Une requête qui traverse minuit ferait
 * sinon compter « aujourd'hui » deux jours différents selon le widget, et les
 * chiffres d'une même page ne se recouperaient pas.
 */
final readonly class DashboardContext
{
    public function __construct(
        public string $organizationId,
        public CarbonImmutable $today,
    ) {}

    public static function forOrganization(string $organizationId): self
    {
        return new self($organizationId, CarbonImmutable::now()->startOfDay());
    }

    /**
     * Bornes d'une fenêtre de N jours **finissant aujourd'hui**, aujourd'hui
     * compris.
     *
     * Le premier jour est donc `today - (days - 1)` : une fenêtre de sept jours
     * qui commencerait à `today - 7` en compterait huit, et le graphe montrerait
     * une colonne de plus que son titre n'en annonce.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function window(int $days): array
    {
        $start = $this->today->subDays($days - 1);

        return [$start, $this->today->endOfDay()];
    }

    public function windowStart(int $days): CarbonImmutable
    {
        return $this->today->subDays($days - 1);
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
