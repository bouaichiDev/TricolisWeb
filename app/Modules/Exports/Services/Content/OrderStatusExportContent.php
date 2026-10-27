<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services\Content;

use App\Modules\Exports\Enums\ExportFormat;
use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Models\ExportJob;
use App\Modules\Orders\Models\Order;
use App\Shared\Database\MorphMap;
use RuntimeException;

/**
 * Le statut d'une commande, poussé vers le système du client (REST Push).
 *
 * **Jamais la commande sérialisée** : seuls les champs de `ORDER_FIELDS` et
 * `SERVICE_FIELDS` partent, renommables par `settings.fieldMapping`
 * (`{"reference": "orderRef", "services.status": "state"}`), complétés par
 * `settings.staticValues`. Aucun identifiant interne, aucun prix, aucune
 * adresse ni personne.
 *
 * Le contenu reflète l'état **au moment de l'envoi** : une relance transmet
 * le statut courant, pas une photographie périmée.
 */
final readonly class OrderStatusExportContent implements ExportContentBuilder
{
    public const string EXPORT_TYPE = 'order_status';

    /** Fréquence : à chaque changement de statut de la commande. */
    public const string ON_STATUS_CHANGED = 'on_order_status_changed';

    private const array ORDER_FIELDS = ['reference', 'externalReference', 'customerReference', 'status', 'orderDate', 'eventDate'];

    private const array SERVICE_FIELDS = ['serviceReference', 'sequence', 'status', 'requestedDate'];

    public function build(ExportJob $job, CustomerExportConfiguration $configuration): ExportContent
    {
        if ($configuration->format !== ExportFormat::JSON) {
            throw new RuntimeException('Le statut de commande ne s’exporte qu’en JSON.');
        }

        $order = $job->entity_type === MorphMap::ORDER && $job->entity_id !== null
            ? Order::with('orderServices')->find($job->entity_id)
            : null;

        if ($order === null) {
            throw new RuntimeException('La commande visée n’existe plus.');
        }

        if ($configuration->customer_id !== $order->customer_id || $job->customer_id !== $order->customer_id) {
            throw new RuntimeException('Cette destination n’appartient pas au client de la commande.');
        }

        $settings = $configuration->settings ?? [];
        $map = is_array($settings['fieldMapping'] ?? null) ? $settings['fieldMapping'] : [];
        $payload = $this->pick($this->orderValues($order), self::ORDER_FIELDS, $map, '');

        if (($settings['includeServices'] ?? true) !== false) {
            $payload[$this->name($map, 'services', 'services')] = $order->orderServices
                ->sortBy('sequence')
                ->map(fn ($service): array => $this->pick([
                    'serviceReference' => $service->service_number,
                    'sequence' => $service->sequence,
                    'status' => $service->status?->value ?? $service->status,
                    'requestedDate' => $service->requested_date?->format('Y-m-d'),
                ], self::SERVICE_FIELDS, $map, 'services.'))
                ->values()
                ->all();
        }

        foreach (is_array($settings['staticValues'] ?? null) ? $settings['staticValues'] : [] as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $payload[$key] = $value;
            }
        }

        return new ExportContent(
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $this->fileName($configuration, $order),
            'application/json',
        );
    }

    /** @return array<string, mixed> */
    private function orderValues(Order $order): array
    {
        return [
            'reference' => $order->order_number,
            'externalReference' => $order->external_reference,
            'customerReference' => $order->customer_reference,
            'status' => $order->status?->value,
            'orderDate' => $order->order_date?->format('Y-m-d'),
            'eventDate' => $order->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $fields
     * @param  array<mixed>  $map
     * @return array<string, mixed>
     */
    private function pick(array $values, array $fields, array $map, string $prefix): array
    {
        $picked = [];

        foreach ($fields as $field) {
            $picked[$this->name($map, $prefix.$field, $field)] = $values[$field] ?? null;
        }

        return $picked;
    }

    /** @param  array<mixed>  $map */
    private function name(array $map, string $path, string $fallback): string
    {
        $name = $map[$path] ?? null;

        return is_string($name) && preg_match('/^[A-Za-z_][A-Za-z0-9_.-]{0,63}$/', trim($name)) === 1 ? trim($name) : $fallback;
    }

    private function fileName(CustomerExportConfiguration $configuration, Order $order): string
    {
        $pattern = trim((string) $configuration->file_name_pattern);
        $name = $pattern === ''
            ? 'order-status-{orderNumber}.json'
            : strtr($pattern, ['{orderNumber}' => (string) $order->order_number, '{format}' => 'json']);
        $name = preg_replace('/\{[^}]*\}/', '', $name) ?? $name;
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($name)) ?? '';

        return trim($clean, '.-') === '' ? 'order-status.json' : $clean;
    }
}
