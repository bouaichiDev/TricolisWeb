<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Organizations;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Services\MenuResolver;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Menu de navigation de l'utilisateur connecté.
 *
 * Une seule route, en lecture. **Le menu se règle sur le rôle** — voir
 * `RoleMenuController` — et non plus ici : il se réglait à deux niveaux, et il
 * fallait savoir lequel ouvrir pour obtenir quoi.
 */
class MenuController extends Controller
{
    public function __construct(private readonly MenuResolver $menu) {}

    /**
     * Menu effectif de l'utilisateur connecté.
     *
     * Aucune permission métier : le menu **est** la projection des rôles et des
     * permissions de l'appelant. Une entrée qu'il n'a pas le droit d'ouvrir n'y
     * figure pas.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::ok($this->menu->resolve($user, $this->organizationId()));
    }
}
