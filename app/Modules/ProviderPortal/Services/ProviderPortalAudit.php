<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Services;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Providers\Models\Provider;
use Illuminate\Database\Eloquent\Model;

/**
 * Le module Audit existant, appliqué au portail fournisseur.
 *
 * L'entrée est écrite dans l'organisation du transporteur, pour que ses
 * administrateurs la retrouvent dans leur journal ; l'identifiant du
 * fournisseur l'accompagne, et les actions sont préfixées `provider_portal.`.
 */
final readonly class ProviderPortalAudit
{
    public const string PREFIX = 'provider_portal.';

    public function __construct(private WriteAuditLog $writer) {}

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(
        Provider $provider,
        ?User $actor,
        string $action,
        Model $entity,
        ?array $oldValues = null,
        array $newValues = [],
        ?string $ipAddress = null,
    ): void {
        $this->writer->execute(
            $provider->organization_id,
            $actor,
            self::PREFIX.$action,
            $entity,
            $oldValues,
            ['providerId' => $provider->id, ...$newValues],
            null,
            $ipAddress,
        );
    }
}
