<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Organizations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Organizations\StoreOrganizationLogoRequest;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Services\OrganizationLogo;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Logo d'une organisation.
 *
 * Il sert deux usages qui n'ont pas les mêmes contraintes : l'**écran**, qui
 * veut une image à afficher, et le **PDF de facture**, qui veut des octets à
 * embarquer. D'où une route qui sert le fichier — pour l'écran — et un
 * `data:` URI exposé aux modèles — pour le papier. Un PDF ne peut pas aller
 * chercher une URL : dompdf n'a pas de session, et une facture qui dépendrait
 * d'un serveur joignable au bon moment serait une facture parfois sans logo.
 *
 * Le fichier vit sur le disque privé et se sert par ici : sous `/storage`, un
 * chemin devinable donnerait le logo d'un organisme à qui l'essaie.
 */
class OrganizationLogoController extends Controller
{
    public function __construct(private readonly OrganizationLogo $logo) {}

    /**
     * Servir le logo.
     *
     * Permission requise : `organizations.view` sur cette organisation.
     *
     * `404` quand il n'y en a pas : c'est ce qu'attend une balise `<img>`, et
     * c'est la vérité — la ressource n'existe pas.
     */
    public function show(Organization $organization): StreamedResponse
    {
        $this->authorize('view', $organization);

        abort_unless($this->logo->exists($organization), 404);

        return Storage::disk('local')->response(
            $organization->logo_path,
            'logo',
            ['Content-Type' => $organization->logo_mime_type ?? 'image/png'],
        );
    }

    /**
     * Déposer ou remplacer le logo.
     *
     * Permission requise : `organizations.update`. Le propriétaire l'a d'office.
     */
    public function store(StoreOrganizationLogoRequest $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        $file = $request->file('logo');
        abort_if($file === null, 422);

        $before = ['logo' => $organization->logo_path];
        $this->logo->replace($organization, $file);

        $this->audit($request, $organization->id, 'organization_logo_updated', $organization, $before, ['logo' => $organization->logo_path]);

        return ApiResponse::ok(['hasLogo' => true]);
    }

    /**
     * Retirer le logo.
     *
     * Permission requise : `organizations.update`.
     */
    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('update', $organization);

        $before = ['logo' => $organization->logo_path];
        $this->logo->remove($organization);

        $this->audit($request, $organization->id, 'organization_logo_removed', $organization, $before, ['logo' => null]);

        return ApiResponse::ok(['hasLogo' => false]);
    }
}
