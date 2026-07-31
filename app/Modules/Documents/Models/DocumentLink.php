<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'document_id',
    'entity_type',
    'entity_id',
])]
class DocumentLink extends Model
{
    use HasUlid;

    public $timestamps = true;

    protected $table = 'document_links';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    /**
     * @return MorphTo<$this>
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
