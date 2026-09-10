<?php

declare(strict_types=1);

namespace App\Modules\DeliveryNotes\Services;

/**
 * Les chemins qu'un modèle de bon de livraison peut nommer.
 *
 * C'est ce que l'éditeur de modèle propose, et exactement ce que
 * `DeliveryNoteRenderContext` fournit. Un test tient les deux listes
 * identiques : proposer un chemin que le contexte ne fournit pas ferait échouer
 * le rendu au moment de la génération, quand il est trop tard pour le corriger.
 *
 * `packages` et `articles` sont des **listes** : elles se parcourent par une
 * section — `{{#packages}} … {{/packages}}` — et non par un remplacement.
 */
final readonly class DeliveryNotePaths
{
    /** Les deux chemins sur lesquels une section se répète. */
    public const string PACKAGES = 'packages';

    public const string ARTICLES = 'articles';

    /**
     * @return list<string>
     */
    public static function scalars(): array
    {
        return [
            'deliveryNote.number', 'deliveryNote.issuedAt', 'deliveryNote.issuedAtTime',

            'organization.code', 'organization.name', 'organization.legalName',
            'organization.registrationNumber', 'organization.taxNumber',
            'organization.email', 'organization.phone', 'organization.logo',

            'orderer.code', 'orderer.name', 'orderer.legalName', 'orderer.email', 'orderer.phone',

            'order.orderNumber', 'order.orderDate', 'order.orderType',
            'order.externalReference', 'order.customerReference', 'order.groupCode',
            'order.currencyCode', 'order.remark',

            'service.serviceNumber', 'service.code', 'service.name', 'service.sequence',
            'service.quantity', 'service.unit', 'service.requestedDate',
            'service.requestedFrom', 'service.requestedTo', 'service.weight',
            'service.volume', 'service.packageCount', 'service.instructions', 'service.status',

            'loading.agencyCode', 'loading.agencyName', 'loading.point',
            'loading.depotCode', 'loading.depotName', 'loading.email', 'loading.phone',
            'loading.addressCode', 'loading.name', 'loading.addressLine1', 'loading.addressLine2',
            'loading.postalCode', 'loading.city', 'loading.country', 'loading.instructions',

            'delivery.contactName', 'delivery.contactPhone', 'delivery.contactEmail',
            'delivery.addressCode', 'delivery.name', 'delivery.addressLine1', 'delivery.addressLine2',
            'delivery.postalCode', 'delivery.city', 'delivery.country', 'delivery.instructions',

            'totals.packageCount', 'totals.articleCount', 'totals.packageQuantity',
            'totals.articleQuantity', 'totals.weight', 'totals.volume',
        ];
    }

    /**
     * Champs disponibles **à l'intérieur** d'une section sur les colis.
     *
     * @return list<string>
     */
    public static function packageFields(): array
    {
        return [
            'packages.reference', 'packages.barcode', 'packages.description',
            'packages.packageType', 'packages.groupingType', 'packages.quantity',
            'packages.weight', 'packages.volume', 'packages.length', 'packages.width',
            'packages.height', 'packages.handlingInstructions', 'packages.status',
        ];
    }

    /**
     * Champs disponibles **à l'intérieur** d'une section sur les articles.
     *
     * @return list<string>
     */
    public static function articleFields(): array
    {
        return [
            'articles.articleCode', 'articles.barcode', 'articles.name', 'articles.description',
            'articles.externalReference', 'articles.quantity', 'articles.orderedQuantity',
            'articles.weight', 'articles.volume', 'articles.packageReference', 'articles.packageBarcode',
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            ...self::scalars(),
            self::PACKAGES,
            ...self::packageFields(),
            self::ARTICLES,
            ...self::articleFields(),
        ];
    }
}
