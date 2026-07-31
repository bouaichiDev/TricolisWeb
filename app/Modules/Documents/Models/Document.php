<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Organizations\Models\Organization;
use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'organization_id',
    'reference_number',
    'document_type',
    'status',
    'file_name',
    'storage_path',
    'mime_type',
    'size',
    'received_at',
    'created_by',
])]
class Document extends Model
{
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'documents';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'received_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<DocumentLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'document_id');
    }
}
