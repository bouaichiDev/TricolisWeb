<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité de toute réponse de l'API (Phase 7, §33).
 *
 * L'API ne sert que du JSON et des fichiers téléchargés : rien n'y doit être
 * interprété comme page, encadré, ni deviné par le navigateur. D'où une
 * politique stricte, sans effet sur les écrans — qui sont servis par les
 * applications React, et dont la CSP relève de leur hébergement (voir
 * docs/customer-portal/security.md).
 *
 * HSTS n'est émis que sur une requête déjà en HTTPS : l'émettre en HTTP
 * n'aurait aucun effet, et l'imposer en local casserait le développement.
 * Un en-tête déjà posé par le contrôleur (un téléchargement, par exemple)
 * n'est jamais écrasé.
 */
final class AddSecurityHeaders
{
    /** @var array<string, string> */
    private const array HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; base-uri 'none'",
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Global, mais réservé à l'API : les pages web (documentation,
        // visionneuse de journaux) gardent leur propre politique.
        if (! $request->is('api/*')) {
            return $response;
        }

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($request->isSecure() && ! $response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
