<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Import;

use App\Http\Requests\Api\V1\Orders\StoreOrderRequest;
use App\Modules\Integrations\Actions\ImportCustomerOrderAction;
use App\Modules\Integrations\DTOs\ImportRun;
use App\Modules\Integrations\DTOs\ImportSummary;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\ImportPreviewValidator;
use App\Modules\Integrations\Services\MappingInterpreter;
use App\Modules\Integrations\Services\Validation\ImportValidationRules;
use App\Modules\Orders\Models\Order;

/**
 * Enregistrements → commandes : découpe, correspondance, puis l'Action unique.
 *
 * C'est la chaîne commune à tous les canaux. Un canal ne diffère que par la
 * façon dont il obtient les enregistrements (dépôt, FTP, REST) et par
 * l'`ImportRun` qu'il construit — jamais par la façon dont il écrit.
 *
 * Chaque commande est traitée pour elle-même : une commande refusée n'empêche
 * pas les suivantes (§32 — validation, transaction par commande, résumé).
 */
final readonly class ImportPipeline
{
    /** Nombre de commandes détaillées dans une prévisualisation. */
    private const int PREVIEW_ORDERS = 50;

    public function __construct(
        private OrderGroups $groups,
        private MappingInterpreter $interpreter,
        private ImportCustomerOrderAction $import,
        private ImportPreviewValidator $preview,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function run(ImportRun $run, array $rows): ImportSummary
    {
        $mapping = $run->configuration->mapping ?? [];
        $outcomes = [];

        foreach ($this->groups->split($mapping, $rows) as $index => $group) {
            $outcomes[] = $this->import->execute($run, $this->interpreter->apply($mapping, $group), $index);
        }

        return new ImportSummary(count($rows), $outcomes);
    }

    /**
     * Ce que l'import ferait, sans rien écrire.
     *
     * Le verdict reprend les règles de `StoreOrderRequest` (moins les
     * identifiants résolus à l'import) et celles de la configuration ; la
     * détection de doublon applique la même clé que l'import.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{orderCount: int, valid: int, invalid: int, duplicates: int, orders: list<array<string, mixed>>}
     */
    public function preview(CustomerImportConfiguration $configuration, array $rows): array
    {
        $mapping = $configuration->mapping ?? [];
        $rules = (new StoreOrderRequest)->rules();
        $groups = $this->groups->split($mapping, $rows);
        $onDuplicate = ImportValidationRules::onDuplicate($configuration->validation_rules);
        $counts = ['valid' => 0, 'invalid' => 0, 'duplicates' => 0];
        $orders = [];

        $payloads = array_map(fn (array $group): array => $this->interpreter->apply($mapping, $group), $groups);
        $existing = $this->existingReferences($configuration, $payloads);

        foreach ($payloads as $index => $payload) {
            $reference = is_scalar($payload['externalReference'] ?? null) ? (string) $payload['externalReference'] : null;
            $errors = [
                ...ImportValidationRules::errors($payload, $configuration->validation_rules),
                ...$this->preview->verdict($payload, $rules)['errors'],
            ];
            $duplicate = $reference !== null && isset($existing[$reference]);

            if ($duplicate && $onDuplicate === 'reject') {
                $errors['externalReference'][] = "La référence {$reference} a déjà été importée.";
            }

            $status = $errors !== [] ? 'invalid' : ($duplicate ? 'duplicate' : 'valid');
            $counts[$status === 'duplicate' ? 'duplicates' : $status]++;

            if (count($orders) < self::PREVIEW_ORDERS) {
                $orders[] = [
                    'index' => $index,
                    'reference' => $reference,
                    'status' => $status,
                    'duplicateAction' => $duplicate ? $onDuplicate : null,
                    'lineCount' => is_array($payload['lines'] ?? null) ? count($payload['lines']) : 0,
                    'serviceCount' => is_array($payload['services'] ?? null) ? count($payload['services']) : 0,
                    'errors' => $errors,
                ];
            }
        }

        return ['orderCount' => count($payloads), ...$counts, 'orders' => $orders];
    }

    /**
     * Les références déjà présentes chez ce client, en une requête.
     *
     * @param  list<array<string, mixed>>  $payloads
     * @return array<string, true>
     */
    private function existingReferences(CustomerImportConfiguration $configuration, array $payloads): array
    {
        $references = array_values(array_unique(array_filter(array_map(
            static fn (array $payload): ?string => is_scalar($payload['externalReference'] ?? null) ? (string) $payload['externalReference'] : null,
            $payloads,
        ))));

        if ($references === []) {
            return [];
        }

        return Order::where('customer_id', $configuration->customer_id)
            ->whereIn('external_reference', $references)
            ->pluck('external_reference')
            ->mapWithKeys(static fn (string $reference): array => [$reference => true])
            ->all();
    }
}
