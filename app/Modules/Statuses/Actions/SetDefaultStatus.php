<?php

declare(strict_types=1);

namespace App\Modules\Statuses\Actions;

use App\Modules\Statuses\Models\Status;
use App\Modules\Statuses\Services\DefaultStatuses;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Choisit le statut qu'une entité reçoit à sa création — ou retire ce choix.
 *
 * **Un seul par entité.** Cocher un statut décoche les autres de la même
 * source, dans la même transaction : deux statuts « par défaut » laisseraient la
 * création choisir au hasard.
 *
 * Retirer le choix n'efface rien : la colonne de l'entité reprend sa valeur par
 * défaut, celle d'avant la configuration.
 */
final readonly class SetDefaultStatus
{
    public function __construct(private DefaultStatuses $defaults) {}

    /**
     * @return list<Status> les statuts dont la case a changé, pour l'audit
     *
     * @throws ValidationException
     */
    public function execute(string $source, ?string $statusId): array
    {
        $target = $statusId === null ? null : Status::whereKey($statusId)->first();

        if ($statusId !== null && ($target === null || $target->source !== $source)) {
            throw ValidationException::withMessages([
                'statusId' => ['Ce statut n’appartient pas à cette entité.'],
            ]);
        }

        if ($target !== null && ! $target->active) {
            throw ValidationException::withMessages([
                'statusId' => ['Un statut désactivé ne peut pas être donné aux nouvelles entités.'],
            ]);
        }

        $changed = DB::transaction(function () use ($source, $target): array {
            $changed = [];

            $previous = Status::where('source', $source)
                ->where('is_default', true)
                ->when($target !== null, fn ($query) => $query->whereKeyNot($target->id))
                ->get();

            foreach ($previous as $status) {
                $status->update(['is_default' => false]);
                $changed[] = $status;
            }

            if ($target !== null && ! $target->is_default) {
                $target->update(['is_default' => true]);
                $changed[] = $target;
            }

            return $changed;
        });

        $this->defaults->flush();

        return $changed;
    }
}
