<?php

declare(strict_types=1);

namespace App\Modules\Integrations\DTOs;

use App\Modules\Orders\Models\Order;

/**
 * Le bilan d'une exécution : ce qui a été lu, créé, mis à jour, ignoré, refusé.
 *
 * Rendu dans la réponse d'un import synchrone, journalisé et audité pour un
 * import automatique. Aucune table ne le conserve ligne à ligne — voir
 * BLOCKED_DESIGN_DECISION « Persistent Customer Import Execution History ».
 */
final readonly class ImportSummary
{
    /**
     * @param  list<ImportOutcome>  $outcomes
     */
    public function __construct(
        public int $rowCount,
        public array $outcomes,
    ) {}

    public static function empty(): self
    {
        return new self(0, []);
    }

    public function merge(self $other): self
    {
        return new self($this->rowCount + $other->rowCount, [...$this->outcomes, ...$other->outcomes]);
    }

    public function count(string $status): int
    {
        return count(array_filter($this->outcomes, static fn (ImportOutcome $o): bool => $o->status === $status));
    }

    /** Rien d'invalide ni d'échoué : la source peut être considérée traitée. */
    public function isClean(): bool
    {
        return $this->count(ImportOutcome::INVALID) === 0 && $this->count(ImportOutcome::FAILED) === 0;
    }

    /**
     * @return list<Order>
     */
    public function orders(string ...$statuses): array
    {
        $orders = [];

        foreach ($this->outcomes as $outcome) {
            if ($outcome->order !== null && in_array($outcome->status, $statuses, true)) {
                $orders[] = $outcome->order;
            }
        }

        return $orders;
    }

    /**
     * Les compteurs seuls — sans référence ni message : c'est ce qui part en
     * journal et en audit, où une donnée client n'a rien à faire.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'rowCount' => $this->rowCount,
            'orderCount' => count($this->outcomes),
            'created' => $this->count(ImportOutcome::CREATED),
            'updated' => $this->count(ImportOutcome::UPDATED),
            'skipped' => $this->count(ImportOutcome::SKIPPED_DUPLICATE),
            'invalid' => $this->count(ImportOutcome::INVALID),
            'failed' => $this->count(ImportOutcome::FAILED),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $problems = array_values(array_filter(
            $this->outcomes,
            static fn (ImportOutcome $outcome): bool => ! $outcome->succeeded(),
        ));

        return [
            ...$this->counts(),
            'valid' => count($this->outcomes) - count($problems),
            'outcomes' => array_map(static fn (ImportOutcome $o): array => $o->toArray(), $this->outcomes),
            'errors' => array_map(static fn (ImportOutcome $o): array => $o->toArray(), $problems),
        ];
    }
}
