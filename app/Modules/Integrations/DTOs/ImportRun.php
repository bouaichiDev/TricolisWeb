<?php

declare(strict_types=1);

namespace App\Modules\Integrations\DTOs;

use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Validation\ImportValidationRules;
use App\Modules\Orders\Enums\OrderSource;
use Illuminate\Http\Request;

/**
 * Ce qu'une exécution d'import impose à chaque commande qu'elle crée.
 *
 * Tout ce que le contenu client ne doit pas décider est ici, fixé par
 * l'appelant : le client (celui de la configuration), l'agence, l'origine
 * (`Order.source`), l'auteur, la stratégie de doublon. Le fichier ne peut
 * rien y changer — la fusion se fait toujours dans ce sens.
 */
final readonly class ImportRun
{
    /** Import lancé par un utilisateur interne, depuis le back-office. */
    public const string INTERNAL = 'internal';

    /** Import déposé par le client dans son portail. */
    public const string PORTAL = 'portal';

    /** Import automatique : FTP/SFTP ou pull REST. */
    public const string PULL = 'pull';

    public function __construct(
        public CustomerImportConfiguration $configuration,
        public string $organizationId,
        public User $actor,
        public string $agencyId,
        public ?string $depotId,
        public OrderSource $source,
        /** skip | reject | update — voir `ImportValidationRules::onDuplicate`. */
        public string $onDuplicate,
        /** Sans référence externe, un import automatique ne peut pas être rejoué sans doublon. */
        public bool $requireReference,
        public ?Request $request = null,
        /** Libellé technique de l'exécution, pour l'audit et les journaux. */
        public string $channel = self::INTERNAL,
    ) {}

    public static function make(
        CustomerImportConfiguration $configuration,
        string $organizationId,
        User $actor,
        string $agencyId,
        ?string $depotId,
        OrderSource $source,
        bool $automated,
        ?Request $request = null,
        string $channel = self::INTERNAL,
    ): self {
        $rules = $configuration->validation_rules;

        return new self(
            $configuration,
            $organizationId,
            $actor,
            $agencyId,
            $depotId,
            $source,
            ImportValidationRules::onDuplicate($rules),
            ImportValidationRules::requiresReference($rules) ?? $automated,
            $request,
            $channel,
        );
    }

    public function customerId(): string
    {
        return $this->configuration->customer_id;
    }

    /** @return array<string, mixed>|null */
    public function validationRules(): ?array
    {
        return $this->configuration->validation_rules;
    }
}
