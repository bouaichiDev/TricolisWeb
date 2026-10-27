<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Orders;

use App\Http\Controllers\Api\V1\Customer\Concerns\ResolvesCustomerOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Orders\StoreCustomerOrderDocumentRequest;
use App\Modules\CustomerPortal\Services\CurrentCustomerContext;
use App\Modules\CustomerPortal\Services\CustomerPortalAudit;
use App\Modules\CustomerPortal\Services\Documents\CustomerDocumentFiles;
use App\Modules\Documents\Actions\AttachOrderDocument;
use App\Modules\Documents\Models\Document;
use App\Modules\Orders\Models\Order;
use App\Shared\Database\MorphMap;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documents d'une commande du client.
 *
 * Connaître un identifiant de document ne suffit jamais : le chemin est
 * toujours `Document → DocumentLink → Order → Customer courant`. Un document
 * existant mais rattaché à la commande d'un autre client répond 404.
 */
class CustomerOrderDocumentController extends Controller
{
    use ResolvesCustomerOrder;

    public function __construct(private readonly CurrentCustomerContext $context) {}

    /**
     * Permission : `customer.orders.documents.view`.
     */
    public function index(string $order): JsonResponse
    {
        $documents = $this->documents($this->order($this->context, $order))->latest()->get();

        return ApiResponse::ok($documents->map(fn (Document $document): array => $this->present($document))->all());
    }

    /**
     * Permission : `customer.orders.documents.view`.
     */
    public function download(string $order, string $document): StreamedResponse
    {
        abort_unless(Str::isUlid($document), 404);

        $file = $this->documents($this->order($this->context, $order))->whereKey($document)->firstOrFail();

        // Même service que `/documents/{id}/download` : fichier présent, type
        // MIME d'origine, nom sans séparateur de chemin.
        return CustomerDocumentFiles::download($file);
    }

    /**
     * Permission : `customer.orders.documents.upload`.
     */
    public function store(StoreCustomerOrderDocumentRequest $request, string $order, AttachOrderDocument $attach, CustomerPortalAudit $audit): JsonResponse
    {
        $model = $this->order($this->context, $order);
        $document = $attach->execute($model, $request->file('file'), [
            'documentType' => 'customer_upload',
            'status' => 'received',
            'referenceNumber' => $request->validated('referenceNumber'),
            'receivedAt' => now()->toDateTimeString(),
        ], $this->context->user(), $request->ip());

        $audit->record($this->context->customer(), $this->context->user(), 'order_document_uploaded', $model, null, [
            'orderId' => $model->id,
            'documentId' => $document->id,
        ], $request->ip());

        return ApiResponse::created($this->present($document));
    }

    /**
     * @return Builder<Document>
     */
    private function documents(Order $order): Builder
    {
        return Document::query()
            ->where('organization_id', $order->organization_id)
            ->whereHas('links', static fn ($query) => $query->where('entity_type', MorphMap::ORDER)->where('entity_id', $order->id));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Document $document): array
    {
        return [
            'id' => $document->id,
            'fileName' => $document->file_name,
            'documentType' => $document->document_type,
            'referenceNumber' => $document->reference_number,
            'mimeType' => $document->mime_type,
            'size' => $document->size,
            'createdAt' => $document->created_at?->toIso8601String(),
        ];
    }
}
