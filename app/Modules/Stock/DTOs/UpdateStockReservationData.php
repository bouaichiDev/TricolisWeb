<?php

declare(strict_types=1);

namespace App\Modules\Stock\DTOs;

use App\Shared\Support\PartialAttributes;

/**
 * Modification d'une réservation, limitée au statut.
 *
 * Le §24 demande de « limiter `PATCH` aux champs réellement modifiables » et
 * interdit « la modification arbitraire de quantité après utilisation sans
 * Action dédiée ».
 *
 * `quantity` est donc **exclue** : la changer devrait ajuster `reservedQuantity`
 * du solde sous verrou, ce qui est une opération de stock, pas une correction
 * de saisie. Pour réserver autrement, on libère et on recrée.
 *
 * Les trois clés étrangères sont exclues pour la même raison.
 */
final readonly class UpdateStockReservationData
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'status' => 'status',
    ];

    public function __construct(public PartialAttributes $attributes) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(PartialAttributes::fromValidated($validated, self::MAPPING));
    }
}
