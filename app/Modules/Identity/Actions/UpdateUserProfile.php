<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Shared\Support\InputMapper;

/**
 * Met à jour les informations de profil d'un utilisateur connecté.
 */
final readonly class UpdateUserProfile
{
    /** @var array<string, string> */
    private const array MAPPING = [
        'first_name' => 'firstName',
        'last_name' => 'lastName',
        'phone' => 'phone',
        'preferred_language' => 'preferredLanguage',
    ];

    /**
     * @param  array<string, mixed>  $validated
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    public function execute(User $user, array $validated): array
    {
        $columns = array_keys(self::MAPPING);
        $old = $user->only($columns);

        $user->update(InputMapper::map($validated, self::MAPPING));

        return ['old' => $old, 'new' => $user->fresh()->only($columns)];
    }
}
