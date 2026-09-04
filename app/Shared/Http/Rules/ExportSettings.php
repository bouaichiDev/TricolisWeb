<?php

declare(strict_types=1);

namespace App\Shared\Http\Rules;

use App\Modules\Exports\Enums\ExportTransport;
use App\Modules\Exports\Services\Transports\RestAuthentication;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Les réglages que la plateforme sait interpréter, et leurs bornes.
 *
 * **`settings` reste ouvert** : le §66 en fait le sac où chaque client range
 * ses conventions — mapping de champs, valeurs fixes, en-têtes maison — et
 * fermer la liste casserait les destinations existantes. Cette règle ne ferme
 * rien : elle vérifie les clés que le code lit vraiment, et laisse passer les
 * autres.
 *
 * **Une règle d'ensemble, et non des règles imbriquées.** Déclarer
 * `settings.authType` amènerait `validated()` à ne rendre que les clés
 * déclarées : `fieldMapping` disparaîtrait à la première sauvegarde, sans un
 * mot. Le contrôle se fait donc ici, sur le tableau entier.
 *
 * L'intérêt est de refuser tôt : une URL de jeton vide ou un séparateur de
 * trois caractères ne se découvriraient sinon qu'à la première clôture, la
 * facture déjà figée et l'envoi en échec.
 */
final readonly class ExportSettings implements ValidationRule
{
    public function __construct(private ?string $transport = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $this->authentication($value, $fail);
        $this->mail($value, $fail);
        $this->file($value, $fail);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function authentication(array $settings, Closure $fail): void
    {
        $mode = $settings['authType'] ?? null;

        if ($mode !== null && ! in_array($mode, RestAuthentication::MODES, true)) {
            $fail('Ce mode d’authentification n’est pas reconnu.');

            return;
        }

        $header = $settings['apiKeyHeader'] ?? null;

        if ($header !== null && (! is_string($header) || preg_match('/^[A-Za-z0-9-]{1,64}$/', $header) !== 1)) {
            $fail('Un nom d’en-tête ne contient que des lettres, des chiffres et des tirets.');
        }

        $this->oauth($settings, $mode, $fail);
        $this->text($settings, ['clientId' => 255, 'scope' => 255], $fail);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function oauth(array $settings, mixed $mode, Closure $fail): void
    {
        $tokenUrl = $settings['tokenUrl'] ?? null;
        $declared = is_string($tokenUrl) && trim($tokenUrl) !== '';

        if ($mode === RestAuthentication::OAUTH2 && ! $declared) {
            $fail('Un mode OAuth2 exige l’URL du serveur de jetons.');

            return;
        }

        if ($declared && filter_var($tokenUrl, FILTER_VALIDATE_URL) === false) {
            $fail('L’URL du serveur de jetons est invalide.');
        }

        $lifetime = $settings['tokenLifetimeSeconds'] ?? null;

        if ($lifetime !== null && (! is_int($lifetime) || $lifetime < 120 || $lifetime > 86400)) {
            $fail('La durée de vie d’un jeton se compte entre 120 et 86 400 secondes.');
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function mail(array $settings, Closure $fail): void
    {
        $recipients = $settings['recipients'] ?? null;

        // Un transport courriel sans destinataire ne partirait jamais : autant
        // le dire a la saisie plutot qu'a la premiere cloture.
        if ($recipients === null || $recipients === '') {
            if ($this->transport === ExportTransport::EMAIL->value) {
                $fail('Un envoi par courriel exige au moins un destinataire.');
            }

            return;
        }

        (new EmailRecipients)->validate('recipients', $recipients, $fail);

        $this->text($settings, ['subject' => 255, 'body' => 2000], $fail);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function file(array $settings, Closure $fail): void
    {
        foreach (['delimiter' => 'Le séparateur CSV', 'enclosure' => 'Le caractère d’encadrement CSV'] as $key => $label) {
            $character = $settings[$key] ?? null;

            if ($character !== null && (! is_string($character) || mb_strlen($character) !== 1)) {
                $fail(sprintf('%s doit être un seul caractère.', $label));
            }
        }

        $this->text($settings, ['documentTitle' => 120], $fail);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, int>  $limits
     */
    private function text(array $settings, array $limits, Closure $fail): void
    {
        foreach ($limits as $key => $max) {
            $value = $settings[$key] ?? null;

            if ($value === null) {
                continue;
            }

            if (! is_string($value) || mb_strlen($value) > $max) {
                $fail(sprintf('Le réglage « %s » doit être un texte d’au plus %d caractères.', $key, $max));
            }
        }
    }
}
