<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Templates;

use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Templates\Enums\TemplateType;
use Illuminate\Validation\Validator;

/**
 * Message ou document : la table est commune, les règles ne le sont pas.
 *
 * Le §0.7 est explicite — « ne jamais mettre artificiellement `channel = EMAIL`
 * sur le document facture ». Un modèle de facture avec un canal apparaîtrait
 * dans le sélecteur des messages, et une règle de communication pourrait
 * l'envoyer par courriel comme s'il s'agissait d'un texte.
 *
 * Trois refus :
 *
 * - une facture **avec** un canal ;
 * - une facture **avec** un objet — un document n'a pas de ligne de sujet ;
 * - un message **sans** canal — il ne saurait pas par où partir.
 *
 * L'objet reste exigé pour l'e-mail seul : le §19 interdit de l'imposer à SMS
 * et WhatsApp.
 *
 * La règle est partagée entre création et modification : deux copies auraient
 * divergé au premier ajustement.
 */
final readonly class TemplateNature
{
    public static function check(
        Validator $validator,
        mixed $templateType,
        mixed $channel,
        mixed $subjectTemplate,
    ): void {
        $type = is_string($templateType) ? TemplateType::tryFrom($templateType) : null;

        if ($type === null) {
            return;
        }

        if ($type->isDocument()) {
            if ($channel !== null && $channel !== '') {
                $validator->errors()->add('channel', 'Un modèle de facture est un document : il n’a pas de canal d’envoi.');
            }

            if ($subjectTemplate !== null && $subjectTemplate !== '') {
                $validator->errors()->add('subjectTemplate', 'Un modèle de facture est un document : il n’a pas d’objet.');
            }

            return;
        }

        if ($channel === null || $channel === '') {
            $validator->errors()->add('channel', 'Un modèle de message doit indiquer son canal d’envoi.');

            return;
        }

        if ($channel === CommunicationChannel::EMAIL->value && ($subjectTemplate === null || $subjectTemplate === '')) {
            $validator->errors()->add('subjectTemplate', 'Le canal e-mail exige un objet.');
        }
    }
}
