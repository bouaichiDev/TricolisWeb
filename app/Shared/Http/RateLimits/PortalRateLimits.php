<?php

declare(strict_types=1);

namespace App\Shared\Http\RateLimits;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Les limiteurs du périmètre externe : portail client, lien public, API par
 * clé (Phase 7, §15). Les plafonds viennent de `config/customer_portal.php`
 * et de `config/customer_integrations.php` ; leurs valeurs par défaut sont
 * documentées dans `docs/customer-portal/security.md`.
 *
 * Chaque compteur est **par appelant identifié** — membre du portail,
 * empreinte de clé API — et par adresse IP seulement à défaut : un client ne
 * consomme pas le crédit d'un autre derrière le même proxy.
 *
 * La connexion garde son propre compteur, dans l'Action (5 essais par minute
 * et par couple adresse/e-mail).
 */
final class PortalRateLimits
{
    public static function register(): void
    {
        // Le lien public de suivi est la seule porte sans compte : compté par IP.
        RateLimiter::for('public-tracking', static fn (Request $request): Limit => Limit::perMinute(
            Config::integer('customer_portal.public_tracking.rate_limit_per_minute', 60),
        )->by('public-tracking|'.$request->ip()));

        // L'API client (X-Api-Key) : par empreinte de clé, jamais la clé.
        RateLimiter::for('customer-api', static fn (Request $request): Limit => Limit::perMinute(
            Config::integer('customer_integrations.api.rate_limit_per_minute', 60),
        )->by('customer-api|'.self::apiKeyFingerprint($request)));

        // La connexion : le compteur de l'Action est par couple adresse/e-mail ;
        // celui-ci, par adresse seule, freine qui essaie beaucoup d'e-mails.
        RateLimiter::for('customer-portal-login', static fn (Request $request): Limit => Limit::perMinute(
            Config::integer('customer_portal.rate_limits.logins_per_minute', 30),
        )->by('customer-portal-login|'.$request->ip()));

        // Le portail authentifié : un crédit large, par compte.
        RateLimiter::for('customer-portal', static fn (Request $request): Limit => Limit::perMinute(
            Config::integer('customer_portal.rate_limits.api_per_minute', 300),
        )->by('customer-portal|'.self::caller($request)));

        // Portail fournisseur : mêmes principes, compteurs distincts.
        RateLimiter::for('provider-portal-login', static fn (Request $request): Limit => Limit::perMinute(
            Config::integer('provider_portal.rate_limits.logins_per_minute', 30),
        )->by('provider-portal-login|'.$request->ip()));

        RateLimiter::for('provider-portal', static fn (Request $request): Limit => Limit::perMinute(
            Config::integer('provider_portal.rate_limits.api_per_minute', 300),
        )->by('provider-portal|'.self::caller($request)));

        // Les créations (chauffeurs, véhicules) : un plafond serré, qui borne
        // aussi les essais d'e-mails ou d'immatriculations déjà pris.
        RateLimiter::for('provider-portal-write', static fn (Request $request): Limit => Limit::perMinute(
            Config::integer('provider_portal.rate_limits.writes_per_minute', 30),
        )->by('provider-portal-write|'.self::caller($request)));

        // L'exécution d'une tournée (arrêts, preuves) : un rattrapage de
        // quarante arrêts dépasse vite le plafond des créations. Compteur à part.
        RateLimiter::for('provider-portal-execute', static fn (Request $request): Limit => Limit::perMinute(
            Config::integer('provider_portal.rate_limits.executions_per_minute', 120),
        )->by('provider-portal-execute|'.self::caller($request)));

        // Les dépôts de fichiers coûtent cher (lecture, validation) : à part.
        RateLimiter::for('customer-portal-import', static fn (Request $request): Limit => Limit::perMinute(
            Config::integer('customer_portal.rate_limits.imports_per_minute', 10),
        )->by('customer-portal-import|'.self::caller($request)));

        // La relance d'un envoi appelle le système du client : à part aussi.
        RateLimiter::for('customer-portal-retry', static fn (Request $request): Limit => Limit::perMinute(
            Config::integer('customer_portal.rate_limits.export_retries_per_minute', 10),
        )->by('customer-portal-retry|'.self::caller($request)));
    }

    private static function caller(Request $request): string
    {
        $user = $request->user();

        return $user === null ? 'ip:'.$request->ip() : 'user:'.$user->getAuthIdentifier();
    }

    private static function apiKeyFingerprint(Request $request): string
    {
        $key = $request->header('X-Api-Key');

        return is_string($key) && $key !== '' ? hash('sha256', $key) : 'ip:'.$request->ip();
    }
}
