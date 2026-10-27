<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une exécution d'import client : dépôt de fichier, relève REST, fichier relevé
 * en FTP/SFTP (décision Phase 6 levée le 2026-10-10).
 *
 * Écrite uniquement par `CustomerImportHistory`, jamais modifiée ensuite.
 * `errors` ne porte que rang, référence externe, champs et messages — jamais
 * le contenu d'une ligne.
 */
#[Fillable([
    'customer_id', 'configuration_id', 'channel', 'source', 'status', 'file_name',
    'row_count', 'order_count', 'created_count', 'updated_count', 'skipped_count', 'invalid_count', 'failed_count',
    'errors', 'error_message', 'triggered_by', 'job_id', 'started_at', 'finished_at',
])]
class CustomerImportRun extends Model
{
    use HasUlid;

    /** Tout importé, rien de refusé. */
    public const string COMPLETED = 'completed';

    /** Des commandes créées, d'autres refusées. */
    public const string COMPLETED_WITH_ERRORS = 'completed_with_errors';

    /** Fichier refusé en entier : rien n'a été écrit (import « tout ou rien » du back-office). */
    public const string REJECTED = 'rejected';

    /** L'exécution n'a pas abouti : connexion, fichier illisible, erreur technique. */
    public const string FAILED = 'failed';

    public $timestamps = false;

    protected $table = 'customer_import_runs';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * @return BelongsTo<CustomerImportConfiguration, $this>
     */
    public function configuration(): BelongsTo
    {
        return $this->belongsTo(CustomerImportConfiguration::class, 'configuration_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trigger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
