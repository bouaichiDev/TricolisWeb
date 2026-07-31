<?php

declare(strict_types=1);

namespace App\Modules\Orders\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Documents\Models\DocumentLink;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Packages\Models\Package;
use App\Modules\Packages\Models\PackageOrderLine;
use App\Shared\Database\MorphMap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Duplique une commande selon des options.
 *
 * Ne sont jamais copiés : le numéro de commande, l'historique des statuts,
 * l'audit, la planification, la facturation, les preuves de livraison et les
 * données d'exécution. La copie repart au statut initial du workflow.
 */
final readonly class DuplicateOrder
{
    public function __construct(
        private GenerateOrderNumber $numbers,
        private RecalculateOrderTotals $totals,
        private WriteAuditLog $audit,
    ) {}

    /**
     * @param  array{lines?: bool, packages?: bool, services?: bool, contacts?: bool, documents?: bool}  $options
     */
    public function execute(Order $source, array $options, User $user, ?Request $request = null): Order
    {
        $withLines = $options['lines'] ?? true;
        $withPackages = $options['packages'] ?? true;
        $withServices = $options['services'] ?? true;
        $withContacts = $options['contacts'] ?? true;
        $withDocuments = $options['documents'] ?? false;

        return DB::transaction(function () use ($source, $user, $request, $withLines, $withPackages, $withServices, $withContacts, $withDocuments): Order {
            $copy = Order::create($this->headerAttributes($source, $user));

            $lineMap = $withLines ? $this->copyLines($source, $copy) : [];
            $packageMap = $withPackages ? $this->copyPackages($source, $copy, $lineMap) : [];

            if ($withServices) {
                $this->copyServices($source, $copy, $packageMap, $withContacts);
            }

            if ($withDocuments) {
                $this->copyDocumentLinks($source, $copy);
            }

            $this->totals->execute($copy);

            $this->audit->execute($source->organization_id, $user, 'duplicated', $copy, null, [
                'source_order_id' => $source->id,
                'source_order_number' => $source->order_number,
                'order_number' => $copy->order_number,
            ], $request);

            return $copy;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function headerAttributes(Order $source, User $user): array
    {
        return [
            'organization_id' => $source->organization_id,
            'customer_id' => $source->customer_id,
            'agency_id' => $source->agency_id,
            'depot_id' => $source->depot_id,
            'order_number' => $this->numbers->execute($source->organization_id, (int) now()->format('Y')),
            'customer_reference' => $source->customer_reference,
            'order_type' => $source->order_type,
            'group_code' => $source->group_code,
            'order_date' => now(),
            'source' => $source->source,
            'internal_remark' => $source->internal_remark,
            'worker_remark' => $source->worker_remark,
            'currency_code' => $source->currency_code,
            'status' => OrderStatus::DRAFT,
            'created_by' => $user->id,
        ];
    }

    /** @return array<string, string> ancien identifiant => nouveau */
    private function copyLines(Order $source, Order $copy): array
    {
        $map = [];

        foreach ($source->lines()->get() as $line) {
            $attributes = $line->only([
                'catalog_item_id', 'external_reference', 'article_code', 'barcode', 'name', 'description',
                'quantity', 'weight', 'volume', 'length', 'width', 'height', 'purchase_price', 'selling_price',
            ]);
            // Les quantités d'exécution repartent à zéro.
            $map[$line->id] = $copy->lines()->create($attributes + ['status' => 'active'])->id;
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $lineMap
     * @return array<string, string>
     */
    private function copyPackages(Order $source, Order $copy, array $lineMap): array
    {
        $map = [];
        $packages = $source->packages()->orderByRaw('parent_package_id is not null')->get();

        foreach ($packages as $package) {
            $attributes = $package->only(['package_type_id', 'grouping_type_id', 'reference', 'description', 'quantity', 'weight', 'volume', 'length', 'width', 'height']);
            $attributes['parent_package_id'] = $package->parent_package_id === null ? null : ($map[$package->parent_package_id] ?? null);
            // Le code-barres identifie un colis physique : il n'est jamais copié.
            $new = $copy->packages()->create($attributes + ['barcode' => null, 'status' => 'draft']);
            $map[$package->id] = $new->id;

            $this->copyPackageLines($package, $new->id, $lineMap);
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $lineMap
     */
    private function copyPackageLines(Package $package, string $newPackageId, array $lineMap): void
    {
        foreach ($package->packageOrderLines()->get() as $allocation) {
            if (! isset($lineMap[$allocation->order_line_id])) {
                continue;
            }

            PackageOrderLine::create([
                'package_id' => $newPackageId,
                'order_line_id' => $lineMap[$allocation->order_line_id],
                'quantity' => $allocation->quantity,
            ]);
        }
    }

    /**
     * @param  array<string, string>  $packageMap
     */
    private function copyServices(Order $source, Order $copy, array $packageMap, bool $withContacts): void
    {
        foreach ($source->orderServices()->with(['contacts', 'servicePackages'])->get() as $service) {
            $attributes = $service->only([
                'service_id', 'address_id', 'sequence', 'requested_date', 'requested_from', 'requested_to',
                'quantity', 'unit', 'required_time_minutes', 'weight', 'volume', 'package_count',
                'customer_unit_price', 'customer_total_price', 'provider_unit_cost', 'provider_total_cost', 'instructions',
            ]);

            $new = $copy->orderServices()->create($attributes + [
                'service_number' => $service->service_number,
                'remaining_time_minutes' => $service->required_time_minutes,
                'status' => 'draft',
            ]);

            if ($withContacts) {
                foreach ($service->contacts as $contact) {
                    $new->contacts()->create($contact->only([
                        'contact_id', 'contact_role', 'first_name_snapshot', 'last_name_snapshot',
                        'phone_snapshot', 'mobile_snapshot', 'email_snapshot', 'is_primary',
                    ]));
                }
            }

            foreach ($service->servicePackages as $link) {
                if (isset($packageMap[$link->package_id])) {
                    $new->servicePackages()->create([
                        'package_id' => $packageMap[$link->package_id],
                        'quantity' => $link->quantity,
                        'handling_instructions' => $link->handling_instructions,
                        'status' => 'pending',
                    ]);
                }
            }
        }
    }

    private function copyDocumentLinks(Order $source, Order $copy): void
    {
        DocumentLink::where('entity_type', MorphMap::ORDER)
            ->where('entity_id', $source->id)
            ->get()
            ->each(fn (DocumentLink $link) => DocumentLink::create([
                'document_id' => $link->document_id,
                'entity_type' => MorphMap::ORDER,
                'entity_id' => $copy->id,
            ]));
    }
}
