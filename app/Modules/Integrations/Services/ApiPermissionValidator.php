<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Modules\Identity\Models\Permission;

/**
 * Contrôle les permissions accordées à une clé API client.
 *
 * Le §15 est explicite : « réutiliser les codes de permissions existants »,
 * « ne pas créer un second système de permissions ». Les codes sont donc
 * validés contre la table `permissions`, jamais contre une liste recopiée.
 *
 * Quatre modules sont **interdits** à une clé client : une intégration doit
 * pouvoir déposer des commandes et lire ses exports, pas gérer les comptes, les
 * rôles ni l'organisation du transporteur. La liste est dérivée du champ
 * `module`, pas énumérée code par code — elle reste juste quand un module
 * gagne une permission.
 */
final readonly class ApiPermissionValidator
{
    /** @var list<string> */
    private const array FORBIDDEN_MODULES = ['organizations', 'users', 'roles', 'permissions', 'subscriptions'];

    /**
     * Codes que le projet connaît et qu'une clé client peut porter.
     *
     * @return list<string>
     */
    public function allowedCodes(): array
    {
        return Permission::query()
            ->whereNotIn('module', self::FORBIDDEN_MODULES)
            ->pluck('code')
            ->all();
    }

    /**
     * Codes existants mais interdits à une clé client.
     *
     * @return list<string>
     */
    public function forbiddenCodes(): array
    {
        return Permission::query()
            ->whereIn('module', self::FORBIDDEN_MODULES)
            ->pluck('code')
            ->all();
    }
}
