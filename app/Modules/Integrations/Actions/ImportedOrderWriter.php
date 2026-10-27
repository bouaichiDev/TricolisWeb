<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions;

use App\Modules\Integrations\DTOs\ImportRun;
use App\Modules\Integrations\Services\ImportPayloadValidator;
use App\Modules\Integrations\Services\ImportReferenceResolver;
use App\Modules\Orders\Actions\CreateFullOrder;
use App\Modules\Orders\Actions\ReplaceOrderContent;
use App\Modules\Orders\DTOs\CreateOrderData;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderContentEngagement;
use App\Modules\Statuses\Services\StatusMachine;
use App\Shared\Database\MorphMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Écrit une commande importée — **par les Actions Order existantes**, jamais
 * par un chemin parallèle.
 *
 * Création : `CreateFullOrder`, comme une saisie interne ou portail — même
 * numérotation, mêmes contrôles de portée, même événement `OrderCreated`.
 * Mise à jour : `ReplaceOrderContent`, sous les mêmes conditions que le
 * portail (statut qui admet un changement de contenu, rien d'engagé).
 *
 * **Une transaction par commande.** La résolution des références peut créer
 * l'adresse d'un client final : si la commande est refusée, cette adresse
 * disparaît avec elle. Une commande ne naît jamais sans ses lignes.
 */
final readonly class ImportedOrderWriter
{
    /** Colonnes d'en-tête qu'un import peut réécrire. */
    private const array UPDATABLE_HEADER = ['external_reference', 'customer_reference', 'order_type', 'group_code', 'order_date', 'worker_remark'];

    public function __construct(
        private ImportReferenceResolver $references,
        private ImportPayloadValidator $validator,
        private CreateFullOrder $orders,
        private ReplaceOrderContent $replace,
        private StatusMachine $statuses,
        private OrderContentEngagement $engagement,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function create(ImportRun $run, array $payload, int $index): Order
    {
        return DB::transaction(function () use ($run, $payload, $index): Order {
            $full = $this->prepare($run, $payload, $index);

            return $this->orders->execute(
                CreateOrderData::fromArray($full),
                $run->organizationId,
                $run->actor,
                $run->request,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function update(ImportRun $run, Order $existing, array $payload, int $index): Order
    {
        return DB::transaction(function () use ($run, $existing, $payload, $index): Order {
            $locked = Order::whereKey($existing->id)->lockForUpdate()->firstOrFail();

            if (! $this->statuses->allowsContentChanges(MorphMap::ORDER, $locked->status?->value)
                || $this->engagement->engaged([$locked->id]) !== []) {
                throw ValidationException::withMessages(["orders.{$index}.externalReference" => [
                    "La commande {$locked->order_number} n’est plus modifiable (statut ou planification) : la mise à jour est refusée.",
                ]]);
            }

            $full = $this->prepare($run, $payload, $index);
            $header = array_intersect_key(
                CreateOrderData::fromArray($full)->attributes,
                array_flip(self::UPDATABLE_HEADER),
            );
            $header['order_date'] ??= $locked->order_date;

            return $this->replace->execute(
                $locked,
                $header,
                $full['lines'] ?? [],
                $full['packages'] ?? [],
                $full['services'] ?? [],
                $run->actor,
                $run->request?->ip(),
            );
        });
    }

    /**
     * Références résolues, périmètre imposé, charge validée comme une saisie.
     *
     * La fusion se fait **dans ce sens** : ce que l'exécution impose (client,
     * agence, origine) écrase ce que le contenu aurait prétendu fournir.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function prepare(ImportRun $run, array $payload, int $index): array
    {
        // Un contenu venu du client ne décide ni du statut, ni du numéro, ni
        // d'une remarque interne : seul un import lancé par le transporteur
        // lui-même garde ces champs, comme une saisie interne.
        if ($run->channel !== ImportRun::INTERNAL) {
            unset($payload['status'], $payload['orderNumber'], $payload['internalRemark'], $payload['currencyCode']);
        }

        $resolved = $this->references->resolve($payload, $run->customerId(), $run->organizationId, $index);

        $full = array_merge($resolved, [
            'customerId' => $run->customerId(),
            'agencyId' => $run->agencyId,
            'depotId' => $run->depotId,
            'source' => $run->source->value,
        ]);

        $errors = $this->validator->errors($full, $index);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $full;
    }
}
