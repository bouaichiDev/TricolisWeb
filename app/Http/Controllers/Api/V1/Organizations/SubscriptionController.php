<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Organizations\StoreSubscriptionRequest;
use App\Http\Requests\Api\V1\Organizations\UpdateSubscriptionRequest;
use App\Http\Resources\Api\V1\Organizations\SubscriptionResource;
use App\Modules\Organizations\Enums\SubscriptionStatus;
use App\Modules\Organizations\Models\Subscription;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Abonnement de l'organisation active.
 *
 * Le diagramme définit `Organization 1 — 0..1 Subscription` : la ressource est
 * donc un singleton, sans identifiant dans l'URL. L'organisation visée est
 * toujours celle de l'en-tête `X-Organization-Id`.
 */
class SubscriptionController extends Controller
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'plan_code' => 'planCode',
        'status' => 'status',
        'starts_at' => 'startsAt',
        'ends_at' => 'endsAt',
        'trial_ends_at' => 'trialEndsAt',
    ];

    /**
     * Consulter l'abonnement de l'organisation active.
     *
     * Permission requise : `subscriptions.view`. Renvoie 404 tant qu'aucun
     * abonnement n'a été souscrit.
     */
    public function show(Request $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [Subscription::class, $organizationId]);

        $subscription = Subscription::where('organization_id', $organizationId)->first();

        if ($subscription === null) {
            return ApiResponse::error('Aucun abonnement pour cette organisation.', 404);
        }

        return ApiResponse::ok(new SubscriptionResource($subscription));
    }

    /**
     * Souscrire l'abonnement de l'organisation active.
     *
     * Permission requise : `subscriptions.create`. Une organisation ne peut
     * porter qu'un seul abonnement : un second appel renvoie 409.
     */
    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('create', [Subscription::class, $organizationId]);

        if (Subscription::where('organization_id', $organizationId)->exists()) {
            return ApiResponse::error('Cette organisation possède déjà un abonnement.', 409);
        }

        $data = InputMapper::map($request->validated(), self::MAPPING);
        $data['organization_id'] = $organizationId;
        $data['status'] ??= SubscriptionStatus::ACTIVE->value;

        $subscription = Subscription::create($data);
        $this->audit($request, $organizationId, 'created', $subscription, null, $subscription->toArray());

        return ApiResponse::created(new SubscriptionResource($subscription));
    }

    /**
     * Modifier l'abonnement de l'organisation active.
     *
     * Permission requise : `subscriptions.update`. Un changement de statut est
     * audité avec son ancienne et sa nouvelle valeur.
     */
    public function update(UpdateSubscriptionRequest $request): JsonResponse
    {
        $subscription = $this->requireSubscription();
        $this->authorize('update', $subscription);

        $oldValues = $subscription->toArray();
        $subscription->update(InputMapper::map($request->validated(), self::MAPPING));

        $action = $request->has('status') ? 'status_changed' : 'updated';
        $this->audit($request, $subscription->organization_id, $action, $subscription, $oldValues, $subscription->fresh()->toArray());

        return ApiResponse::ok(new SubscriptionResource($subscription->fresh()));
    }

    /**
     * Supprimer l'abonnement de l'organisation active.
     *
     * Permission requise : `subscriptions.delete`. Pour conserver l'historique,
     * préférer un passage au statut `cancelled` via `PATCH`.
     *
     * @response 204
     */
    public function destroy(Request $request): JsonResponse
    {
        $subscription = $this->requireSubscription();
        $this->authorize('delete', $subscription);

        $this->audit($request, $subscription->organization_id, 'deleted', $subscription, $subscription->toArray(), null);
        $subscription->delete();

        return ApiResponse::noContent();
    }

    private function requireSubscription(): Subscription
    {
        $organizationId = $this->requireOrganizationId();

        $subscription = Subscription::where('organization_id', $organizationId)->first();

        abort_if($subscription === null, 404, 'Aucun abonnement pour cette organisation.');

        return $subscription;
    }
}
