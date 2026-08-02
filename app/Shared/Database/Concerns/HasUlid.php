<?php

declare(strict_types=1);

namespace App\Shared\Database\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Génère automatiquement un ULID comme clé primaire.
 *
 * @mixin Model
 */
trait HasUlid
{
    protected static function bootHasUlid(): void
    {
        static::creating(static function (Model $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::ulid();
            }
        });
    }

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }
}
