<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer\Stock;

use App\Http\Controllers\Controller;
use App\Modules\CustomerPortal\Services\Stock\CustomerStockScope;
use App\Modules\CustomerPortal\Services\Stock\StockQuantity;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Résumé du stock du Customer courant (§9). Permission : `customer.stock.view`.
 *
 * Deux requêtes d'agrégation, quel que soit le volume : le nombre d'articles,
 * puis la somme des balances de ses articles. Rien n'est chargé en mémoire.
 */
class CustomerStockSummaryController extends Controller
{
    public function __invoke(CustomerStockScope $scope): JsonResponse
    {
        $totals = $scope->balances()
            ->selectRaw('SUM(quantity) AS quantity_total, SUM(reserved_quantity) AS reserved_total, SUM(available_quantity) AS available_total')
            ->toBase()
            ->first();

        return ApiResponse::ok([
            'itemsCount' => $scope->items()->count(),
            ...StockQuantity::totals($totals?->quantity_total, $totals?->reserved_total, $totals?->available_total),
        ]);
    }
}
