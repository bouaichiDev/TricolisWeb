<?php

declare(strict_types=1);

namespace App\Modules\DeliveryNotes\Actions;

use App\Modules\DeliveryNotes\DTOs\RenderedDeliveryNote;
use App\Modules\DeliveryNotes\Exceptions\NoDeliveryNoteTemplate;
use App\Modules\DeliveryNotes\Services\DeliveryNoteRenderContext;
use App\Modules\Orders\Models\OrderService;
use App\Modules\Templates\Actions\ResolveTemplateAction;
use App\Modules\Templates\DTOs\TemplateQuery;
use App\Modules\Templates\Models\Template;
use App\Modules\Templates\Services\TemplateRenderer;
use Illuminate\Database\Eloquent\Collection;

/**
 * Le bon de livraison d'un service, en HTML.
 *
 * **Toujours depuis le modèle du moment.** Rien n'est figé ici : l'aperçu comme
 * la génération lisent le modèle tel qu'il est enregistré. Ce qui est figé,
 * c'est le **PDF déjà produit** — un fichier sur le disque, que retoucher le
 * modèle ne réécrit pas. C'est la lecture littérale de la demande : « une
 * modification du modèle s'applique aux nouvelles générations, sans modifier
 * les PDF déjà enregistrés. »
 *
 * **Aucun repli sur une mise en page livrée.** Une facture en a un, parce que
 * refuser aurait cassé la facturation de toutes les organisations le jour de la
 * migration. Un BL n'a pas cet historique : il n'a jamais été produit sans
 * modèle, et lui inventer une mise en page ferait remettre au destinataire un
 * document que personne n'a relu.
 *
 * Le **numéro** est déterministe — `BL-<commande>-<service>`. Deux générations
 * du même service portent donc le même numéro, ce qui est exact : c'est le même
 * bon, réédité. Une séquence aurait fait croire à deux livraisons.
 */
final readonly class RenderDeliveryNoteAction
{
    public function __construct(
        private ResolveTemplateAction $resolve,
        private TemplateRenderer $renderer,
        private DeliveryNoteRenderContext $context,
    ) {}

    /**
     * @throws NoDeliveryNoteTemplate
     */
    public function execute(OrderService $service, ?string $templateId = null): RenderedDeliveryNote
    {
        $template = $this->choose($service, $templateId);
        $number = $this->number($service);

        $rendered = $this->renderer->renderDocument($template, $this->context->build($service, $number));

        return RenderedDeliveryNote::fromTemplate($rendered->body, $number, $template);
    }

    /**
     * Les modèles employables pour ce service, le premier étant celui que
     * `execute()` retiendrait sans choix explicite.
     *
     * @return Collection<int, Template>
     */
    public function candidates(OrderService $service): Collection
    {
        $service->loadMissing('order');

        return $this->resolve->candidates(TemplateQuery::forDeliveryNote(
            organizationId: (string) $service->order?->organization_id,
            customerId: $service->order?->customer_id,
            serviceId: $service->service_id,
        ));
    }

    /**
     * Le modèle demandé, s'il est employable ; sinon celui que la résolution
     * choisit.
     *
     * Un identifiant hors de cette liste est **refusé**, jamais remplacé en
     * silence par le modèle par défaut : l'utilisateur croirait avoir généré le
     * BL qu'il a choisi.
     *
     * @throws NoDeliveryNoteTemplate
     */
    private function choose(OrderService $service, ?string $templateId): Template
    {
        $candidates = $this->candidates($service);

        if ($candidates->isEmpty()) {
            throw NoDeliveryNoteTemplate::forService((string) $service->service_number);
        }

        if ($templateId === null) {
            /** @var Template $first */
            $first = $candidates->first();

            return $first;
        }

        $chosen = $candidates->firstWhere('id', $templateId);

        if (! $chosen instanceof Template) {
            throw NoDeliveryNoteTemplate::notApplicable();
        }

        return $chosen;
    }

    private function number(OrderService $service): string
    {
        $service->loadMissing('order');

        return 'BL-'.($service->order?->order_number ?? '?').'-'.$service->service_number;
    }
}
