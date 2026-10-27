<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Integrations\DTOs\ImportOutcome;
use App\Modules\Integrations\DTOs\ImportRun;
use App\Modules\Integrations\DTOs\ImportSummary;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\Import\CustomerImportHistory;
use App\Modules\Integrations\Services\Import\ImportPipeline;
use App\Modules\Integrations\Services\Sources\ImportSourceKind;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crée les commandes d'un fichier déposé **depuis le back-office**.
 *
 * L'écriture passe par la chaîne commune (`ImportPipeline` →
 * `ImportCustomerOrderAction`) ; ce qui reste propre à ce canal est sa
 * convention historique : **tout ou rien**. Une seule commande refusée annule
 * le fichier entier, erreurs préfixées par le rang (`orders.1.services.0.unit`),
 * et l'utilisateur corrige puis redépose.
 *
 * La transaction enveloppe tout, résolution comprise : l'adresse d'un client
 * final créée pour une commande disparaît si le fichier est refusé.
 *
 * **Ce que le fichier ne dit pas, l'écran le fournit** : le client vient de la
 * configuration, l'agence est choisie à l'import (`orders.agency_id` est
 * `NOT NULL`), le dépôt reste facultatif.
 */
final readonly class ImportOrdersFromFile
{
    public function __construct(
        private ImportPipeline $pipeline,
        private CustomerImportHistory $history,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     *
     * @throws ValidationException
     */
    public function execute(
        CustomerImportConfiguration $configuration,
        array $rows,
        string $agencyId,
        ?string $depotId,
        string $organizationId,
        User $user,
        Request $request,
    ): ImportSummary {
        $run = ImportRun::make(
            $configuration,
            $organizationId,
            $user,
            $agencyId,
            $depotId,
            ImportSourceKind::orderSource($configuration),
            automated: false,
            request: $request,
            channel: ImportRun::INTERNAL,
        );

        $startedAt = now();
        $context = ['file' => $request->file('file')?->getClientOriginalName()];
        $summary = null;

        try {
            DB::transaction(function () use ($run, $rows, &$summary): void {
                $summary = $this->pipeline->run($run, $rows);
                $errors = [];

                foreach ($summary->outcomes as $outcome) {
                    if (! $outcome->succeeded()) {
                        $errors = [...$errors, ...$this->errorsOf($outcome)];
                    }
                }

                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }
            });
        } catch (ValidationException $exception) {
            // Hors transaction : l'historique d'un fichier refusé survit à l'annulation.
            if ($summary instanceof ImportSummary) {
                $this->history->record($run, $summary, $context, $startedAt, rolledBack: true);
            }

            throw $exception;
        }

        $this->history->record($run, $summary, $context, $startedAt);

        return $summary;
    }

    /**
     * @return array<string, list<string>>
     */
    private function errorsOf(ImportOutcome $outcome): array
    {
        $errors = [];

        foreach ($outcome->errors as $field => $messages) {
            $errors[$field === '_' ? "orders.{$outcome->index}" : $field] = $messages;
        }

        return $errors;
    }
}
