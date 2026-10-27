<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Integrations\CustomerImportRunResource;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Models\CustomerImportRun;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * L'historique d'une configuration d'import, dans le back-office : chaque
 * exécution, son bilan, et — sur la fiche — les commandes refusées.
 *
 * Permission : `customer_import_configurations.view` sur la configuration.
 */
final class ImportRunController extends Controller
{
    use ResolvesCustomerScope;

    public function index(Request $request, CustomerImportConfiguration $configuration): JsonResponse
    {
        $this->guardCustomerOwned($configuration);
        $this->authorize('view', $configuration);

        $perPage = (int) ($request->validate(['perPage' => ['sometimes', 'integer', 'min:1', 'max:100']])['perPage'] ?? 20);

        $page = CustomerImportRun::query()
            ->where('configuration_id', $configuration->id)
            ->with('trigger')
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::paginated($page->through(
            static fn (CustomerImportRun $run): CustomerImportRunResource => new CustomerImportRunResource($run),
        ));
    }

    public function show(CustomerImportConfiguration $configuration, string $run): JsonResponse
    {
        $this->guardCustomerOwned($configuration);
        $this->authorize('view', $configuration);

        $found = CustomerImportRun::query()
            ->where('configuration_id', $configuration->id)
            ->with('trigger')
            ->whereKey($run)
            ->firstOrFail();

        return ApiResponse::ok(new CustomerImportRunResource($found, withErrors: true));
    }
}
