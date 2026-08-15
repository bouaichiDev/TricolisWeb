<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Shared\Database\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Copie figée de l'adresse d'une ligne de facture.
 *
 * **Aucune relation vers `Address`.** C'est l'objet même du snapshot : corriger
 * une adresse ne doit pas réécrire une facture déjà émise. Le §12 l'exige, et
 * la migration ne pose aucune clé étrangère vers `addresses`.
 *
 * Tous les champs sont facultatifs : rien ne garantit que l'adresse d'origine
 * était complète, et un snapshot doit savoir figer une adresse partielle.
 */
#[Fillable([
    'invoice_line_id',
    'address_code',
    'name',
    'address_line1',
    'address_line2',
    'postal_code',
    'city',
    'country',
])]
class InvoiceLineAddressSnapshot extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'invoice_line_address_snapshots';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return BelongsTo<InvoiceLine, $this>
     */
    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class, 'invoice_line_id');
    }
}
