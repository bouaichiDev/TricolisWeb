<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions;

use App\Modules\Integrations\DTOs\ImportOutcome;
use App\Modules\Integrations\DTOs\ImportRun;
use App\Modules\Integrations\Services\Validation\ImportValidationRules;
use App\Modules\Orders\Models\Order;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * **Le** point d'arrivée de tout import de commande : fichier déposé (CSV,
 * Excel, XML, JSON), dépôt FTP/SFTP, pull REST. Aucun canal ne crée une
 * commande autrement.
 *
 * ## Idempotence — Customer + externalReference
 *
 * `orders` n'a aucune contrainte d'unicité sur `external_reference` ; en
 * ajouter une modifierait une table existante (non fait, voir rapport). La
 * garantie est donc applicative :
 *
 * 1. un **verrou** par couple (client, référence) sérialise deux exécutions
 *    concurrentes du même contenu ;
 * 2. sous ce verrou, la commande existante est cherchée ;
 * 3. absente → CREATE ; présente → selon `@onDuplicate` de la configuration :
 *    `skip` (défaut), `reject` (erreur), `update` (réécriture, seulement si la
 *    commande l'admet encore).
 *
 * Sans référence, aucune idempotence n'est possible : un import automatique
 * l'exige donc (`requireReference`), un dépôt manuel peut s'en passer.
 */
final readonly class ImportCustomerOrderAction
{
    private const int LOCK_SECONDS = 120;

    private const int LOCK_WAIT_SECONDS = 20;

    public function __construct(private ImportedOrderWriter $writer) {}

    /**
     * @param  array<string, mixed>  $payload  charge produite par la correspondance
     */
    public function execute(ImportRun $run, array $payload, int $index): ImportOutcome
    {
        $reference = $this->referenceOf($payload);
        $errors = $this->configuredErrors($run, $payload, $index, $reference);

        if ($errors !== []) {
            return ImportOutcome::invalid($index, $reference, $errors);
        }

        if ($reference === null) {
            return $this->guard($index, null, fn (): ImportOutcome => ImportOutcome::done(
                ImportOutcome::CREATED,
                $index,
                null,
                $this->writer->create($run, $payload, $index),
            ));
        }

        $key = 'customer-import:'.$run->customerId().':'.sha1($reference);

        try {
            // Store partagé entre processus : un verrou `array` ne protégerait
            // pas deux workers l'un de l'autre.
            return Cache::store((string) config('customer_integrations.cache_store', 'database'))
                ->lock($key, self::LOCK_SECONDS)->block(
                    self::LOCK_WAIT_SECONDS,
                    fn (): ImportOutcome => $this->reconcile($run, $payload, $index, $reference),
                );
        } catch (LockTimeoutException) {
            return ImportOutcome::failed($index, $reference, 'Cette référence est déjà en cours d’import : réessayez plus tard.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function reconcile(ImportRun $run, array $payload, int $index, string $reference): ImportOutcome
    {
        $existing = Order::where('organization_id', $run->organizationId)
            ->where('customer_id', $run->customerId())
            ->where('external_reference', $reference)
            ->orderBy('id')
            ->first();

        if ($existing === null) {
            return $this->guard($index, $reference, fn (): ImportOutcome => ImportOutcome::done(
                ImportOutcome::CREATED,
                $index,
                $reference,
                $this->writer->create($run, $payload, $index),
            ));
        }

        return match ($run->onDuplicate) {
            'update' => $this->guard($index, $reference, fn (): ImportOutcome => ImportOutcome::done(
                ImportOutcome::UPDATED,
                $index,
                $reference,
                $this->writer->update($run, $existing, $payload, $index),
            )),
            'reject' => ImportOutcome::invalid($index, $reference, ["orders.{$index}.externalReference" => [
                "La référence {$reference} a déjà été importée (commande {$existing->order_number}).",
            ]]),
            default => ImportOutcome::done(ImportOutcome::SKIPPED_DUPLICATE, $index, $reference, $existing),
        };
    }

    /**
     * Une erreur métier devient `invalid` ; toute autre, `failed` — sans que
     * son message technique, qui pourrait trahir l'infrastructure, n'atteigne
     * l'utilisateur. La transaction de la commande a déjà tout annulé.
     *
     * @param  callable(): ImportOutcome  $work
     */
    private function guard(int $index, ?string $reference, callable $work): ImportOutcome
    {
        try {
            return $work();
        } catch (ValidationException $exception) {
            return ImportOutcome::invalid($index, $reference, $exception->errors());
        } catch (Throwable $exception) {
            report($exception);

            return ImportOutcome::failed($index, $reference, 'Erreur technique pendant l’enregistrement de cette commande.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, list<string>>
     */
    private function configuredErrors(ImportRun $run, array $payload, int $index, ?string $reference): array
    {
        $errors = [];

        foreach (ImportValidationRules::errors($payload, $run->validationRules()) as $field => $messages) {
            $errors["orders.{$index}.{$field}"] = $messages;
        }

        if ($reference === null && $run->requireReference) {
            $errors["orders.{$index}.externalReference"][] = 'Une référence externe est obligatoire : sans elle, un nouvel envoi du même contenu dupliquerait la commande.';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function referenceOf(array $payload): ?string
    {
        $reference = $payload['externalReference'] ?? null;

        return is_scalar($reference) && trim((string) $reference) !== '' ? trim((string) $reference) : null;
    }
}
