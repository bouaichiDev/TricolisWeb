<?php

declare(strict_types=1);

namespace App\Modules\Drivers\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Modules\Drivers\DTOs\CreateDriverData;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Providers\Services\ProviderScopeGuard;
use App\Shared\Support\AuditContext;
use Illuminate\Support\Facades\DB;

/**
 * Crée un chauffeur de l'organisation active, avec son compte.
 *
 * **Le fournisseur est facultatif** : un transporteur emploie ses propres
 * chauffeurs. Quand il est donné, il est revérifié ici et pas seulement dans le
 * Form Request — l'Action doit rester sûre appelée directement, depuis un
 * import par exemple.
 *
 * L'`organization_id` vient du contexte actif, jamais de l'appelant.
 *
 * **Le compte est créé dans la même transaction.** Un chauffeur sans compte ne
 * pourrait pas ouvrir l'application, et c'est ce compte qui reliera plus tard
 * chaque tournée à la personne qui l'a faite. Les deux naissent ensemble ou pas
 * du tout.
 */
final readonly class CreateDriverAction
{
    public function __construct(
        private ProviderScopeGuard $guard,
        private WriteAuditLog $audit,
        private CreateDriverAccount $accounts,
    ) {}

    public function execute(CreateDriverData $data, AuditContext $context): Driver
    {
        // Le fournisseur est facultatif : un transporteur emploie ses propres
        // chauffeurs. Quand il est fourni, il doit rester de l'organisation.
        if ($data->providerId !== null) {
            $this->guard->provider($data->providerId, $context->organizationId);
        }

        return DB::transaction(function () use ($data, $context): Driver {
            // Le compte d'abord : un chauffeur sans compte ne pourrait pas
            // ouvrir l'application, et c'est lui qui reliera plus tard chaque
            // tournee a la personne qui l'a faite.
            $user = $this->accounts->execute([
                'firstName' => $data->firstName,
                'lastName' => $data->lastName,
                'email' => $data->email,
                'phone' => $data->phone,
            ], $context->organizationId);

            $driver = Driver::create($data->toAttributes($context->organizationId, $user->id));

            $this->audit->execute(
                $context->organizationId,
                $context->user,
                'driver.created',
                $driver,
                null,
                $driver->only(['provider_id', 'user_id', 'code', 'name', 'status', 'address_id', 'contact_id']),
                null,
                $context->ipAddress,
            );

            return $driver;
        });
    }
}
