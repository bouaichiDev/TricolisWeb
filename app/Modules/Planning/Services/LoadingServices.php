<?php

declare(strict_types=1);

namespace App\Modules\Planning\Services;

use App\Modules\Orders\Models\Service;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Support\Facades\DB;

/**
 * Quels services sont des chargements.
 *
 * La reconnaissance se fait **par code**, comme décidé le 26 août 2026. Les
 * codes vivent dans les réglages de l'organisation — pas en dur : deux
 * transporteurs ne nomment pas leur chargement de la même façon, et une
 * constante dans le code obligerait à livrer une version pour en ajouter un.
 *
 * ```json
 * { "planning": { "loadingServiceCodes": ["LOAD", "CHARG"] } }
 * ```
 *
 * **Le revers de cette approche est connu et compensé.** Renommer le code d'un
 * service le ferait cesser d'être un chargement sans que rien ne le signale :
 * le regroupement au dépôt s'arrêterait, silencieusement. `unmatched()` rend
 * les codes réglés qui ne correspondent à aucun service, afin que l'écran des
 * réglages puisse le dire avant que la planification ne s'en aperçoive.
 *
 * La comparaison ignore la casse : « load » et « LOAD » désignent le même
 * service pour qui saisit.
 */
final readonly class LoadingServices
{
    /** Chemin du réglage dans `organizations.settings`. */
    public const string SETTING_PATH = 'planning.loadingServiceCodes';

    /**
     * Codes réglés pour cette organisation, en majuscules.
     *
     * @return list<string>
     */
    public function codes(Organization $organization): array
    {
        $codes = data_get($organization->settings ?? [], self::SETTING_PATH, []);

        if (! is_array($codes)) {
            return [];
        }

        $kept = [];

        foreach ($codes as $code) {
            if (is_string($code) && trim($code) !== '') {
                $kept[] = mb_strtoupper(trim($code));
            }
        }

        return array_values(array_unique($kept));
    }

    /** Ce service est-il un chargement pour cette organisation ? */
    public function isLoading(Service $service, Organization $organization): bool
    {
        $codes = $this->codes($organization);

        return $codes !== [] && in_array(mb_strtoupper((string) $service->code), $codes, true);
    }

    /**
     * Identifiants des services de chargement de l'organisation.
     *
     * Rendus en une requête : la planification en a besoin pour toute une
     * commande d'un coup, et interroger service par service reviendrait à
     * poser la même question dix fois.
     *
     * @return list<string>
     */
    public function serviceIds(Organization $organization): array
    {
        $codes = $this->codes($organization);

        if ($codes === []) {
            return [];
        }

        return Service::where('organization_id', $organization->id)
            ->whereIn(DB::raw('UPPER(code)'), $codes)
            ->pluck('id')
            ->all();
    }

    /**
     * Codes réglés qui ne correspondent à aucun service existant.
     *
     * C'est le garde-fou de la reconnaissance par code : un code mal saisi, ou
     * un service renommé depuis, apparaît ici plutôt que de faire disparaître
     * le regroupement au dépôt sans un mot.
     *
     * @return list<string>
     */
    public function unmatched(Organization $organization): array
    {
        $codes = $this->codes($organization);

        if ($codes === []) {
            return [];
        }

        $known = Service::where('organization_id', $organization->id)
            ->pluck('code')
            ->map(static fn (string $code): string => mb_strtoupper($code))
            ->all();

        return array_values(array_diff($codes, $known));
    }
}
