<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Contacts\Models\Contact;
use App\Shared\Database\Concerns\HasUlid;
use App\Shared\Enums\ContactRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contact d'un service de commande, avec copie des informations utiles.
 *
 * Le contact partagé peut être modifié ou supprimé plus tard : les colonnes de
 * snapshot garantissent qu'une commande passée reste lisible telle qu'elle a
 * été exécutée.
 */
#[Fillable([
    'order_service_id',
    'contact_id',
    'contact_role',
    'first_name_snapshot',
    'last_name_snapshot',
    'phone_snapshot',
    'mobile_snapshot',
    'email_snapshot',
    'is_primary',
])]
class OrderServiceContact extends Model
{
    use HasFactory;
    use HasUlid;

    public $timestamps = true;

    protected $table = 'order_service_contacts';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contact_role' => ContactRole::class,
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<OrderService, $this>
     */
    public function orderService(): BelongsTo
    {
        return $this->belongsTo(OrderService::class, 'order_service_id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /**
     * Fige les informations d'un contact partagé dans la liaison.
     *
     * @return array<string, mixed>
     */
    public static function snapshotFrom(Contact $contact): array
    {
        return [
            'first_name_snapshot' => $contact->first_name,
            'last_name_snapshot' => $contact->last_name,
            'phone_snapshot' => $contact->phone,
            'mobile_snapshot' => $contact->mobile,
            'email_snapshot' => $contact->email,
        ];
    }
}
