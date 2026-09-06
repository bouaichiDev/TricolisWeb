<?php

use App\Modules\Platform\Models\PlatformSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * La configuration de l'installation.
 *
 * Un seul réglage aujourd'hui — le logo par défaut — et une asymétrie qui porte
 * tout le reste : **lire est public, écrire ne l'est pas**. La barre latérale de
 * chacun demande s'il existe un logo par défaut, et l'écran de connexion aussi,
 * qui s'affiche sans session ; protéger cette question obligerait à distribuer
 * une permission plateforme pour afficher une image de marque.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    Storage::fake('local');
});

describe('reading', function (): void {
    it('is open to any authenticated account', function (): void {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/configuration')
            ->assertOk()
            ->assertJsonPath('data.hasDefaultLogo', false);
    });

    /**
     * Hors du middleware `organization` : la plateforme n'agit dans aucune
     * organisation, et exiger l'en-tête interdirait l'accès à un compte qui n'en
     * a pas.
     */
    it('answers without an organization header', function (): void {
        $this->actingAs(makePlatformAdmin($this->user), 'sanctum')
            ->getJson('/api/v1/configuration')
            ->assertOk();
    });

    /**
     * L'écran de connexion s'affiche pour des gens qui n'ont pas de jeton, et
     * il y pose le logo de l'installation. Exiger une session ici l'obligerait
     * à se signer d'une icône générique.
     */
    it('answers an anonymous caller, for the login screen', function (): void {
        $this->getJson('/api/v1/configuration')
            ->assertOk()
            ->assertJsonPath('data.hasDefaultLogo', false);
    });

    it('answers 404 when there is no default logo', function (): void {
        $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/configuration/logo')
            ->assertNotFound();
    });
});

describe('writing', function (): void {
    /**
     * Le propriétaire d'un organisme détient pourtant tout chez lui : c'est
     * exactement le cas que `hasPlatformPermission` écarte, `hasPermission`
     * étant bornée à une organisation.
     */
    it('refuses an organization owner, however powerful', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->post('/api/v1/configuration/logo', ['logo' => pngLogo()])
            ->assertForbidden();
    });

    it('accepts a platform administrator', function (): void {
        $this->actingAs(makePlatformAdmin($this->user), 'sanctum')
            ->post('/api/v1/configuration/logo', ['logo' => pngLogo()])
            ->assertOk()
            ->assertJsonPath('data.hasDefaultLogo', true);

        $path = PlatformSetting::current()->default_logo_path;

        expect($path)->not->toBeNull();
        Storage::disk('local')->assertExists($path);
    });

    /**
     * Le fichier vit sur le disque **privé**, comme celui d'une organisation.
     * Deux façons de servir la même chose en feraient une à protéger et une à
     * oublier.
     */
    it('keeps the file off the public disk', function (): void {
        $this->actingAs(makePlatformAdmin($this->user), 'sanctum')
            ->post('/api/v1/configuration/logo', ['logo' => pngLogo()])
            ->assertOk();

        // Le chemin dit le disque : `local` le sert par une route, `public`
        // l'exposerait sous `/storage` à qui devine l'adresse.
        expect(PlatformSetting::current()->default_logo_path)->toStartWith('platform-logo/');
        Storage::disk('local')->assertExists(PlatformSetting::current()->default_logo_path);
    });

    it('replaces the previous file rather than piling them up', function (): void {
        $admin = makePlatformAdmin($this->user);

        $this->actingAs($admin, 'sanctum')
            ->post('/api/v1/configuration/logo', ['logo' => pngLogo('first.png')])->assertOk();

        $first = PlatformSetting::current()->default_logo_path;

        $this->actingAs($admin, 'sanctum')
            ->post('/api/v1/configuration/logo', ['logo' => pngLogo('second.png')])->assertOk();

        $second = PlatformSetting::current()->default_logo_path;

        expect($second)->not->toBe($first);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
    });

    /**
     * Le SVG est le format naturel d'un logo, et le moteur PDF ne le rend pas.
     * L'accepter ici pour le refuser sur une organisation ferait croire à un
     * bug — voir `StorePlatformLogoRequest`.
     */
    it('refuses a format the PDF engine cannot render', function (): void {
        $this->actingAs(makePlatformAdmin($this->user), 'sanctum')
            ->post('/api/v1/configuration/logo', [
                'logo' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
            ])
            ->assertStatus(422);
    });

    it('serves the file once it is there, then forgets it on removal', function (): void {
        $admin = makePlatformAdmin($this->user);

        $this->actingAs($admin, 'sanctum')
            ->post('/api/v1/configuration/logo', ['logo' => pngLogo()])->assertOk();

        $this->actingAs($this->user, 'sanctum')->get('/api/v1/configuration/logo')->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/v1/configuration/logo')
            ->assertOk()
            ->assertJsonPath('data.hasDefaultLogo', false);

        $this->actingAs($this->user, 'sanctum')->get('/api/v1/configuration/logo')->assertNotFound();
    });
});

describe('what never leaves', function (): void {
    /**
     * Le chemin révélerait la disposition du disque, et l'écran n'en a pas
     * besoin : il lui suffit de savoir s'il doit demander l'image.
     */
    it('never carries the file path', function (): void {
        $this->actingAs(makePlatformAdmin($this->user), 'sanctum')
            ->post('/api/v1/configuration/logo', ['logo' => pngLogo()])->assertOk();

        $body = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/configuration')->assertOk()->getContent();

        expect($body)->not->toContain('platform-logo');
    });

    /**
     * La table ne porte qu'une ligne, et la contrainte le dit. Deux
     * enregistrements concurrents en laisseraient deux, et la lecture prendrait
     * celle que l'ordre SQL veut bien rendre.
     */
    it('keeps a single row, whatever the number of writes', function (): void {
        $admin = makePlatformAdmin($this->user);

        $this->actingAs($admin, 'sanctum')
            ->post('/api/v1/configuration/logo', ['logo' => pngLogo()])->assertOk();
        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/v1/configuration/logo')->assertOk();

        expect(PlatformSetting::count())->toBe(1);
    });
});
