<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Stock\Actions\CreateStockAdjustmentRequestAction;
use App\Modules\Stock\Actions\CreateStockMovementAction;
use App\Modules\Stock\DTOs\CreateStockMovementData;
use App\Modules\Stock\Models\StockAdjustmentRequest;
use App\Modules\Stock\Models\StockBalance;
use App\Modules\Stock\Models\StockItem;
use App\Modules\Stock\Models\StockLocation;
use App\Shared\Support\AuditContext;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Demandes de correction pour les tests du traitement interne (Phase 5b).
 *
 * Les demandes naissent comme au portail — par l'Action de création, au nom
 * d'un membre IKEA — et le stock bouge par l'Action de mouvement : aucune
 * balance n'est posée à la main.
 */
final class StockAdjustments
{
    public static function request(StockItem $item, ?StockLocation $location, string $requested, string $email = 'admin@ikea.test'): StockAdjustmentRequest
    {
        return app(CreateStockAdjustmentRequestAction::class)->execute(
            $item, $location, $requested, 'Inventaire du 30 septembre', 'Deux cartons abîmés',
            new AuditContext($item->customer->organization_id, CustomerPortal::member($email)->user),
        );
    }

    /** Sortie de stock hors demande : le stock bouge entre la demande et la décision. */
    public static function withdraw(StockItem $item, StockLocation $location, string $quantity): void
    {
        app(CreateStockMovementAction::class)->execute(
            new CreateStockMovementData($item->id, 'outbound', $quantity, sourceLocationId: $location->id),
            new AuditContext($location->depot->agency->organization_id),
            now()->toDateTimeString(),
        );
    }

    public static function balance(StockItem $item, StockLocation $location): string
    {
        return (string) StockBalance::where('stock_item_id', $item->id)->where('stock_location_id', $location->id)->value('quantity');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function approve(StockAdjustmentRequest $request, array $payload = []): TestResponse
    {
        return self::internal()->postJson("/api/v1/stock-adjustment-requests/{$request->id}/approve", $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function reject(StockAdjustmentRequest $request, array $payload = ['reviewComment' => 'Recompté : le stock est juste.']): TestResponse
    {
        return self::internal()->postJson("/api/v1/stock-adjustment-requests/{$request->id}/reject", $payload);
    }

    /**
     * L'administrateur du transporteur, dans son organisation.
     *
     * @return TestCase
     */
    public static function internal(): mixed
    {
        app('auth')->forgetGuards();

        return test()->actingAs(authUser(), 'sanctum')->withHeaders(['X-Organization-Id' => authOrganization()->id]);
    }
}
