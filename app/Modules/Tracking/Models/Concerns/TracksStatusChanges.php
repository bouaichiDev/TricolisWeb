<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models\Concerns;

use App\Modules\Tracking\Services\PublishTrackingEvent;
use BackedEnum;

/**
 * Publie une étape du parcours quand le statut change.
 *
 * Un observateur plutôt qu'un appel dans chaque Action : `order_services.status`
 * s'écrit depuis six endroits — création complète, duplication, changement de
 * statut, contrôleur — et en ajouter un septième demain sans y penser laisserait
 * un trou silencieux. La commande avancerait sans que le client le voie.
 *
 * Rien n'est publié quand le statut n'a pas bougé : `saved` se déclenche à
 * chaque écriture, y compris pour un changement d'adresse.
 */
trait TracksStatusChanges
{
    public static function bootTracksStatusChanges(): void
    {
        static::saved(function ($model): void {
            // `wasChanged` est faux a la creation : `wasRecentlyCreated` couvre
            // le premier statut, celui qui ouvre le parcours.
            if (! $model->wasRecentlyCreated && ! $model->wasChanged('status')) {
                return;
            }

            $status = $model->getAttribute('status');
            $status = $status instanceof BackedEnum ? $status->value : $status;

            if (! is_string($status) || $status === '') {
                return;
            }

            app(PublishTrackingEvent::class)->forStatus($model, $status);
        });
    }
}
