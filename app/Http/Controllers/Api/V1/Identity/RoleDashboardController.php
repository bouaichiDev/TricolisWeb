<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Dashboard\UpdateRoleDashboardRequest;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RoleDashboardConfiguration;
use App\Modules\Identity\Services\RoleDashboardCatalogue;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Le tableau de bord d'un rôle : ce qu'il montre, et dans quel ordre.
 *
 * C'est le seul endroit où il se règle, et il se règle **sur la fiche du
 * rôle** — à côté des permissions, parce qu'on les pense ensemble : un rôle qui
 * gagne `invoices.view` veut souvent la carte qui va avec, et celui qui la perd
 * n'a rien à faire de la carte.
 *
 * Les deux réglages restent pourtant indépendants, et dans un seul sens :
 * **activer un widget n'accorde jamais rien**. La permission décide, à chaque
 * chargement du tableau de bord. Retirer `invoices.view` fait disparaître la
 * carte sans que personne ait à toucher à cette configuration.
 *
 * Lire demande `dashboard.configure`, comme écrire. C'est un écart assumé avec
 * `RoleMenuController`, où lire ne demande que `view` : le menu d'un rôle
 * s'affiche sur sa fiche pour tout le monde, alors que cet onglet-ci n'a de
 * sens que pour qui peut le régler — sans la permission, il ne montrerait
 * qu'une liste d'interrupteurs inertes.
 */
class RoleDashboardController extends Controller
{
    public function __construct(private readonly RoleDashboardCatalogue $catalogue) {}

    /**
     * Le catalogue entier, avec l'état de ce rôle.
     *
     * Y compris les widgets qu'il ne peut pas voir, marqués `availableForRole:
     * false` : les retirer aurait laissé croire qu'ils n'existent pas, quand il
     * ne manque qu'une permission — et l'écran des permissions est à côté.
     */
    public function index(Role $role): JsonResponse
    {
        $this->authorize('configureDashboard', $role);

        return ApiResponse::ok($this->catalogue->forRole($role));
    }

    /**
     * Remplacer la sélection, d'un bloc.
     *
     * `PUT`, et non `PATCH` : la liste envoyée **est** la configuration, widgets
     * décochés compris — par leur absence. Une mise à jour partielle aurait
     * demandé de distinguer « je ne parle pas de ce widget » de « je le
     * retire », ce qu'une liste de clés ne sait pas dire.
     */
    public function update(UpdateRoleDashboardRequest $request, Role $role): JsonResponse
    {
        $this->authorize('configureDashboard', $role);

        $before = $this->catalogue->forRole($role);

        RoleDashboardConfiguration::updateOrCreate(
            ['role_id' => $role->id],
            ['widgets' => $this->normalize($request->validated('widgets'))],
        );

        $after = $this->catalogue->forRole($role);
        $this->auditDashboard($request, $role, 'role_dashboard_updated', $before, $after);

        return ApiResponse::ok($after);
    }

    /**
     * Revenir aux défauts du catalogue.
     *
     * En **supprimant** la ligne, et non en la vidant. Une ligne vide dirait
     * « ce rôle ne voit rien », soit l'inverse de ce qu'on demande ici ; son
     * absence dit « rien de choisi », ce qui laisse le catalogue décider — et
     * fait profiter le rôle des widgets ajoutés plus tard.
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorize('configureDashboard', $role);

        $before = $this->catalogue->forRole($role);

        RoleDashboardConfiguration::where('role_id', $role->id)->delete();

        $after = $this->catalogue->forRole($role);
        $this->auditDashboard($request, $role, 'role_dashboard_reset', $before, $after);

        return ApiResponse::ok($after);
    }

    /**
     * Ne garder que la clé et le rang.
     *
     * La validation a déjà écarté les clés inconnues ; ce filtre écarte les
     * champs **en trop**. Sans lui, un client pourrait joindre un `label`, une
     * `route` ou un `resolver` à chaque entrée, et la colonne JSON les
     * conserverait — pour ressortir un jour dans une réponse, ou sous les yeux
     * de quelqu'un qui les croirait pris en compte.
     *
     * @param  array<int, array<string, mixed>>  $widgets
     * @return array<int, array{key: string, position: int}>
     */
    private function normalize(array $widgets): array
    {
        $normalized = array_map(
            static fn (array $widget): array => [
                'key' => (string) $widget['key'],
                'position' => (int) $widget['position'],
            ],
            $widgets,
        );

        usort($normalized, static fn (array $a, array $b): int => [$a['position'], $a['key']] <=> [$b['position'], $b['key']]);

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $before
     * @param  array<int, array<string, mixed>>  $after
     */
    private function auditDashboard(Request $request, Role $role, string $action, array $before, array $after): void
    {
        if ($role->organization_id === null) {
            return;
        }

        // Seules les sélections sont journalisées, pas le catalogue entier :
        // une entrée de journal de cinquante lignes dont deux ont changé se
        // relit mal, et c'est précisément quand on la relit qu'elle sert.
        $this->audit(
            $request,
            $role->organization_id,
            $action,
            $role,
            ['dashboard' => $this->enabledKeys($before)],
            ['dashboard' => $this->enabledKeys($after)],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, string>
     */
    private function enabledKeys(array $items): array
    {
        return array_values(array_map(
            static fn (array $item): string => (string) $item['key'],
            array_filter($items, static fn (array $item): bool => (bool) $item['isEnabled']),
        ));
    }
}
