<?php

declare(strict_types=1);

namespace App\Modules\Orders\Exceptions;

use App\Modules\Orders\Enums\OrderStatus;
use Illuminate\Validation\ValidationException;

/**
 * Transition de statut refusée par le workflow.
 *
 * Les statuts atteignables sont **passés par l'appelant**, pas relus sur
 * l'énumération : le cycle de vie vit désormais dans le référentiel, et une
 * liste calculée ici nommerait des transitions qu'un administrateur vient
 * peut-être de retirer.
 */
final class InvalidOrderTransition
{
    /**
     * @param  list<string>  $allowed  codes réellement atteignables
     */
    public static function between(OrderStatus $from, OrderStatus $to, array $allowed = []): ValidationException
    {
        $message = $allowed === []
            ? sprintf('Le statut « %s » est final : aucune transition n’est possible.', $from->label())
            : sprintf(
                'Transition impossible de « %s » vers « %s ». Statuts atteignables : %s.',
                $from->label(),
                $to->label(),
                implode(', ', $allowed),
            );

        return ValidationException::withMessages(['status' => [$message]]);
    }

    public static function notManuallyAssignable(OrderStatus $status): ValidationException
    {
        return ValidationException::withMessages([
            'status' => [sprintf(
                'Le statut « %s » est produit par la planification ou la facturation : il ne peut pas être posé manuellement.',
                $status->label(),
            )],
        ]);
    }

    public static function reasonRequired(OrderStatus $status): ValidationException
    {
        return ValidationException::withMessages([
            'reasonText' => [sprintf('Un motif est obligatoire pour passer au statut « %s ».', $status->label())],
        ]);
    }
}
