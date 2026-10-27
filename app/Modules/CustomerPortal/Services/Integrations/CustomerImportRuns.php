<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Services\Integrations;

use App\Modules\Customers\Services\CustomerDefaultAgency;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\DTOs\ImportRun;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Sources\ImportSourceKind;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * L'exécution d'un import déposé dans le portail.
 *
 * Tout est imposé, rien n'est lu dans la requête : le client est celui de la
 * configuration (déjà bornée au client courant), l'agence son agence par
 * défaut — comme une commande saisie dans le portail —, l'auteur
 * l'utilisateur connecté, l'origine déduite du format (`excel_import` pour
 * un classeur).
 */
final readonly class CustomerImportRuns
{
    public function __construct(private CustomerDefaultAgency $agencies) {}

    public function for(CustomerImportConfiguration $configuration, User $user, ?Request $request = null): ImportRun
    {
        $customer = $configuration->customer;

        if ($customer === null) {
            throw new RuntimeException('Le client de cette configuration est introuvable.');
        }

        return ImportRun::make(
            $configuration,
            $customer->organization_id,
            $user,
            $this->agencies->for($customer),
            null,
            ImportSourceKind::orderSource($configuration),
            automated: false,
            request: $request,
            channel: ImportRun::PORTAL,
        );
    }

    /**
     * La configuration peut-elle recevoir un dépôt du portail ?
     *
     * @return string|null la raison du refus, ou `null`
     */
    public static function refusal(CustomerImportConfiguration $configuration): ?string
    {
        return match (true) {
            ! $configuration->is_active => 'Cette intégration est désactivée.',
            ImportSourceKind::of($configuration) !== ImportSourceKind::UPLOAD => 'Cette intégration est automatique : elle ne reçoit pas de fichier déposé.',
            ! ImportSourceKind::acceptsUpload($configuration) => 'Le format de cette intégration ne se lit pas depuis un fichier déposé.',
            ! is_array($configuration->mapping) || $configuration->mapping === [] => 'Cette intégration n’a pas encore de correspondance : contactez votre transporteur.',
            default => null,
        };
    }
}
