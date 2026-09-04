<?php

use App\Modules\Billing\Services\InvoiceRenderContext;
use App\Modules\Organizations\Services\OrganizationLogo;
use Illuminate\Support\Facades\Storage;

/**
 * Ce que le logo devient une fois déposé.
 *
 * Deux usages aux contraintes différentes : l'**écran**, qui veut une image à
 * afficher, et le **PDF de facture**, qui veut des octets à embarquer. D'où une
 * route qui sert le fichier, et un `data:` URI exposé aux modèles — dompdf n'a
 * pas de session et ne peut pas aller chercher une URL.
 *
 * Le dépôt lui-même est vérifié par `OrganizationLogoTest`.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    Storage::fake('local');
});

describe('serving and removing', function (): void {
    it('serves the file with its own type', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->post("/api/v1/organizations/{$this->organization->id}/logo", ['logo' => pngLogo()])
            ->assertOk();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->get("/api/v1/organizations/{$this->organization->id}/logo")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    });

    /** `404` est ce qu'attend une balise `<img>`, et c'est la vérité. */
    it('answers 404 when there is none', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->get("/api/v1/organizations/{$this->organization->id}/logo")
            ->assertNotFound();
    });

    it('removes the file and the flag together', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->post("/api/v1/organizations/{$this->organization->id}/logo", ['logo' => pngLogo()])
            ->assertOk();

        $path = $this->organization->fresh()->logo_path;

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->deleteJson("/api/v1/organizations/{$this->organization->id}/logo")
            ->assertOk()
            ->assertJsonPath('data.hasLogo', false);

        expect($this->organization->fresh()->logo_path)->toBeNull();
        Storage::disk('local')->assertMissing($path);
    });

    it('tells the detail response whether a logo exists', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/organizations/{$this->organization->id}")
            ->assertOk()
            ->assertJsonPath('data.hasLogo', false);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->post("/api/v1/organizations/{$this->organization->id}/logo", ['logo' => pngLogo()])
            ->assertOk();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/organizations/{$this->organization->id}")
            ->assertOk()
            ->assertJsonPath('data.hasLogo', true);
    });
});

describe('the logo inside a document', function (): void {
    /**
     * Le PDF embarque les octets : dompdf n'a pas de session, et une facture
     * qui pointerait vers une URL dépendrait d'un serveur joignable au bon
     * moment.
     */
    it('encodes the logo for a template', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->post("/api/v1/organizations/{$this->organization->id}/logo", ['logo' => pngLogo()])
            ->assertOk();

        $uri = app(OrganizationLogo::class)->dataUri($this->organization->fresh());

        expect($uri)->toStartWith('data:image/png;base64,');
    });

    /**
     * Un `data:` URI vide casserait la mise en page là où une image absente ne
     * fait qu'un trou.
     */
    it('gives nothing when the file is gone', function (): void {
        $this->organization->update(['logo_path' => 'organization-logos/absent.png', 'logo_mime_type' => 'image/png']);

        expect(app(OrganizationLogo::class)->dataUri($this->organization->fresh()))->toBeNull();
    });

    it('offers the path to the template editor', function (): void {
        expect(InvoiceRenderContext::availablePaths())
            ->toContain('organization.logo');
    });
});
