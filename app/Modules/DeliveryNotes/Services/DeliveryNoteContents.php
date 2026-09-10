<?php

declare(strict_types=1);

namespace App\Modules\DeliveryNotes\Services;

use App\Modules\Orders\Models\OrderService;
use App\Modules\Orders\Models\OrderServicePackage;
use App\Modules\Packages\Models\PackageOrderLine;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ce que ce service transporte : ses colis, et les articles qui s'y trouvent.
 *
 * **Le périmètre est le service, jamais la commande.** Une commande peut porter
 * un enlèvement à Lyon et une livraison à Nice ; le BL de la livraison ne doit
 * montrer que les colis pris en charge par elle. Le lien est
 * `order_service_packages` — la table qui dit précisément cela — et non la
 * liste des colis de la commande.
 *
 * Les **quantités** viennent de deux endroits différents, et c'est voulu :
 * celle du colis vient du lien au service — un service peut n'en prendre qu'une
 * partie — celle de l'article vient de sa répartition dans le colis. Lire la
 * quantité du colis lui-même ferait mentir le BL dès qu'un service n'emporte
 * pas tout.
 *
 * Un article éclaté entre deux colis apparaît **deux fois**, une par colis,
 * avec sa quantité dans chacun. C'est ce qu'on veut sur un bon de livraison :
 * il sert à contrôler des colis à leur ouverture, pas à totaliser une commande.
 */
final readonly class DeliveryNoteContents
{
    /**
     * @return list<array<string, scalar|null>>
     */
    public function packages(OrderService $service): array
    {
        return $this->links($service)
            ->map(fn (OrderServicePackage $link): array => [
                'reference' => $link->package?->reference,
                'barcode' => $link->package?->barcode,
                'description' => $link->package?->description,
                'packageType' => $link->package?->packageType?->name,
                'groupingType' => $link->package?->groupingType?->name,
                'quantity' => $this->decimal($link->quantity, 3),
                'weight' => $this->decimal($link->package?->weight, 3),
                'volume' => $this->decimal($link->package?->volume, 4),
                'length' => $this->decimal($link->package?->length, 3),
                'width' => $this->decimal($link->package?->width, 3),
                'height' => $this->decimal($link->package?->height, 3),
                'handlingInstructions' => $link->handling_instructions,
                'status' => $link->status ?? $link->package?->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, scalar|null>>
     */
    public function articles(OrderService $service): array
    {
        $articles = [];

        foreach ($this->links($service) as $link) {
            $package = $link->package;

            if ($package === null) {
                continue;
            }

            foreach ($package->packageOrderLines as $allocation) {
                $articles[] = $this->article($allocation, $package->reference, $package->barcode);
            }
        }

        return $articles;
    }

    /**
     * Les totaux, calculés sur ce que le BL montre — pas sur la commande.
     *
     * Un total repris de `Order.weight` couvrirait les colis des autres
     * services : le destinataire signerait pour un poids qu'il n'a pas reçu.
     *
     * Les deux listes sont **passées**, non relues : les recalculer ici
     * doublerait les requêtes, et un écart entre le tableau et son total est
     * exactement ce qu'un BL ne peut pas se permettre.
     *
     * @param  list<array<string, scalar|null>>  $packages
     * @param  list<array<string, scalar|null>>  $articles
     * @return array<string, scalar|null>
     */
    public function totals(array $packages, array $articles): array
    {
        return [
            'packageCount' => count($packages),
            'articleCount' => count($articles),
            'packageQuantity' => $this->sum($packages, 'quantity', 3),
            'articleQuantity' => $this->sum($articles, 'quantity', 3),
            'weight' => $this->sum($packages, 'weight', 3),
            'volume' => $this->sum($packages, 'volume', 4),
        ];
    }

    /**
     * @return Collection<int, OrderServicePackage>
     */
    private function links(OrderService $service): Collection
    {
        return $service->servicePackages()
            ->with([
                'package.packageType:id,code,name',
                'package.groupingType:id,code,name',
                'package.packageOrderLines.orderLine',
            ])
            ->get();
    }

    /**
     * @return array<string, scalar|null>
     */
    private function article(PackageOrderLine $allocation, ?string $reference, ?string $barcode): array
    {
        $line = $allocation->orderLine;

        return [
            'articleCode' => $line?->article_code,
            'barcode' => $line?->barcode,
            'name' => $line?->name,
            'description' => $line?->description,
            'externalReference' => $line?->external_reference,
            'quantity' => $this->decimal($allocation->quantity, 3),
            'orderedQuantity' => $this->decimal($line?->quantity, 3),
            'weight' => $this->decimal($line?->weight, 3),
            'volume' => $this->decimal($line?->volume, 4),
            'packageReference' => $reference,
            'packageBarcode' => $barcode,
        ];
    }

    /**
     * Un décimal en **chaîne à décimales fixes**.
     *
     * Un flottant se relit `1.2000000000000002` sur le papier remis au client ;
     * la chaîne dit exactement ce que la base stocke.
     */
    private function decimal(mixed $value, int $scale): ?string
    {
        return $value === null ? null : number_format((float) $value, $scale, '.', '');
    }

    /**
     * @param  list<array<string, scalar|null>>  $rows
     */
    private function sum(array $rows, string $key, int $scale): string
    {
        $total = 0.0;

        foreach ($rows as $row) {
            $total += (float) ($row[$key] ?? 0);
        }

        return number_format($total, $scale, '.', '');
    }
}
