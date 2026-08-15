<?php

declare(strict_types=1);

namespace App\Modules\Providers\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Providers\DTOs\UpdateProviderData;
use App\Modules\Providers\Models\Provider;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Modifie un fournisseur.
 *
 * L'audit ne retient que les champs réellement modifiés : enregistrer tout le
 * modèle à chaque `PATCH` rendrait le journal illisible.
 */
final readonly class UpdateProviderAction
{
    public function __construct(private WriteAuditLog $audit) {}

    public function execute(Provider $provider, UpdateProviderData $data, AuditContext $context): Provider
    {
        $attributes = $data->attributes->all();

        if ($attributes === []) {
            return $provider;
        }

        return DB::transaction(function () use ($provider, $attributes, $context): Provider {
            $before = $provider->only(array_keys($attributes));
            $provider->update($attributes);
            $after = $provider->fresh()->only(array_keys($attributes));

            if ($before !== $after) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    'provider.updated',
                    $provider,
                    $before,
                    $after,
                    null,
                    $context->ipAddress,
                );
            }

            return $provider->fresh();
        });
    }
}
