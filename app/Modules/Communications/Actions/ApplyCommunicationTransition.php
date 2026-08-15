<?php

declare(strict_types=1);

namespace App\Modules\Communications\Actions;

use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Exceptions\CommunicationNotEditable;
use App\Modules\Communications\Models\OrderCommunication;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Applique un changement de statut, sous verrou.
 *
 * Toutes les transitions passent par ici : mise en file, envoi, échec,
 * annulation, relance. Trois garanties en découlent :
 *
 * 1. **le verrou** (`lockForUpdate`) sérialise deux demandes concurrentes ;
 * 2. **le statut est relu en base** après le verrou, jamais pris de l'instance
 *    en mémoire — c'est ce qui rend l'envoi idempotent : un second dispatch du
 *    même Job trouve la communication déjà partie et s'arrête ;
 * 3. **la transition est vérifiée** contre l'enum, qui porte le graphe.
 *
 * L'audit ne journalise que le statut et les horodatages touchés : ni corps de
 * message, ni réponse fournisseur.
 */
final readonly class ApplyCommunicationTransition
{
    public function __construct(private WriteCommunicationAudit $writer) {}

    /**
     * @param  array<string, mixed>  $extra  colonnes posées par la transition
     */
    public function execute(
        OrderCommunication $communication,
        CommunicationStatus $target,
        array $extra,
        string $auditAction,
        AuditContext $context,
    ): OrderCommunication {
        return DB::transaction(function () use ($communication, $target, $extra, $auditAction, $context): OrderCommunication {
            /** @var OrderCommunication $locked */
            $locked = OrderCommunication::whereKey($communication->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo($target)) {
                throw CommunicationNotEditable::forTransition($locked->status, $target);
            }

            $before = ['status' => $locked->status->value];
            $attributes = ['status' => $target, ...$extra];

            $locked->forceFill($attributes)->save();

            $after = ['status' => $target->value, ...array_map(
                static fn (mixed $value): mixed => $value instanceof \BackedEnum ? $value->value : $value,
                $extra,
            )];

            $this->writer->transition($locked, $auditAction, $before, $after, $context);

            return $locked->refresh();
        });
    }
}
