<?php

use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Contacts\Models\EntityContact;
use App\Modules\Orders\Models\Order;
use App\Shared\Database\MorphMap;
use Tests\Support\RecipientImport;

/**
 * Le **client final**, décrit en entier par le fichier.
 *
 * Il n'existe nulle part avant l'import : le fichier porte son identité, son
 * téléphone, son courriel et son adresse, et l'import crée l'adresse **et** la
 * personne à joindre. Le donneur d'ordre, lui, se désigne par son code — voir
 * `ImportRecipientRulesTest`.
 *
 * Ce que ces tests tiennent, et qui n'est pas évident : ce client final
 * **n'appartient pas au donneur d'ordre**. Son carnet d'adresses décrit ses
 * lieux à lui ; y verser mille destinataires le rendrait inutilisable.
 */
beforeEach(fn () => RecipientImport::prepare($this));

describe('l’adresse du client final', function (): void {
    it('est créée depuis le fichier et portée par la prestation', function (): void {
        RecipientImport::send($this, RecipientImport::csv([]))->assertCreated();

        $address = RecipientImport::service('CMD-1')->address;

        expect($address->address_line_1)->toBe('12 rue du Rhône')
            ->and($address->postal_code)->toBe('1204')
            ->and($address->city)->toBe('Genève')
            ->and($address->name)->toBe('Amina Alaoui');
    });

    /** Le fichier écrit « ch » ; la colonne et le géocodage attendent « CH ». */
    it('écrit le pays en majuscules', function (): void {
        RecipientImport::send($this, RecipientImport::csv([]))->assertCreated();

        expect(RecipientImport::service('CMD-1')->address->country)->toBe('CH');
    });

    it('prend la société pour nom quand le fichier en porte une', function (): void {
        RecipientImport::send($this, RecipientImport::csv(['DEST_SOCIETE' => 'Boulangerie du Rhône']))->assertCreated();

        expect(RecipientImport::service('CMD-1')->address->name)->toBe('Boulangerie du Rhône');
    });

    it('ne lui donne aucun code : il n’appartient pas au donneur d’ordre', function (): void {
        RecipientImport::send($this, RecipientImport::csv([]))->assertCreated();

        $address = RecipientImport::service('CMD-1')->address;

        expect($address->code)->toBeNull()
            ->and(EntityAddress::where('address_id', $address->id)
                ->where('entity_type', MorphMap::CUSTOMER)->exists())->toBeFalse();
    });

    /**
     * `OrderScopeGuard` refuse une adresse qu'aucune liaison ne rattache à
     * l'organisation : sans elle, l'import échouerait sur `addressId` un instant
     * après l'avoir créée.
     */
    it('la rattache à l’organisation, et à rien d’autre', function (): void {
        RecipientImport::send($this, RecipientImport::csv([]))->assertCreated();

        $links = EntityAddress::where('address_id', RecipientImport::service('CMD-1')->address_id)->get();

        expect($links)->toHaveCount(1)
            ->and($links->first()->entity_type)->toBe(MorphMap::ORGANIZATION)
            ->and($links->first()->entity_id)->toBe($this->organization->id);
    });

    it('donne à chaque client final la sienne', function (): void {
        RecipientImport::send($this, RecipientImport::csv(
            [],
            ['REF' => 'CMD-2', 'DEST_RUE' => '4 rue de Lausanne', 'DEST_VILLE' => 'Lausanne', 'DEST_CP' => '1003'],
        ))->assertCreated();

        $cities = Order::whereIn('external_reference', ['CMD-1', 'CMD-2'])->get()
            ->map(fn (Order $order) => $order->orderServices()->firstOrFail()->address->city)
            ->sort()->values()->all();

        expect($cities)->toBe(['Genève', 'Lausanne']);
    });
});

describe('la personne à joindre', function (): void {
    it('est créée dans les contacts, reliée à son adresse pour la livraison', function (): void {
        RecipientImport::send($this, RecipientImport::csv([]))->assertCreated();

        $address = RecipientImport::service('CMD-1')->address;
        $contact = $address->contacts()->firstOrFail();

        expect($contact->first_name)->toBe('Amina')
            ->and($contact->last_name)->toBe('Alaoui')
            ->and($contact->email)->toBe('amina@example.test')
            ->and($contact->phone)->toBe('+41 22 555 01 01')
            ->and($contact->pivot->contact_role)->toBe('delivery')
            ->and((bool) $contact->pivot->is_primary)->toBeTrue();
    });

    /** C'est ce contact que le bon de livraison imprime sous « livraison ». */
    it('est le contact principal de la prestation', function (): void {
        RecipientImport::send($this, RecipientImport::csv([]))->assertCreated();

        $service = RecipientImport::service('CMD-1');
        $snapshot = $service->contacts()->firstOrFail();

        expect($service->contacts()->count())->toBe(1)
            ->and($snapshot->is_primary)->toBeTrue()
            ->and($snapshot->contact_id)->toBe($service->address->contacts()->firstOrFail()->id)
            ->and($snapshot->first_name_snapshot)->toBe('Amina')
            ->and($snapshot->phone_snapshot)->toBe('+41 22 555 01 01')
            ->and($snapshot->email_snapshot)->toBe('amina@example.test');
    });

    it('est rattachée à l’organisation, jamais au donneur d’ordre', function (): void {
        RecipientImport::send($this, RecipientImport::csv([]))->assertCreated();

        $contactId = RecipientImport::service('CMD-1')->contacts()->firstOrFail()->contact_id;
        $links = EntityContact::where('contact_id', $contactId)->get();

        expect($links)->toHaveCount(1)
            ->and($links->first()->entity_type)->toBe(MorphMap::ORGANIZATION)
            ->and($links->first()->organization_id)->toBe($this->organization->id);
    });
});
