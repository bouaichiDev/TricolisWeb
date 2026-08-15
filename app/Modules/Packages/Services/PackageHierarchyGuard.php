<?php

declare(strict_types=1);

namespace App\Modules\Packages\Services;

use App\Modules\Packages\Models\Package;
use Illuminate\Validation\ValidationException;

/**
 * Protège la hiérarchie des colis.
 *
 * Trois choses rendraient l'arbre incohérent : un colis parent d'une autre
 * commande, un cycle, et une imbrication sans fin. Les trois sont vérifiées
 * ici, avant écriture.
 */
final readonly class PackageHierarchyGuard
{
    public function assertValidParent(Package $package, ?Package $parent, string $field = 'parentPackageId'): void
    {
        if ($parent === null) {
            return;
        }

        if ($parent->order_id !== $package->order_id) {
            $this->fail($field, 'Le colis parent doit appartenir à la même commande.');
        }

        if ($package->exists && $parent->id === $package->id) {
            $this->fail($field, 'Un colis ne peut pas être son propre parent.');
        }

        if ($package->exists && $this->isDescendantOf($parent, $package)) {
            $this->fail($field, 'Cette relation créerait un cycle dans la hiérarchie des colis.');
        }

        if ($this->depthOf($parent) + 1 >= Package::MAX_DEPTH) {
            $this->fail($field, sprintf('La profondeur maximale d’imbrication (%d) serait dépassée.', Package::MAX_DEPTH));
        }
    }

    /**
     * `$candidate` est-il quelque part sous `$ancestor` ?
     */
    private function isDescendantOf(Package $candidate, Package $ancestor): bool
    {
        $current = $candidate;
        $visited = 0;

        while ($current->parent_package_id !== null && $visited <= Package::MAX_DEPTH) {
            if ($current->parent_package_id === $ancestor->id) {
                return true;
            }

            $current = Package::find($current->parent_package_id);
            $visited++;

            if ($current === null) {
                return false;
            }
        }

        return false;
    }

    private function depthOf(Package $package): int
    {
        $depth = 0;
        $current = $package;

        while ($current->parent_package_id !== null && $depth <= Package::MAX_DEPTH) {
            $current = Package::find($current->parent_package_id);
            $depth++;

            if ($current === null) {
                break;
            }
        }

        return $depth;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
