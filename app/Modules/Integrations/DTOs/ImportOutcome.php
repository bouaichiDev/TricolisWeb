<?php

declare(strict_types=1);

namespace App\Modules\Integrations\DTOs;

use App\Modules\Orders\Models\Order;

/**
 * Le sort d'une commande du contenu importé.
 *
 * Les états ne sont pas un enum en base — aucune table ne les stocke — mais un
 * vocabulaire fermé de résultat :
 *
 * - `created` / `updated` : la commande existe, telle que le contenu la décrit ;
 * - `skipped_duplicate` : sa référence était déjà importée, rien n'a changé ;
 * - `invalid` : erreur métier (champ manquant, commande non modifiable…) —
 *   la rejouer sans corriger la source donnerait la même erreur ;
 * - `failed` : erreur technique inattendue — rien n'a été écrit pour elle.
 */
final readonly class ImportOutcome
{
    public const string CREATED = 'created';

    public const string UPDATED = 'updated';

    public const string SKIPPED_DUPLICATE = 'skipped_duplicate';

    public const string INVALID = 'invalid';

    public const string FAILED = 'failed';

    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public string $status,
        public int $index,
        public ?string $reference,
        public ?Order $order = null,
        public array $errors = [],
    ) {}

    public static function done(string $status, int $index, ?string $reference, Order $order): self
    {
        return new self($status, $index, $reference, $order);
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function invalid(int $index, ?string $reference, array $errors): self
    {
        return new self(self::INVALID, $index, $reference, null, $errors);
    }

    public static function failed(int $index, ?string $reference, string $message): self
    {
        return new self(self::FAILED, $index, $reference, null, ['_' => [$message]]);
    }

    public function succeeded(): bool
    {
        return in_array($this->status, [self::CREATED, self::UPDATED, self::SKIPPED_DUPLICATE], true);
    }

    /**
     * @return array{index: int, reference: string|null, status: string, orderId: string|null, orderNumber: string|null, errors: array<string, list<string>>}
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'reference' => $this->reference,
            'status' => $this->status,
            'orderId' => $this->order?->id,
            'orderNumber' => $this->order?->order_number,
            'errors' => $this->errors,
        ];
    }
}
