<?php

declare(strict_types=1);

namespace App\Modules\DeliveryNotes\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\DeliveryNotes\DTOs\RenderedDeliveryNote;
use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\OrderService;
use App\Shared\Database\MorphMap;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Le BL sur du papier, puis dans les documents de la commande.
 *
 * **Deux liaisons, pas une.** Le document est rattaché à la commande *et* au
 * service : l'onglet Documents d'une commande doit les montrer tous, et la
 * fiche d'un service ne doit montrer que le sien. Une seule liaison aurait
 * forcé l'un des deux écrans à filtrer sur le nom du fichier.
 *
 * **Le fichier est écrit avant la transaction, effacé si elle échoue.** C'est
 * la règle déjà suivie par le téléversement : un `Storage::put` ne se défait
 * pas par un `rollBack`, et laisser un orphelin sur le disque à chaque échec
 * remplit le volume sans que rien ne le signale.
 *
 * Chaque génération produit **un nouveau document**. Les précédents restent :
 * un BL remis au client ne se réécrit pas parce que le modèle a changé depuis.
 */
final readonly class GenerateDeliveryNoteAction
{
    private const string PAPER = 'a4';

    public function __construct(
        private RenderDeliveryNoteAction $render,
        private WriteAuditLog $audit,
    ) {}

    public function execute(
        OrderService $service,
        ?string $templateId,
        User $user,
        Request $request,
    ): Document {
        $document = $this->render->execute($service, $templateId);
        $organizationId = (string) $service->order?->organization_id;

        $path = $this->store($organizationId, $document);

        try {
            return DB::transaction(
                fn (): Document => $this->record($service, $document, $path, $organizationId, $user, $request),
            );
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    /**
     * Le HTML rendu, mis en page A4.
     *
     * dompdf n'a ni session ni accès au réseau : toute ressource externe y
     * manquerait au rendu. C'est pourquoi le logo part encodé dans le HTML,
     * décidé en amont par le contexte.
     */
    private function store(string $organizationId, RenderedDeliveryNote $document): string
    {
        $pdf = Pdf::loadHTML($document->html)->setPaper(self::PAPER)->output();
        $path = "documents/{$organizationId}/".$this->fileName($document);

        Storage::disk('local')->put($path, $pdf);

        return $path;
    }

    private function record(
        OrderService $service,
        RenderedDeliveryNote $rendered,
        string $path,
        string $organizationId,
        User $user,
        Request $request,
    ): Document {
        $document = Document::create([
            'organization_id' => $organizationId,
            'reference_number' => $rendered->number,
            'document_type' => 'delivery_note',
            'status' => 'active',
            'file_name' => $this->downloadName($rendered),
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size' => Storage::disk('local')->size($path),
            'received_at' => now(),
            'created_by' => $user->id,
        ]);

        $document->links()->create(['entity_type' => MorphMap::ORDER, 'entity_id' => $service->order_id]);
        $document->links()->create(['entity_type' => MorphMap::ORDER_SERVICE, 'entity_id' => $service->id]);

        $this->audit->execute(
            $organizationId,
            $user,
            'delivery_note.generated',
            $document,
            null,
            ['referenceNumber' => $rendered->number, 'templateId' => $rendered->templateId],
            $request,
        );

        return $document;
    }

    /**
     * Un nom de fichier unique sur le disque.
     *
     * Le numéro seul se répéterait à chaque réédition, et le second fichier
     * écraserait le premier — soit exactement le PDF déjà remis au client.
     *
     * L'horodatage **ne suffit pas** : deux générations lancées dans la même
     * seconde retombent sur le même nom, et un test l'a montré. Le suffixe
     * aléatoire tranche ce cas ; l'horodatage reste pour qu'un fichier
     * retrouvé sur le disque se date sans requête.
     */
    private function fileName(RenderedDeliveryNote $document): string
    {
        return $this->slug($document->number)
            .'-'.now()->format('YmdHis')
            .'-'.Str::lower(Str::random(6))
            .'.pdf';
    }

    /** Le nom que le navigateur proposera : lisible, sans horodatage. */
    private function downloadName(RenderedDeliveryNote $document): string
    {
        return $this->slug($document->number).'.pdf';
    }

    private function slug(string $value): string
    {
        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?? 'BL';

        return trim($slug, '-') === '' ? 'BL' : trim($slug, '-');
    }
}
