<?php

declare(strict_types=1);

namespace App\Modules\Packages\Services;

use App\Modules\Orders\Models\OrderLine;
use App\Modules\Packages\Models\Package;
use App\Modules\Packages\Models\PackageOrderLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Répartit une ligne de commande entre des colis.
 *
 * Une ligne peut être éclatée entre plusieurs colis, mais la somme des
 * quantités affectées ne peut pas dépasser la quantité commandée. Deux
 * affectations concurrentes pourraient chacune lire un total encore valide et
 * le dépasser ensemble : la ligne est donc verrouillée (`lockForUpdate`) le
 * temps de relire le total et d'écrire l'affectation.
 */
final readonly class PackageLineAllocator
{
    public function allocate(Package $package, OrderLine $line, float $quantity, string $field = 'quantity'): PackageOrderLine
    {
        $this->assertSameOrder($package, $line);

        if ($quantity <= 0) {
            throw ValidationException::withMessages([$field => ['La quantité affectée doit être strictement positive.']]);
        }

        return DB::transaction(function () use ($package, $line, $quantity, $field): PackageOrderLine {
            $locked = OrderLine::whereKey($line->id)->lockForUpdate()->firstOrFail();

            $existing = PackageOrderLine::where('package_id', $package->id)
                ->where('order_line_id', $locked->id)
                ->first();

            $alreadyAssigned = (float) PackageOrderLine::where('order_line_id', $locked->id)
                ->when($existing !== null, fn ($query) => $query->whereKeyNot($existing->id))
                ->sum('quantity');

            $this->assertWithinOrderedQuantity($alreadyAssigned + $quantity, (float) $locked->quantity, $field);

            if ($existing !== null) {
                $existing->update(['quantity' => $quantity]);

                return $existing;
            }

            return PackageOrderLine::create([
                'package_id' => $package->id,
                'order_line_id' => $locked->id,
                'quantity' => $quantity,
            ]);
        });
    }

    public function release(PackageOrderLine $allocation): void
    {
        $allocation->delete();
    }

    private function assertSameOrder(Package $package, OrderLine $line): void
    {
        if ($package->order_id !== $line->order_id) {
            throw ValidationException::withMessages([
                'orderLineId' => ['Le colis et la ligne doivent appartenir à la même commande.'],
            ]);
        }
    }

    private function assertWithinOrderedQuantity(float $total, float $ordered, string $field): void
    {
        if ($total > $ordered) {
            throw ValidationException::withMessages([
                $field => [sprintf(
                    'La quantité affectée dépasse la quantité commandée : %s demandé au total pour %s commandé.',
                    rtrim(rtrim(number_format($total, 3, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($ordered, 3, '.', ''), '0'), '.'),
                )],
            ]);
        }
    }
}
