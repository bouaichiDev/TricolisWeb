<?php

use App\Modules\Addresses\Models\Address;
use App\Modules\Addresses\Models\EntityAddress;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Orders\Models\Order;
use App\Shared\Database\MorphMap;
use Tests\Support\RecipientImport;

/**
 * Donneur d'ordre **ou** client final : deux parties, deux façons de les nommer.
 *
 * Le donneur d'ordre est connu — son code suffit. Le client final ne l'est
 * pas — le fichier doit tout dire de lui. Une prestation va chez l'un ou chez
 * l'autre, et **l'essai juge comme l'import** : un « valide » suivi d'un refus
 * est le pire des verdicts.
 */
beforeEach(fn () => RecipientImport::prepare($this));

describe('le donneur d’ordre, par son code', function (): void {
    it('retrouve son point enregistré quand la ligne ne décrit pas de client final', function (): void {
        $known = Address::factory()->create(['code' => 'QUAI-NORD']);
        EntityAddress::create([
            'organization_id' => $this->organization->id, 'address_id' => $known->id,
            'entity_type' => MorphMap::CUSTOMER, 'entity_id' => $this->customer->id,
        ]);

        RecipientImport::send($this, RecipientImport::csv(
            ['ADR' => 'QUAI-NORD', ...RecipientImport::NO_RECIPIENT],
            ['REF' => 'CMD-2'],
        ))->assertCreated();

        expect(RecipientImport::service('CMD-1')->address_id)->toBe($known->id)
            ->and(RecipientImport::service('CMD-2')->address->city)->toBe('Genève');
    });

    it('refuse un code inconnu, en renvoyant vers le client final', function (): void {
        $response = RecipientImport::send($this, RecipientImport::csv(['ADR' => 'ADR-CLI-01', ...RecipientImport::NO_RECIPIENT]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('orders.0.services.0.addressCode');

        expect($response->json('errors')['orders.0.services.0.addressCode'][0])->toContain('recipient');
    });

    /** Choisir en silence produirait une commande livrée au mauvais endroit. */
    it('refuse une prestation qui porte à la fois un code et un client final', function (): void {
        RecipientImport::send($this, RecipientImport::csv(['ADR' => 'QUAI-NORD']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('orders.0.services.0.recipient');
    });
});

describe('le client final, en entier', function (): void {
    it('refuse un client final incomplet, en nommant chaque manque', function (): void {
        RecipientImport::send($this, RecipientImport::csv(['DEST_TEL' => '', 'DEST_MAIL' => '', 'DEST_PAYS' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'orders.0.services.0.recipient.phone',
                'orders.0.services.0.recipient.email',
                'orders.0.services.0.recipient.country',
            ]);
    });

    it('refuse un courriel et un pays mal écrits', function (): void {
        RecipientImport::send($this, RecipientImport::csv(['DEST_MAIL' => 'amina', 'DEST_PAYS' => 'Suisse']))
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'orders.0.services.0.recipient.email',
                'orders.0.services.0.recipient.country',
            ]);
    });

    /**
     * Le piège le plus fréquent : les colonnes du client final sont remplies,
     * mais la correspondance ne porte pas `recipient` — elles sont ignorées.
     * Le refus doit le dire, et non parler d'un `addressId` introuvable.
     */
    it('nomme le bloc manquant quand la correspondance ignore le client final', function (): void {
        $mapping = RecipientImport::mapping();
        unset($mapping['services'][0]['recipient']);
        $configuration = RecipientImport::configure($this, $mapping);

        $response = RecipientImport::send($this, RecipientImport::csv([]), 'import', $configuration)
            ->assertStatus(422)
            ->assertJsonMissingValidationErrors('orders.0.services.0.addressId');

        expect($response->json('errors')['orders.0.services.0.addressCode'][0])->toContain('recipient');

        $preview = RecipientImport::send($this, RecipientImport::csv([]), 'preview', $configuration)->assertOk();

        expect($preview->json('data.errors')['services.0.addressCode'][0])->toContain('recipient');
    });

    /** Une correspondance écrite avant la séparation doit dire quoi changer. */
    it('refuse l’ancien bloc « address » en indiquant son remplaçant', function (): void {
        $mapping = RecipientImport::mapping();
        $mapping['services'][0]['address'] = $mapping['services'][0]['recipient'];
        unset($mapping['services'][0]['recipient']);

        RecipientImport::send($this, RecipientImport::csv([]), 'import', RecipientImport::configure($this, $mapping))
            ->assertStatus(422)
            ->assertJsonValidationErrors('orders.0.services.0.address');
    });

    /**
     * La résolution écrit avant la validation : sans la transaction, un fichier
     * refusé laisserait derrière lui adresses et contacts sans commande.
     */
    it('n’abandonne ni adresse ni contact derrière un fichier refusé', function (): void {
        [$addresses, $contacts] = [Address::count(), Contact::count()];

        RecipientImport::send($this, RecipientImport::csv([], ['REF' => 'CMD-2', 'QTE' => '0']))->assertStatus(422);

        expect(Address::count())->toBe($addresses)
            ->and(Contact::count())->toBe($contacts)
            ->and(Order::where('external_reference', 'CMD-1')->exists())->toBeFalse();
    });
});

describe('l’essai juge comme l’import', function (): void {
    it('ne réclame pas de code quand le client final est décrit', function (): void {
        $response = RecipientImport::send($this, RecipientImport::csv([]), 'preview')->assertOk();

        expect($response->json('data.errors'))->toBe([]);
    });

    it('annonce ce qui manque au client final', function (): void {
        $response = RecipientImport::send($this, RecipientImport::csv(['DEST_TEL' => '']), 'preview')->assertOk();

        expect(array_keys($response->json('data.errors')))->toContain('services.0.recipient.phone');
    });

    it('annonce le conflit entre code et client final', function (): void {
        $response = RecipientImport::send($this, RecipientImport::csv(['ADR' => 'QUAI-NORD']), 'preview')->assertOk();

        expect(array_keys($response->json('data.errors')))->toContain('services.0.recipient');
    });

    it('ne crée rien', function (): void {
        [$addresses, $contacts] = [Address::count(), Contact::count()];

        RecipientImport::send($this, RecipientImport::csv([]), 'preview')->assertOk();

        expect(Address::count())->toBe($addresses)->and(Contact::count())->toBe($contacts);
    });
});
