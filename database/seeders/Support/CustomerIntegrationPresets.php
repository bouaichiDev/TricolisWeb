<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * Profils de correspondance **génériques**, pour trois formes de contenu
 * client qu'on rencontre : un XML de commandes imbriquées, un JSON d'API, un
 * classeur plat « une ligne par article ».
 *
 * Ce ne sont pas des importeurs par client : ce sont des données de
 * configuration (`mapping`), que la démonstration et les tests posent sur des
 * `CustomerImportConfiguration`. Le moteur n'en connaît aucune.
 *
 * Les champs de prestation que le contenu ne porte pas (unité, durée,
 * poids…) sont des constantes `@value`, que l'exploitant ajuste.
 */
final class CustomerIntegrationPresets
{
    /**
     * `<Orders><Order><OrderNo/>…<Items><Item/>…</Items></Order></Orders>`.
     *
     * @return array<string, mixed>
     */
    public static function nestedXmlOrders(string $serviceCode): array
    {
        return [
            '@records' => 'Order',
            'externalReference' => 'OrderNo',
            'customerReference' => 'CustomerOrderNo',
            'orderDate' => ['@source' => 'OrderDate', '@type' => 'date'],
            'lines' => [[
                '@each' => 'Items.Item',
                'articleCode' => 'Sku',
                'name' => 'Description',
                'quantity' => ['@source' => 'Quantity', '@type' => 'decimal'],
            ]],
            'services' => [self::delivery($serviceCode, 'DeliveryDate', [
                'firstName' => 'Recipient.FirstName', 'lastName' => 'Recipient.LastName',
                'email' => 'Recipient.Email', 'phone' => 'Recipient.Phone',
                'addressLine1' => 'Recipient.Street', 'postalCode' => 'Recipient.PostalCode',
                'city' => 'Recipient.City', 'country' => 'Recipient.Country',
            ])],
        ];
    }

    /**
     * `{"data": [{"reference": …, "lines": [{…}], "recipient": {…}}], …}`.
     *
     * @return array<string, mixed>
     */
    public static function apiJsonOrders(string $serviceCode): array
    {
        return [
            '@records' => 'data',
            'externalReference' => 'reference',
            'orderDate' => ['@source' => 'created_at', '@type' => 'date'],
            'lines' => [[
                '@each' => 'lines',
                'articleCode' => 'sku',
                'name' => 'label',
                'quantity' => ['@source' => 'qty', '@type' => 'decimal'],
            ]],
            'services' => [self::delivery($serviceCode, 'delivery_date', [
                'firstName' => 'recipient.first_name', 'lastName' => 'recipient.last_name',
                'email' => 'recipient.email', 'phone' => 'recipient.phone',
                'addressLine1' => 'recipient.street', 'postalCode' => 'recipient.zip',
                'city' => 'recipient.city', 'country' => 'recipient.country',
            ])],
        ];
    }

    /**
     * Un classeur « une ligne par article » : les lignes d'une même commande
     * partagent la colonne `Commande`.
     *
     * @return array<string, mixed>
     */
    public static function flatSpreadsheetOrders(string $serviceCode): array
    {
        return [
            'externalReference' => 'Commande',
            'orderDate' => ['@source' => 'Date', '@type' => 'date'],
            'lines' => [[
                'articleCode' => 'Article',
                'name' => 'Désignation',
                'quantity' => ['@source' => 'Quantité', '@type' => 'decimal'],
            ]],
            'services' => [self::delivery($serviceCode, 'Livraison', [
                'firstName' => 'Prénom', 'lastName' => 'Nom', 'email' => 'Courriel', 'phone' => 'Téléphone',
                'addressLine1' => 'Adresse', 'postalCode' => 'NPA', 'city' => 'Localité',
                'country' => ['@source' => 'Pays', '@default' => 'CH'],
            ])],
        ];
    }

    /**
     * La prestation de livraison : ce que le contenu porte (date, client
     * final), le reste en constantes.
     *
     * @param  array<string, mixed>  $recipient
     * @return array<string, mixed>
     */
    private static function delivery(string $serviceCode, string $dateColumn, array $recipient): array
    {
        return [
            'serviceCode' => ['@value' => $serviceCode],
            'serviceNumber' => ['@value' => 'LIV-1'],
            'sequence' => ['@value' => 1],
            'requestedDate' => ['@source' => $dateColumn, '@type' => 'date'],
            'quantity' => ['@value' => 1],
            'unit' => ['@value' => 'commande'],
            'requiredTimeMinutes' => ['@value' => 30],
            'remainingTimeMinutes' => ['@value' => 30],
            'weight' => ['@value' => 0],
            'volume' => ['@value' => 0],
            'packageCount' => ['@value' => 0],
            'recipient' => $recipient,
        ];
    }
}
