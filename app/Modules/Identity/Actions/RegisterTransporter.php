<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;

/**
 * L'inscription publique d'un transporteur.
 *
 * La création elle-même appartient à `ProvisionOrganization` : l'acceptation
 * d'une demande d'accès produit la même chose, et deux copies de cette
 * transaction divergeraient à la première évolution du référentiel.
 *
 * Ce qui reste ici est ce qui n'appartient qu'à l'inscription : le jeton. Le
 * demandeur est devant son écran, il vient de choisir son mot de passe, et la
 * session s'ouvre sans qu'il ait à se reconnecter.
 */
final readonly class RegisterTransporter
{
    public function __construct(private ProvisionOrganization $provision) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{user: User, organization: Organization, token: string}
     */
    public function execute(array $data, string $deviceName): array
    {
        $created = $this->provision->execute($data);

        return [
            ...$created,
            'token' => $created['user']->createToken($deviceName)->plainTextToken,
        ];
    }
}
