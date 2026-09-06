<?php

use App\Modules\Organizations\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Dépôt du logo d'une organisation.
 *
 * Ce qu'il accepte, et ce qu'il refuse. Le fichier part **entier** dans chaque
 * facture PDF, et le moteur ne rend pas tous les formats : les deux contraintes
 * se lisent dans la validation.
 *
 * Ce que le logo devient ensuite — servi à l'écran, encodé dans un document —
 * est vérifié par `OrganizationLogoDocumentTest`.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    Storage::fake('local');
});

/**
 * La barre latérale porte le logo de l'organisation active, et elle est rendue
 * avant toute autre requête. Sans ce booléen sur l'appartenance, elle devrait
 * charger la fiche entière pour un seul champ — ou tenter le téléchargement à
 * l'aveugle et essuyer un 404 par organisation sans logo.
 */
describe('the membership payload', function (): void {
    it('says whether the organization has a logo', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.organizations.0.hasLogo', false);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->post("/api/v1/organizations/{$this->organization->id}/logo", ['logo' => pngLogo()])
            ->assertOk();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.organizations.0.hasLogo', true);
    });

    /**
     * Le chemin du fichier ne sort toujours pas : il révélerait la disposition
     * du disque, et l'écran n'en a pas besoin.
     */
    it('never carries the file path', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->post("/api/v1/organizations/{$this->organization->id}/logo", ['logo' => pngLogo()])
            ->assertOk();

        $body = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson('/api/v1/auth/me')->assertOk()->getContent();

        expect($body)->not->toContain('organization-logos');
    });
});

describe('uploading a logo', function (): void {
    it('stores the file and flags the organization', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->post("/api/v1/organizations/{$this->organization->id}/logo", ['logo' => pngLogo()])
            ->assertOk()
            ->assertJsonPath('data.hasLogo', true);

        $path = $this->organization->fresh()->logo_path;

        expect($path)->not->toBeNull();
        Storage::disk('local')->assertExists($path);
    });

    /**
     * Le fichier vit sur le disque **privé**. Sous `/storage`, un chemin
     * devinable donnerait le logo d'un organisme à qui l'essaie.
     */
    it('keeps the file off the public disk', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->post("/api/v1/organizations/{$this->organization->id}/logo", ['logo' => pngLogo()])
            ->assertOk();

        expect($this->organization->fresh()->logo_path)->toStartWith('organization-logos/');
    });

    /**
     * L'ancien fichier part **après** que le nouveau est écrit : l'inverse
     * laisserait l'organisation sans logo si l'écriture échouait.
     */
    it('replaces the previous file rather than piling them up', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->post("/api/v1/organizations/{$this->organization->id}/logo", ['logo' => pngLogo('first.png')])
            ->assertOk();

        $first = $this->organization->fresh()->logo_path;

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->post("/api/v1/organizations/{$this->organization->id}/logo", ['logo' => pngLogo('second.png')])
            ->assertOk();

        $second = $this->organization->fresh()->logo_path;

        expect($second)->not->toBe($first);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
    });

    /**
     * Le moteur PDF ne rend ni SVG ni WebP : les accepter donnerait des
     * factures au logo manquant sans qu'aucune erreur ne soit levée.
     */
    it('refuses a format the PDF engine cannot render', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->post("/api/v1/organizations/{$this->organization->id}/logo", [
                'logo' => UploadedFile::fake()->create('logo.svg', 8, 'image/svg+xml'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('logo');
    });

    /** Le fichier part entier dans chaque facture : dix mégaoctets pèseraient. */
    it('refuses a file heavier than a megabyte', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->post("/api/v1/organizations/{$this->organization->id}/logo", [
                'logo' => UploadedFile::fake()->create('logo.png', 2048, 'image/png'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('logo');
    });

    it('records the change in the audit trail', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->post("/api/v1/organizations/{$this->organization->id}/logo", ['logo' => pngLogo()])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'action' => 'organization_logo_updated',
        ]);
    });

    /** Une organisation ne dépose pas de logo chez sa voisine. */
    it('never reaches another organization', function (): void {
        $other = Organization::factory()->create();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->post("/api/v1/organizations/{$other->id}/logo", ['logo' => pngLogo()])
            ->assertForbidden();

        expect($other->fresh()->logo_path)->toBeNull();
    });
});
