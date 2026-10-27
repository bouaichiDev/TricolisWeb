<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Provider\ChangeProviderPasswordRequest;
use App\Http\Requests\Api\V1\Provider\UpdateProviderProfileRequest;
use App\Http\Resources\Api\V1\Provider\ProviderProfileResource;
use App\Modules\Identity\Actions\UpdateUserProfile;
use App\Modules\ProviderPortal\Actions\ChangeProviderPassword;
use App\Modules\ProviderPortal\Services\CurrentProviderContext;
use App\Modules\ProviderPortal\Services\ProviderNavigation;
use App\Modules\ProviderPortal\Services\ProviderPortalAudit;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Navigation, profil et mot de passe du membre connecté.
 */
class ProviderContextController extends Controller
{
    public function __construct(private readonly CurrentProviderContext $context) {}

    /**
     * Les entrées de menu que les permissions ouvrent.
     */
    public function navigation(): JsonResponse
    {
        return ApiResponse::ok(ProviderNavigation::for($this->context));
    }

    /**
     * La fiche du fournisseur (lecture seule) et le compte connecté.
     * Permission : `provider.profile.view`.
     */
    public function profile(): JsonResponse
    {
        return ApiResponse::ok(new ProviderProfileResource($this->context));
    }

    /**
     * Modifier son propre profil : nom, téléphone, langue.
     */
    public function updateProfile(UpdateProviderProfileRequest $request, UpdateUserProfile $update, ProviderPortalAudit $audit): JsonResponse
    {
        $user = $this->context->user();
        $changes = $update->execute($user, $request->validated());

        if ($changes['old'] !== $changes['new']) {
            $audit->record($this->context->provider(), $user, 'profile_updated', $this->context->membership(), $changes['old'], $changes['new'], $request->ip());
        }

        return ApiResponse::ok(new ProviderProfileResource($this->context));
    }

    /**
     * Changer son mot de passe ; les autres sessions du portail sont fermées.
     */
    public function changePassword(ChangeProviderPasswordRequest $request, ChangeProviderPassword $change): JsonResponse
    {
        $change->execute(
            $this->context->membership(),
            $request->user()->currentAccessToken(),
            (string) $request->validated('currentPassword'),
            (string) $request->validated('password'),
            $request->ip(),
        );

        return ApiResponse::ok(['message' => 'Votre mot de passe est modifié.']);
    }
}
