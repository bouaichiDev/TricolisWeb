<?php

declare(strict_types=1);

namespace App\Modules\Communications\Services\Senders;

use App\Modules\Communications\Models\OrderCommunication;

/**
 * Base des canaux dont aucun fournisseur n'est raccordé au projet.
 *
 * Le §26 interdit d'ajouter un fournisseur métier absent du diagramme, et le §5
 * interdit les classes vides. Un transporteur qui retournerait « succès » sans
 * rien envoyer serait pire que les deux : la communication passerait en `SENT`
 * alors que rien n'est parti.
 *
 * Ces transporteurs **échouent donc explicitement**, en nommant ce qui manque.
 * La communication passe en `FAILED` avec `errorMessage` — l'état vrai du
 * système. Raccorder un fournisseur consistera à remplacer un seul corps de
 * méthode : ni migration, ni changement d'API.
 */
abstract readonly class UnconfiguredChannelSender implements CommunicationSender
{
    public function send(OrderCommunication $communication): SenderResult
    {
        return SenderResult::failure(
            'Canal indisponible : '.$this->missingRequirement().' n’est configuré dans ce projet.',
            ['channel' => $communication->channel->value, 'configured' => false],
        );
    }

    /**
     * Ce qui manque, nommé pour l'exploitant.
     */
    abstract protected function missingRequirement(): string;
}
