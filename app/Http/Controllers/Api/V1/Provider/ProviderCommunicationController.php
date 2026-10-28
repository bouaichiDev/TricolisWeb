<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Provider\ProviderCommunicationResource;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\ProviderPortal\Services\ProviderCommunications;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Communications reçues par le membre connecté, en lecture seule.
 */
class ProviderCommunicationController extends Controller
{
    public function __construct(private readonly ProviderCommunications $communications) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('perPage', '25')));
        $rows = $this->communications->query()->orderByDesc('sent_at')->orderByDesc('id')->paginate($perPage);

        return ApiResponse::paginated($rows->through(static fn (OrderCommunication $row) => (new ProviderCommunicationResource($row))->resolve()));
    }

    public function show(string $communication): JsonResponse
    {
        return ApiResponse::ok(new ProviderCommunicationResource($this->communications->find($communication)->load('attachments')));
    }
}
