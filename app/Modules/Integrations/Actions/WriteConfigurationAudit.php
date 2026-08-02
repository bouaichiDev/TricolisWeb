<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Shared\Support\AuditContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Écriture et audit communs aux configurations d'intégration.
 *
 * Les trois configurations — import, API, export — suivent exactement le même
 * cycle : créer ou modifier, comparer, journaliser les seuls champs changés.
 * Trois Actions identiques à un nom de table près n'auraient rien apporté.
 *
 * Les colonnes sensibles sont **retirées du journal** : ni empreinte de clé, ni
 * mot de passe chiffré n'ont à figurer dans un audit, qui se consulte plus
 * largement que la table elle-même.
 */
final readonly class WriteConfigurationAudit
{
    /** @var list<string> */
    private const array REDACTED = ['api_key_hash', 'encrypted_password'];

    public function __construct(private WriteAuditLog $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $auditedColumns
     */
    public function update(Model $model, array $attributes, string $action, array $auditedColumns, AuditContext $context): Model
    {
        if ($attributes === []) {
            return $model;
        }

        return DB::transaction(function () use ($model, $attributes, $action, $context): Model {
            $before = $this->redact($model->only(array_keys($attributes)));
            $model->update($attributes);
            $after = $this->redact($model->fresh()->only(array_keys($attributes)));

            if ($before !== $after) {
                $this->audit->execute(
                    $context->organizationId,
                    $context->user,
                    $action,
                    $model,
                    $before,
                    $after,
                    null,
                    $context->ipAddress,
                );
            }

            return $model->fresh();
        });
    }

    /**
     * @param  list<string>  $auditedColumns
     */
    public function created(Model $model, string $action, array $auditedColumns, AuditContext $context): void
    {
        $this->audit->execute(
            $context->organizationId,
            $context->user,
            $action,
            $model,
            null,
            $this->redact($model->only($auditedColumns)),
            null,
            $context->ipAddress,
        );
    }

    /**
     * @param  list<string>  $auditedColumns
     */
    public function deleted(Model $model, string $action, array $auditedColumns, AuditContext $context): void
    {
        $this->audit->execute(
            $context->organizationId,
            $context->user,
            $action,
            $model,
            $this->redact($model->only($auditedColumns)),
            null,
            null,
            $context->ipAddress,
        );
    }

    /**
     * Remplace les valeurs sensibles par un marqueur.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        foreach (self::REDACTED as $column) {
            if (array_key_exists($column, $values)) {
                $values[$column] = '[secret]';
            }
        }

        return $values;
    }
}
