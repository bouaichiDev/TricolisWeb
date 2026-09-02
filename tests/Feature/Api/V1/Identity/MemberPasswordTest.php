<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Mail\PasswordResetMail;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Organizations\Models\OrganizationUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Rendre l'accès à un membre qui l'a perdu.
 *
 * Deux chemins, parce que deux situations : le lien par courriel, où
 * l'administrateur ne connaît jamais le mot de passe, et le mot de passe posé,
 * pour les comptes qui ne relèvent pas de boîte.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->member = OrganizationUser::factory()->forOrganization($this->organization)
        ->create(['is_owner' => false]);

    $this->linkUrl = "/api/v1/organization-users/{$this->member->id}/password-reset-link";
    $this->passwordUrl = "/api/v1/organization-users/{$this->member->id}/password";

    /** Un membre porteur de la seule permission nommée, et de rien d'autre. */
    $this->memberWith = function (string $permission): OrganizationUser {
        $membership = OrganizationUser::factory()->forOrganization($this->organization)
            ->create(['is_owner' => false]);

        $role = Role::factory()->create(['organization_id' => $this->organization->id]);

        RolePermission::create([
            'role_id' => $role->id,
            'permission_id' => Permission::where('code', $permission)->firstOrFail()->id,
        ]);

        UserRole::create(['organization_user_id' => $membership->id, 'role_id' => $role->id]);

        return $membership;
    };
});

describe('lien de réinitialisation', function (): void {
    /**
     * Le courriel reprend le modele `password_reset` de l'organisation, seme
     * par defaut : c'est lui qui part, et non la notification de Laravel.
     */
    it('envoie le lien au membre', function (): void {
        Mail::fake();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->linkUrl)->assertOk()
            ->assertJsonPath('data.email', $this->member->user->email);

        Mail::assertSent(PasswordResetMail::class);
    });

    it('journalise l’envoi', function (): void {
        Mail::fake();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->linkUrl)->assertOk();

        expect(AuditLog::where('action', 'password_reset_link_sent')->count())->toBe(1);
    });
});

describe('mot de passe posé', function (): void {
    it('remplace le mot de passe du membre', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->putJson($this->passwordUrl, [
                'password' => 'Tricolis!2026',
                'password_confirmation' => 'Tricolis!2026',
            ])->assertNoContent();

        expect(Hash::check('Tricolis!2026', $this->member->user->fresh()->password))->toBeTrue();
    });

    /**
     * Un mot de passe change justement pour que l'accès précédent cesse.
     * Laisser vivre les sessions ouvertes viderait le geste de son sens.
     */
    it('révoque les jetons du membre', function (): void {
        $this->member->user->createToken('mobile');

        expect($this->member->user->tokens()->count())->toBe(1);

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->putJson($this->passwordUrl, [
                'password' => 'Tricolis!2026',
                'password_confirmation' => 'Tricolis!2026',
            ])->assertNoContent();

        expect($this->member->user->tokens()->count())->toBe(0);
    });

    /** Le pire défaut ici : un secret qui se relit dans un journal. */
    it('ne journalise jamais le mot de passe', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->putJson($this->passwordUrl, [
                'password' => 'Tricolis!2026',
                'password_confirmation' => 'Tricolis!2026',
            ])->assertNoContent();

        foreach (AuditLog::all() as $log) {
            expect(json_encode([$log->old_values, $log->new_values]))->not->toContain('Tricolis!2026');
        }
    });

    /** Une coquille enfermerait le membre dehors : personne ne relira. */
    it('exige la confirmation', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->putJson($this->passwordUrl, [
                'password' => 'Tricolis!2026',
                'password_confirmation' => 'Tricolis!2027',
            ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    });

    /** Aussi exigeant que la réinitialisation publique : le canal l'est moins. */
    it('refuse un mot de passe faible', function (): void {
        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->putJson($this->passwordUrl, ['password' => 'abc', 'password_confirmation' => 'abc'])
            ->assertUnprocessable()->assertJsonValidationErrors(['password']);
    });
});

describe('qui a le droit', function (): void {
    it('refuse à un membre sans la permission', function (): void {
        $powerless = OrganizationUser::factory()->forOrganization($this->organization)
            ->create(['is_owner' => false])->user;

        $this->actingAs($powerless, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->linkUrl)->assertForbidden();
    });

    /**
     * Renommer un compte et pouvoir entrer dedans ne sont pas le même pouvoir :
     * `users.update` ne doit pas suffire.
     */
    it('ne se déduit pas de la permission de modification', function (): void {
        $editor = ($this->memberWith)('users.update');

        $this->actingAs($editor->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->linkUrl)->assertForbidden();
    });

    it('accepte le porteur de la permission dédiée', function (): void {
        Mail::fake();
        $resetter = ($this->memberWith)('users.reset_password');

        $this->actingAs($resetter->user, 'sanctum')->withHeaders($this->headers)
            ->postJson($this->linkUrl)->assertOk();
    });

    /**
     * Sa propre fiche est exclue : se poser un mot de passe ici contournerait
     * la vérification du mot de passe actuel qu'impose le profil.
     */
    it('refuse sur sa propre fiche', function (): void {
        $self = OrganizationUser::where('organization_id', $this->organization->id)
            ->where('user_id', $this->user->id)->firstOrFail();

        $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->putJson("/api/v1/organization-users/{$self->id}/password", [
                'password' => 'Tricolis!2026',
                'password_confirmation' => 'Tricolis!2026',
            ])->assertForbidden();
    });
});
