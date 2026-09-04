<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\PlatformSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Le logo que porte l'application quand l'organisation active n'en a pas.
 *
 * Il ne remplace jamais celui d'un organisme : la barre latérale essaie
 * l'organisation d'abord, ce logo ensuite, l'icône livrée en dernier. C'est
 * l'identité de l'installation — le nom qu'un intégrateur pose sur l'outil qu'il
 * revend — et non celle d'un client.
 *
 * **Il ne descend pas non plus sur les factures.** Le PDF appelle
 * `organization.logo` ; y substituer le logo de la plateforme mettrait la marque
 * de l'éditeur sur la facture d'un transporteur, ce que personne n'a demandé et
 * que personne ne verrait avant l'envoi au client.
 *
 * Le fichier vit sur le disque **`local`**, comme celui d'une organisation, et se
 * sert par une route authentifiée. Sous `/storage`, il serait lisible sans
 * session — ce qui n'est pas un drame pour une image de marque, mais ferait deux
 * façons de servir la même chose.
 */
final readonly class PlatformDefaultLogo
{
    private const string DIRECTORY = 'platform-logo';

    /**
     * Remplace le logo, et efface le précédent.
     *
     * L'ancien fichier part **après** que le nouveau est écrit : l'inverse
     * laisserait la plateforme sans logo si l'écriture échouait.
     */
    public function replace(UploadedFile $file): void
    {
        $settings = PlatformSetting::current();
        $previous = $settings->default_logo_path;

        $path = $file->store(self::DIRECTORY, 'local');

        $settings->update([
            'default_logo_path' => $path,
            'default_logo_mime_type' => $file->getClientMimeType(),
        ]);

        $this->deleteFile($previous);
    }

    public function remove(): void
    {
        $settings = PlatformSetting::current();
        $previous = $settings->default_logo_path;

        $settings->update(['default_logo_path' => null, 'default_logo_mime_type' => null]);

        $this->deleteFile($previous);
    }

    /**
     * Le fichier est-il réellement là ?
     *
     * La colonne ne suffit pas : elle peut désigner un fichier disparu — disque
     * remonté, sauvegarde partielle. Répondre « oui » ferait servir un 404 à
     * une balise `<img>` qui croyait avoir une image.
     */
    public function exists(): bool
    {
        $settings = PlatformSetting::current();

        return $settings->default_logo_path !== null
            && Storage::disk('local')->exists($settings->default_logo_path);
    }

    private function deleteFile(?string $path): void
    {
        if ($path !== null && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }
}
