<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Les réglages de la plateforme, en une ligne.
 *
 * `current()` la crée si elle manque, plutôt que de rendre `null`. Un appelant
 * qui doit se demander à chaque fois si la configuration existe finit par
 * l'oublier quelque part, et la ligne absente n'apprend rien de plus qu'une
 * ligne vide — dans les deux cas, rien n'est réglé.
 *
 * Elle n'est pas semée : la créer d'avance obligerait à la recréer sur chaque
 * base existante, pour un enregistrement que la première lecture fabrique.
 */
#[Fillable([
    'singleton',
    'default_logo_path',
    'default_logo_mime_type',
])]
class PlatformSetting extends Model
{
    use HasUlid;

    protected $table = 'platform_settings';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'singleton' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::firstOrCreate(['singleton' => true]);
    }
}
