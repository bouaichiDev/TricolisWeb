<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\CustomerImportConfiguration;
use App\Modules\Integrations\Services\ImportPayloadValidator;
use App\Modules\Integrations\Services\ImportReferenceResolver;
use App\Modules\Integrations\Services\MappingInterpreter;
use App\Modules\Orders\Actions\CreateFullOrder;
use App\Modules\Orders\DTOs\CreateOrderData;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crée les commandes décrites par un fichier client.
 *
 * Le fichier a déjà été lu et la correspondance existe : ce qui se joue ici est
 * ce que la prévisualisation ne fait pas — écrire.
 *
 * **Tout ou rien.** Un fichier à moitié importé laisserait un état que personne
 * ne saurait reprendre, et le §4 interdit la table d'historique qui permettrait
 * de le rattraper. Refuser en bloc est la seule issue tenable sans elle.
 *
 * La transaction enveloppe **toute** la méthode, et non la seule création des
 * commandes : la résolution des références écrit elle aussi, depuis qu'une
 * prestation dont le fichier porte l'adresse du destinataire la fait naître.
 * N'ouvrir la transaction qu'après la validation laisserait ces adresses
 * derrière un fichier refusé, sans commande pour les porter ni écran pour les
 * retrouver. L'ordre, lui, ne change pas : rien n'est créé avant que tout soit
 * validé.
 *
 * **Ce que le fichier ne dit pas, l'écran le fournit.** Le client vient de la
 * configuration — elle lui appartient — et l'agence est choisie à l'import,
 * parce que `orders.agency_id` est `NOT NULL` : une commande sans agence
 * n'existe pas. Le dépôt reste facultatif, comme en base.
 */
final readonly class ImportOrdersFromFile
{
    public function __construct(
        private MappingInterpreter $interpreter,
        private ImportReferenceResolver $references,
        private ImportPayloadValidator $validator,
        private CreateFullOrder $orders,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<Order>
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
    ): array {
        /** @var array<string, mixed> $mapping */
        $mapping = $configuration->mapping ?? [];

        return DB::transaction(fn (): array => $this->build(
            $mapping,
            $rows,
            $configuration,
            $agencyId,
            $depotId,
            $organizationId,
            $user,
            $request,
        ));
    }

    /**
     * Le travail lui-même, sous la transaction ouverte par `execute()`.
     *
     * @param  array<string, mixed>  $mapping
     * @param  list<array<string, mixed>>  $rows
     * @return list<Order>
     *
     * @throws ValidationException
     */
    private function build(
        array $mapping,
        array $rows,
        CustomerImportConfiguration $configuration,
        string $agencyId,
        ?string $depotId,
        string $organizationId,
        User $user,
        Request $request,
    ): array {
        $payloads = [];

        foreach ($this->group($mapping, $rows) as $index => $group) {
            $payload = $this->interpreter->apply($mapping, $group);

            // Les codes metier deviennent des identifiants **avant** la
            // validation : `serviceId` et `addressId` sont obligatoires, et un
            // fichier client ne les porte pas.
            $payload = $this->references->resolve(
                $payload,
                $configuration->customer_id,
                $organizationId,
                $index,
            );

            $payloads[] = array_merge($payload, [
                'customerId' => $configuration->customer_id,
                'agencyId' => $agencyId,
                'depotId' => $depotId,
                'source' => $this->sourceOf($configuration)->value,
            ]);
        }

        $this->validator->refuseIfInvalid($payloads);

        return array_map(
            fn (array $payload): Order => $this->orders->execute(
                CreateOrderData::fromArray($payload),
                $organizationId,
                $user,
                $request,
            ),
            $payloads,
        );
    }

    /**
     * Un fichier plat porte souvent **plusieurs** commandes.
     *
     * Elles se reconnaissent à leur référence : les lignes qui partagent la
     * même valeur appartiennent à la même commande. Sans référence
     * correspondante, tout le fichier en décrit une seule — ce qui est le cas
     * d'un export ne contenant qu'un envoi.
     *
     * @param  array<string, mixed>  $mapping
     * @param  list<array<string, mixed>>  $rows
     * @return list<list<array<string, mixed>>>
     */
    private function group(array $mapping, array $rows): array
    {
        $column = $mapping['externalReference'] ?? null;

        if (! is_string($column)) {
            return [$rows];
        }

        $groups = [];

        foreach ($rows as $row) {
            // Une ligne sans référence n'est pas rattachable : elle rejoint le
            // groupe sans nom plutot que d'etre perdue en silence.
            $key = is_scalar($row[$column] ?? null) ? (string) $row[$column] : '';
            $groups[$key][] = $row;
        }

        return array_values($groups);
    }

    /**
     * Le format du fichier, tel que la commande le portera.
     *
     * `source` dit d'où vient une commande, et c'est ce qui permettra de
     * retrouver les commandes importées dans la liste.
     */
    private function sourceOf(CustomerImportConfiguration $configuration): OrderSource
    {
        $format = strtolower((string) $configuration->file_format);

        return match (true) {
            str_contains($format, 'json') => OrderSource::JSON_IMPORT,
            str_contains($format, 'xml') => OrderSource::XML_IMPORT,
            default => OrderSource::CSV_IMPORT,
        };
    }
}
