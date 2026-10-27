<?php

use App\Modules\Integrations\Services\Mapping\MappingPairs;
use App\Modules\Integrations\Services\Mapping\MappingSyntax;
use App\Modules\Integrations\Services\Mapping\ValueCaster;
use App\Modules\Integrations\Services\MappingInterpreter;

/**
 * Le moteur de correspondance (Phase 6 portail, §6) : la convention d'origine
 * tient, les directives s'y ajoutent, et rien n'est jamais exécuté.
 */
beforeEach(function (): void {
    $this->interpreter = new MappingInterpreter;
});

describe('convention d’origine', function (): void {
    it('lit une colonne, omet une colonne vide, fond les doublons', function (): void {
        $payload = $this->interpreter->apply(
            ['externalReference' => 'REF', 'customerReference' => 'VIDE', 'packages' => [['key' => 'COLIS']]],
            [['REF' => ' CMD-1 ', 'VIDE' => '', 'COLIS' => 'A'], ['REF' => 'CMD-1', 'VIDE' => '', 'COLIS' => 'A']],
        );

        expect($payload)->toBe(['externalReference' => 'CMD-1', 'packages' => [['key' => 'A']]]);
    });

    it('recopie une valeur qui ressemble à du code sans l’exécuter', function (): void {
        $payload = $this->interpreter->apply(['customerReference' => 'REF'], [['REF' => 'phpinfo(); system("id")']]);

        expect($payload['customerReference'])->toBe('phpinfo(); system("id")');
    });
});

describe('directives', function (): void {
    it('pose une constante, un défaut et une conversion contrôlée', function (): void {
        $payload = $this->interpreter->apply([
            'orderType' => ['@value' => 'delivery'],
            'groupCode' => ['@source' => 'GROUPE', '@default' => 'STANDARD'],
            'orderDate' => ['@source' => 'DATE', '@type' => 'date'],
        ], [['GROUPE' => '', 'DATE' => '05/10/2026']]);

        expect($payload)->toBe(['orderType' => 'delivery', 'groupCode' => 'STANDARD', 'orderDate' => '2026-10-05']);
    });

    it('parcourt une sous-collection avec @each et remonte au parent par ^', function (): void {
        $payload = $this->interpreter->apply([
            'lines' => [['@each' => 'Items.Item', 'articleCode' => 'Sku', 'externalReference' => '^.OrderNo', 'quantity' => ['@source' => 'Qty', '@type' => 'decimal']]],
        ], [['OrderNo' => 'IK-1', 'Items' => ['Item' => [['Sku' => 'A', 'Qty' => '1,5'], ['Sku' => 'B', 'Qty' => '2']]]]]);

        expect($payload['lines'])->toBe([
            ['articleCode' => 'A', 'externalReference' => 'IK-1', 'quantity' => '1.5'],
            ['articleCode' => 'B', 'externalReference' => 'IK-1', 'quantity' => '2'],
        ]);
    });

    it('traite un élément XML unique comme une liste d’un élément', function (): void {
        $payload = $this->interpreter->apply(
            ['lines' => [['@each' => 'Items.Item', 'articleCode' => 'Sku']]],
            [['Items' => ['Item' => ['Sku' => 'SEUL']]]],
        );

        expect($payload['lines'])->toBe([['articleCode' => 'SEUL']]);
    });
});

describe('conversions', function (): void {
    it('rend la valeur d’origine quand la conversion est impossible', function (): void {
        expect(ValueCaster::cast('douze', 'decimal'))->toBe('douze')
            ->and(ValueCaster::cast('1 250,5', 'decimal'))->toBe('1250.5')
            ->and(ValueCaster::cast('oui', 'boolean'))->toBeTrue()
            ->and(ValueCaster::cast('31/02/2026', 'date'))->toBe('31/02/2026')
            ->and(ValueCaster::cast('42', 'integer'))->toBe(42)
            ->and(ValueCaster::cast('0930', 'time'))->toBe('09:30');
    });
});

describe('syntaxe et diagnostic', function (): void {
    it('refuse une directive inconnue, un type hors liste, @source et @value ensemble', function (): void {
        $problems = MappingSyntax::problems([
            'orderDate' => ['@source' => 'D', '@type' => 'eval'],
            'orderType' => ['@source' => 'T', '@value' => 'x'],
            'lines' => [['@class' => 'App\\Evil', 'name' => 'N']],
            'groupCode' => ['@source' => 'G', '@expression' => '1+1'],
        ]);

        expect($problems)->toHaveCount(4);
    });

    it('accepte la forme historique et signale sans bloquer ce qui manque', function (): void {
        expect(MappingSyntax::problems(['orderNumber' => 'A', 'weight' => 'F']))->toBe([]);

        $diagnosis = MappingSyntax::diagnose(['orderNumber' => 'A', 'lines' => [['quantity' => 'Q']]]);

        expect($diagnosis['unknownTargets'])->toBe(['orderNumber'])
            ->and($diagnosis['missingRequired'])->toContain('orderDate', 'services[].serviceCode');
    });

    it('met la correspondance à plat pour l’affichage', function (): void {
        $pairs = MappingPairs::of([
            '@records' => 'Order',
            'externalReference' => 'OrderNo',
            'lines' => [['@each' => 'Items.Item', 'quantity' => ['@source' => 'Qty', '@type' => 'decimal']]],
            'services' => [['serviceCode' => ['@value' => 'DELIVERY']]],
        ]);

        expect($pairs)->toBe([
            ['target' => 'externalReference', 'source' => 'OrderNo', 'constant' => null, 'type' => null],
            ['target' => 'lines[].quantity', 'source' => 'Items.Item[].Qty', 'constant' => null, 'type' => 'decimal'],
            ['target' => 'services[].serviceCode', 'source' => null, 'constant' => 'DELIVERY', 'type' => null],
        ]);
    });
});
