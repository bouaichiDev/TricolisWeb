<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Services;

use App\Modules\Organizations\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Le logo d'une organisation : le poser, le retirer, le relire.
 *
 * Il vit sur le disque **`local`**, pas `public`. Une organisation n'a pas à
 * voir le logo d'une autre, et un chemin devinable sous `/storage` le donnerait
 * à qui l'essaie ; il se sert donc par une route qui vérifie l'appartenance.
 *
 * `dataUri()` est ce dont le PDF de facture a besoin. dompdf va chercher chaque
 * ressource externe au moment du rendu : une facture qui pointerait vers une
 * URL dépendrait d'un serveur joignable au bon moment — et d'une session, que
 * dompdf n'a pas. Le logo part donc **dans** le HTML, encodé.
 */
final readonly class OrganizationLogo
{
    /** Là où vivent les fichiers, un dossier par organisation. */
    private const string DIRECTORY = 'organization-logos';

    /**
     * Remplace le logo, et efface le précédent.
     *
     * L'ancien fichier part **après** que le nouveau est écrit : l'inverse
     * laisserait l'organisation sans logo si l'écriture échouait.
     */
    public function replace(Organization $organization, UploadedFile $file): void
    {
        $previous = $organization->logo_path;

        $path = $file->store(self::DIRECTORY.'/'.$organization->id, 'local');

        $organization->update([
            'logo_path' => $path,
            'logo_mime_type' => $file->getClientMimeType(),
        ]);

        $this->deleteFile($previous);
    }

    public function remove(Organization $organization): void
    {
        $previous = $organization->logo_path;

        $organization->update(['logo_path' => null, 'logo_mime_type' => null]);

        $this->deleteFile($previous);
    }

    public function exists(Organization $organization): bool
    {
        return $organization->logo_path !== null
            && Storage::disk('local')->exists($organization->logo_path);
    }

    /**
     * Le logo encodé pour un document, ou `null` s'il n'y en a pas.
     *
     * Renvoie `null` aussi quand la ligne désigne un fichier absent : un
     * `data:` URI vide casserait la mise en page du PDF là où une image
     * manquante ne fait qu'un trou.
     */
    public function dataUri(?Organization $organization): ?string
    {
        if ($organization === null || ! $this->exists($organization)) {
            return null;
        }

        $contents = Storage::disk('local')->get($organization->logo_path);

        if ($contents === null) {
            return null;
        }

        $mime = $organization->logo_mime_type ?? 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private function deleteFile(?string $path): void
    {
        if ($path !== null && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }
}
