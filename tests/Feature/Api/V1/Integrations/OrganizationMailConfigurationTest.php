<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Integrations\Models\OrganizationMailConfiguration;
use App\Modules\Integrations\Services\OrganizationMailer;
use App\Modules\Organizations\Models\OrganizationUser;
use Illuminate\Support\Facades\Crypt;

/**
 * La boîte d'envoi de l'organisation.
 *
 * Deux transporteurs hébergés sur la même installation ne peuvent pas signer
 * leurs courriers du même nom : le client de l'un recevrait une facture venue
 * de l'autre, et se demanderait qui la lui réclame.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->url = '/api/v1/mail-configuration';

    $this->payload = fn (array $overrides = []): array => array_merge([
        'host' => 'smtp.atlas.ch',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'envoi@atlas.ch',
        'password' => 'secret-smtp',
        'fromAddress' => 'contact@atlas.ch',
        'fromName' => 'Atlas Transport',
    ], $overrides);

    $this->put = fn (array $payload) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)->putJson($this->url, $payload);

    $this->get = fn () => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)->getJson($this->url);
});

describe('réglage', function (): void {
    /**
     * L'absence de configuration est un état normal — l'organisation part alors
     * avec la messagerie du projet. Un 404 la ferait passer pour une erreur.
     */
    it('rend nul plutôt qu’une erreur quand rien n’est réglé', function (): void {
        ($this->get)()->assertOk()->assertJsonPath('data', null);
    });

    it('crée la configuration au premier enregistrement', function (): void {
        ($this->put)(($this->payload)())->assertOk()
            ->assertJsonPath('data.host', 'smtp.atlas.ch')
            ->assertJsonPath('data.fromAddress', 'contact@atlas.ch')
            ->assertJsonPath('data.hasPassword', true);

        $this->assertDatabaseHas('organization_mail_configurations', [
            'organization_id' => $this->organization->id,
            'host' => 'smtp.atlas.ch',
        ]);
    });

    /** Une seule identité d'expédition : le second enregistrement remplace. */
    it('remplace au lieu d’empiler', function (): void {
        ($this->put)(($this->payload)())->assertOk();
        ($this->put)(($this->payload)(['host' => 'smtp2.atlas.ch']))->assertOk();

        expect(OrganizationMailConfiguration::count())->toBe(1)
            ->and(OrganizationMailConfiguration::first()->host)->toBe('smtp2.atlas.ch');
    });

    it('supprime, et l’organisation repart sur la messagerie du projet', function (): void {
        ($this->put)(($this->payload)())->assertOk();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson($this->url)->assertNoContent();

        expect(OrganizationMailConfiguration::count())->toBe(0);
    });
});

describe('le mot de passe', function (): void {
    /**
     * Le pire défaut possible ici : un secret SMTP qui traverse une réponse
     * JSON finit dans un journal de requêtes, puis dans une capture d'écran.
     */
    it('ne ressort jamais par l’API', function (): void {
        $response = ($this->put)(($this->payload)())->assertOk();

        expect(json_encode($response->json()))->not->toContain('secret-smtp')
            ->and($response->json('data'))->not->toHaveKey('password')
            ->and($response->json('data'))->not->toHaveKey('encryptedPassword');

        expect(json_encode(($this->get)()->json()))->not->toContain('secret-smtp');
    });

    it('est chiffré en base', function (): void {
        ($this->put)(($this->payload)())->assertOk();

        $stored = OrganizationMailConfiguration::first();

        expect($stored->encrypted_password)->not->toBe('secret-smtp')
            ->and(Crypt::decryptString($stored->encrypted_password))->toBe('secret-smtp');
    });

    /**
     * Rouvrir l'écran pour changer un port ne doit pas obliger à ressaisir un
     * secret qu'on n'a plus sous la main.
     */
    it('reste en place quand la requête ne le porte pas', function (): void {
        ($this->put)(($this->payload)())->assertOk();

        $payload = ($this->payload)(['port' => 465]);
        unset($payload['password']);

        ($this->put)($payload)->assertOk()->assertJsonPath('data.hasPassword', true);

        expect(OrganizationMailConfiguration::first()->password())->toBe('secret-smtp');
    });

    /** Pour l'effacer, il faut le dire. */
    it('s’efface sur une valeur vide explicite', function (): void {
        ($this->put)(($this->payload)())->assertOk();
        ($this->put)(($this->payload)(['password' => null]))->assertOk()
            ->assertJsonPath('data.hasPassword', false);
    });

    /** Un journal se relit longtemps après, par des gens qui n'y ont pas droit. */
    it('n’entre pas dans le journal d’audit', function (): void {
        ($this->put)(($this->payload)())->assertOk();

        $entries = AuditLog::all()
            ->map(fn ($log): string => json_encode([$log->old_values, $log->new_values]));

        foreach ($entries as $entry) {
            expect($entry)->not->toContain('secret-smtp');
        }
    });
});

describe('le choix du mailer', function (): void {
    it('prend la boîte de l’organisation quand elle en a une', function (): void {
        ($this->put)(($this->payload)())->assertOk();

        expect(app(OrganizationMailer::class)->configurationFor($this->organization->id)?->host)
            ->toBe('smtp.atlas.ch');
    });

    /**
     * L'intérêt de l'interrupteur : revenir à la messagerie du projet sans
     * effacer des réglages qu'il faudrait ressaisir.
     */
    it('ignore une configuration désactivée', function (): void {
        ($this->put)(($this->payload)(['isActive' => false]))->assertOk();

        expect(app(OrganizationMailer::class)->configurationFor($this->organization->id))->toBeNull();
    });

    it('n’en cherche pas sans organisation', function (): void {
        expect(app(OrganizationMailer::class)->configurationFor(null))->toBeNull();
    });
});

describe('permissions', function (): void {
    it('refuse à un membre sans droit', function (): void {
        $powerless = OrganizationUser::factory()->forOrganization($this->organization)
            ->create(['is_owner' => false])->user;

        $this->actingAs($powerless, 'sanctum')->withHeaders($this->headers)
            ->getJson($this->url)->assertForbidden();

        $this->actingAs($powerless, 'sanctum')->withHeaders($this->headers)
            ->putJson($this->url, ($this->payload)())->assertForbidden();
    });
});

describe('validation', function (): void {
    it('exige un hôte, un port et une adresse d’expédition', function (): void {
        ($this->put)([])->assertUnprocessable()
            ->assertJsonValidationErrors(['host', 'port', 'fromAddress']);
    });

    /** Les serveurs distinguent `tls`, négocié après la connexion, de `ssl`. */
    it('n’accepte que les chiffrements connus', function (): void {
        ($this->put)(($this->payload)(['encryption' => 'starttls']))->assertUnprocessable()
            ->assertJsonValidationErrors(['encryption']);

        ($this->put)(($this->payload)(['encryption' => null]))->assertOk();
    });
});
