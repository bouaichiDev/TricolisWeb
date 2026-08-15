<?php

declare(strict_types=1);

namespace App\Shared\Audit;

use App\Modules\Audit\Actions\WriteAuditLog;
use App\Shared\Support\AuditContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Écriture et audit d'un modèle, avec expurgation des colonnes sensibles.
 *
 * Extrait du `WriteConfigurationAudit` de la Phase 8, dont le mécanisme —
 * comparer avant/après et ne journaliser que les champs changés — est identique
 * pour les configurations d'intégration et pour les communications. Seule la
 * liste des colonnes à masquer diffère : elle est portée par la sous-classe.
 */
abstract readonly class WriteModelAudit
{
    public function __construct(private WriteAuditLog $audit) {}

    /**
     * Colonnes remplacées par un marqueur avant journalisation.
     *
     * @return list<string>
     */
    abstract protected function redactedColumns(): array;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $model, array $attributes, string $action, AuditContext $context): Model
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
     * Journalise un changement d'état déjà appliqué.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function transition(Model $model, string $action, array $before, array $after, AuditContext $context): void
    {
        $this->audit->execute(
            $context->organizationId,
            $context->user,
            $action,
            $model,
            $this->redact($before),
            $this->redact($after),
            null,
            $context->ipAddress,
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($this->redactedColumns() as $column) {
            if (array_key_exists($column, $values)) {
                $values[$column] = '[secret]';
            }
        }

        return $values;
    }
}
