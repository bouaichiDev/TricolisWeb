<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Controllers\Api\V1\Orders\Concerns\ResolvesOrderScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\GenerateDeliveryNoteRequest;
use App\Http\Resources\Api\V1\Documents\DocumentResource;
use App\Http\Resources\Api\V1\Orders\DeliveryNoteTemplateResource;
use App\Modules\DeliveryNotes\Actions\GenerateDeliveryNoteAction;
use App\Modules\DeliveryNotes\Actions\RenderDeliveryNoteAction;
use App\Modules\DeliveryNotes\Exceptions\NoDeliveryNoteTemplate;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Templates\Exceptions\TemplateRenderingFailed;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Le bon de livraison d'un service de commande.
 *
 * **La configuration est ailleurs.** La mise en page se règle dans « Modèles » ;
 * ici, on choisit un modèle applicable et on produit le document renseigné.
 * C'est la séparation demandée, et elle évite qu'un exploitant pressé retouche
 * la mise en page de tous les BL depuis une commande.
 *
 * Le HTML est rendu par le **moteur unique**, jamais reconstruit côté
 * navigateur : un second moteur en JavaScript montrerait un aperçu différent du
 * PDF remis au client, et l'écart ne se verrait qu'après la remise.
 *
 * Trois gestes, trois routes : lister les modèles applicables, voir le
 * document, l'enregistrer en PDF.
 */
class OrderServiceDeliveryNoteController extends Controller
{
    use ResolvesOrderScope;

    /**
     * Lister les modèles de BL applicables à ce service.
     *
     * Permission requise : `delivery_notes.view`. Ne sont proposés que les
     * modèles **actifs**, du type `delivery_note`, et dont la portée couvre ce
     * client et cette prestation — jamais celui d'un autre client. Le premier
     * de la liste est celui que la génération retiendrait sans choix explicite ;
     * `defaultTemplateId` le nomme.
     */
    public function templates(Order $order, OrderService $orderService, RenderDeliveryNoteAction $action): JsonResponse
    {
        $this->guard($order, $orderService, 'view');

        $candidates = $action->candidates($orderService);

        return ApiResponse::ok(
            DeliveryNoteTemplateResource::collection($candidates)->resolve(),
            ['defaultTemplateId' => $candidates->first()?->id],
        );
    }

    /**
     * Prévisualiser le bon de livraison.
     *
     * Permission requise : `delivery_notes.view`. Le document est rendu depuis
     * le modèle **tel qu'il est enregistré** : c'est ce que la génération
     * produira. Sans modèle applicable, 409 — la commande existe, c'est la
     * configuration qui manque.
     */
    public function show(
        Request $request,
        Order $order,
        OrderService $orderService,
        RenderDeliveryNoteAction $action,
    ): JsonResponse {
        $this->guard($order, $orderService, 'view');

        $templateId = $request->query('templateId');

        try {
            $document = $action->execute($orderService, is_string($templateId) ? $templateId : null);
        } catch (NoDeliveryNoteTemplate $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        } catch (TemplateRenderingFailed $exception) {
            return ApiResponse::validationError($exception->getMessage(), ['bodyTemplate' => [$exception->getMessage()]]);
        }

        return ApiResponse::ok([
            'html' => $document->html,
            'number' => $document->number,
            'templateId' => $document->templateId,
            'templateCode' => $document->templateCode,
            'templateName' => $document->templateName,
            'scope' => $document->scope,
        ]);
    }

    /**
     * Générer le PDF et l'enregistrer dans les documents.
     *
     * Permission requise : `delivery_notes.generate`. Le PDF est rattaché à la
     * commande **et** au service. Chaque appel produit un nouveau document :
     * un bon déjà remis ne se réécrit pas parce que le modèle a changé depuis.
     */
    public function store(
        GenerateDeliveryNoteRequest $request,
        Order $order,
        OrderService $orderService,
        GenerateDeliveryNoteAction $action,
    ): JsonResponse {
        $this->guard($order, $orderService, 'generate');

        try {
            $document = $action->execute(
                $orderService,
                $request->validated('templateId'),
                $request->user(),
                $request,
            );
        } catch (NoDeliveryNoteTemplate $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        } catch (TemplateRenderingFailed $exception) {
            return ApiResponse::validationError($exception->getMessage(), ['bodyTemplate' => [$exception->getMessage()]]);
        }

        return ApiResponse::created(new DocumentResource($document->load('links')));
    }

    /**
     * Le service appartient-il bien à cette commande, et l'appelant a-t-il le
     * droit ?
     *
     * Le hors-périmètre répond 404 et non 403 : un 403 confirmerait que
     * l'identifiant existe dans une autre organisation.
     */
    private function guard(Order $order, OrderService $orderService, string $action): void
    {
        $this->guardOrder($order);
        $this->guardBelongsToOrder($order, $orderService, 'Service');
        $this->authorize('deliveryNote', [$order, $action]);
    }
}
