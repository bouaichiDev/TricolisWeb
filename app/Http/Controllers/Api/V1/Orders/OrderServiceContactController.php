<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Controllers\Api\V1\Orders\Concerns\ResolvesOrderScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\StoreOrderServiceContactRequest;
use App\Http\Resources\Api\V1\Orders\OrderServiceContactResource;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\OrderServiceContact;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contacts d'un service de commande.
 *
 * Les informations utiles sont recopiées dans la liaison : une modification
 * ultérieure du contact partagé n'altère pas les commandes déjà passées.
 * Un seul contact principal est admis par rôle et par service.
 */
class OrderServiceContactController extends Controller
{
    use ResolvesOrderScope;

    /**
     * Lister les contacts d'un service.
     *
     * Permission requise : `order_services.view`.
     */
    public function index(Request $request, Order $order, OrderService $orderService): JsonResponse
    {
        $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $orderService, 'Service');
        $this->authorize('manageServices', [$order, 'view']);

        return ApiResponse::ok(OrderServiceContactResource::collection($orderService->contacts()->get()));
    }

    /**
     * Ajouter un contact à un service.
     *
     * Permission requise : `order_services.update`. `contactId` rattache un
     * contact partagé et recopie ses informations ; sans lui, les valeurs
     * fournies constituent un contact ponctuel, propre à cette commande.
     */
    public function store(StoreOrderServiceContactRequest $request, Order $order, OrderService $orderService): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $orderService, 'Service');
        $this->authorize('manageServices', [$order, 'update']);

        $data = $request->validated();
        $attributes = $this->buildAttributes($data, $organizationId);

        $contact = DB::transaction(function () use ($orderService, $attributes) {
            $this->demoteOtherPrimaries($orderService, $attributes);

            return $orderService->contacts()->create($attributes);
        });

        $this->audit($request, $organizationId, 'created', $contact, null, $contact->toArray());

        return ApiResponse::created(new OrderServiceContactResource($contact));
    }

    /**
     * Modifier un contact de service.
     *
     * Permission requise : `order_services.update`.
     */
    public function update(StoreOrderServiceContactRequest $request, Order $order, OrderService $orderService, OrderServiceContact $contact): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $orderService, 'Service');
        $this->assertBelongsToService($orderService, $contact);
        $this->authorize('manageServices', [$order, 'update']);

        $old = $contact->toArray();
        $attributes = $this->buildAttributes($request->validated(), $organizationId);

        DB::transaction(function () use ($orderService, $contact, $attributes): void {
            $this->demoteOtherPrimaries($orderService, $attributes, $contact->id);
            $contact->update($attributes);
        });

        $this->audit($request, $organizationId, 'updated', $contact, $old, $contact->fresh()->toArray());

        return ApiResponse::ok(new OrderServiceContactResource($contact->fresh()));
    }

    /**
     * Retirer un contact d'un service.
     *
     * Permission requise : `order_services.update`.
     *
     * @response 204
     */
    public function destroy(Request $request, Order $order, OrderService $orderService, OrderServiceContact $contact): JsonResponse
    {
        $organizationId = $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $orderService, 'Service');
        $this->assertBelongsToService($orderService, $contact);
        $this->authorize('manageServices', [$order, 'update']);

        $this->audit($request, $organizationId, 'deleted', $contact, $contact->toArray(), null);
        $contact->delete();

        return ApiResponse::noContent();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildAttributes(array $data, string $organizationId): array
    {
        $attributes = [
            'contact_role' => $data['contactRole'] ?? 'other',
            'is_primary' => $data['isPrimary'] ?? false,
            'first_name_snapshot' => $data['firstName'] ?? null,
            'last_name_snapshot' => $data['lastName'] ?? null,
            'phone_snapshot' => $data['phone'] ?? null,
            'mobile_snapshot' => $data['mobile'] ?? null,
            'email_snapshot' => $data['email'] ?? null,
        ];

        if (isset($data['contactId'])) {
            $shared = Contact::whereKey($data['contactId'])
                ->whereHas('entityContacts', fn ($query) => $query->where('organization_id', $organizationId))
                ->first();

            abort_if($shared === null, 404, 'Contact introuvable dans l’organisation active.');

            $attributes['contact_id'] = $shared->id;
            $attributes = array_merge(
                OrderServiceContact::snapshotFrom($shared),
                array_filter($attributes, static fn ($value): bool => $value !== null),
            );
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function demoteOtherPrimaries(OrderService $service, array $attributes, ?string $ignoreId = null): void
    {
        if (($attributes['is_primary'] ?? false) !== true) {
            return;
        }

        $service->contacts()
            ->where('contact_role', $attributes['contact_role'])
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->update(['is_primary' => false]);
    }

    private function assertBelongsToService(OrderService $service, OrderServiceContact $contact): void
    {
        abort_unless($contact->order_service_id === $service->id, 404, 'Contact introuvable pour ce service.');
    }
}
