<?php

declare(strict_types=1);

namespace App\Modules\Communications\Enums;

/**
 * Canaux de communication du diagramme — cinq valeurs, closes.
 *
 * Le canal détermine trois choses : le champ de destinataire exigé, la façon
 * dont le rendu échappe le contenu, et le transporteur choisi à l'envoi.
 */
enum CommunicationChannel: string
{
    case EMAIL = 'email';
    case SMS = 'sms';
    case WHATSAPP = 'whatsapp';
    case PUSH_NOTIFICATION = 'push_notification';
    case INTERNAL_NOTIFICATION = 'internal_notification';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'E-mail',
            self::SMS => 'SMS',
            self::WHATSAPP => 'WhatsApp',
            self::PUSH_NOTIFICATION => 'Notification push',
            self::INTERNAL_NOTIFICATION => 'Notification interne',
        };
    }

    /**
     * Le canal transporte-t-il un objet ?
     *
     * Un SMS n'en a pas. Le §11 interdit d'exiger `subjectTemplate` pour SMS ou
     * WhatsApp : cette méthode porte la règle, plutôt que de la recopier dans
     * chaque Form Request.
     */
    public function usesSubject(): bool
    {
        return $this === self::EMAIL;
    }

    /**
     * Le contenu rendu doit-il être échappé en HTML ?
     *
     * Seul l'e-mail est interprété comme du balisage. Échapper un SMS y
     * écrirait `&amp;` à la place d'une esperluette.
     */
    public function escapesHtml(): bool
    {
        return $this === self::EMAIL;
    }

    public function requiresEmail(): bool
    {
        return $this === self::EMAIL;
    }

    public function requiresPhone(): bool
    {
        return in_array($this, [self::SMS, self::WHATSAPP], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
