<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Platform\StorePlatformLogoRequest;
use App\Modules\Platform\Models\PlatformSetting;
use App\Modules\Platform\Services\PlatformDefaultLogo;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La configuration de la plateforme.
 *
 * Un seul réglage aujourd'hui — le logo par défaut — et c'est délibérément une
 * **page**, pas un champ posé sur un écran existant. Les réglages de
 * l'installation elle-même n'ont pas d'autre endroit où aller, et les glisser
 * dans la fiche d'une organisation les aurait rendus introuvables : ils ne
 * concernent aucune organisation en particulier.
 *
 * Hors du middleware `organization` : la plateforme n'agit dans aucune
 * organisation, et exiger l'en-tête interdirait l'accès à un compte qui n'en a
 * pas.
 *
 * **Lire est public, écrire ne l'est pas.** La barre latérale de chacun demande
 * s'il y a un logo par défaut, et l'écran de connexion aussi — il s'affiche
 * justement pour des gens sans jeton. Protéger cette question obligerait à
 * distribuer une permission plateforme pour afficher une image de marque, et
 * laisserait la page de connexion se signer d'une icône générique. Le dépôt et
 * le retrait, eux, exigent `platform_settings.update`.
 */
class ConfigurationController extends Controller
{
    public function __construct(private readonly PlatformDefaultLogo $logo) {}

    /**
     * Ce que l'application doit savoir de sa propre configuration.
     *
     * Public : la page de connexion la demande avant toute session.
     *
     * Un booléen, pas un chemin : le publier révélerait la disposition du
     * disque, et l'écran n'en a pas besoin — il lui suffit de savoir s'il doit
     * demander l'image.
     */
    public function show(): JsonResponse
    {
        return ApiResponse::ok(['hasDefaultLogo' => $this->logo->exists()]);
    }

    /**
     * Servir le logo par défaut.
     *
     * `404` quand il n'y en a pas : c'est ce qu'attend une balise `<img>`, et
     * c'est la vérité — la ressource n'existe pas.
     */
    public function showLogo(): StreamedResponse
    {
        abort_unless($this->logo->exists(), 404);

        $settings = PlatformSetting::current();

        return Storage::disk('local')->response(
            (string) $settings->default_logo_path,
            'logo',
            ['Content-Type' => $settings->default_logo_mime_type ?? 'image/png'],
        );
    }

    public function storeLogo(StorePlatformLogoRequest $request): JsonResponse
    {
        $this->authorize('update', PlatformSetting::class);

        $file = $request->file('logo');
        abort_if($file === null, 422);

        $this->logo->replace($file);

        return ApiResponse::ok(['hasDefaultLogo' => true]);
    }

    public function destroyLogo(Request $request): JsonResponse
    {
        $this->authorize('update', PlatformSetting::class);

        $this->logo->remove();

        return ApiResponse::ok(['hasDefaultLogo' => false]);
    }
}
