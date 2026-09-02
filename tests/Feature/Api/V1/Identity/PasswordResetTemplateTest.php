<?php

use App\Modules\Identity\Mail\PasswordResetMail;
use App\Modules\Identity\Services\PasswordResetUrl;
use App\Modules\Identity\Services\SendPasswordResetLink;
use App\Modules\Organizations\Models\OrganizationUser;
use App\Modules\Templates\Enums\TemplateType;
use App\Modules\Templates\Models\Template;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Le courriel de réinitialisation, écrit par l'organisation.
 *
 * Un texte signé « Laravel » dans la boîte d'un chauffeur ne rassure personne,
 * et l'administrateur n'avait aucun moyen de le corriger.
 */
beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];

    $this->member = OrganizationUser::factory()->forOrganization($this->organization)
        ->create(['is_owner' => false]);

    $this->url = "/api/v1/organization-users/{$this->member->id}/password-reset-link";

    $this->template = fn (array $overrides = []): Template => Template::updateOrCreate(
        ['organization_id' => $this->organization->id, 'code' => 'PASSWORD_RESET'],
        array_merge([
            'name' => 'Réinitialisation',
            'channel' => 'email',
            'template_type' => TemplateType::PASSWORD_RESET->value,
            'subject_template' => 'Votre accès à {{ organization.name }}',
            'body_template' => '<p>Bonjour {{ user.firstName }}, <a href="{{ resetUrl }}">ici</a></p>',
            'language' => 'fr',
            'available_variables' => SendPasswordResetLink::availableVariables(),
            'is_default' => true,
            'is_active' => true,
        ], $overrides),
    );

    $this->send = fn () => $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
        ->postJson($this->url);
});

describe('le modèle de l’organisation', function (): void {
    it('est semé, prêt à être modifié', function (): void {
        $template = Template::where('organization_id', $this->organization->id)
            ->where('template_type', TemplateType::PASSWORD_RESET->value)->first();

        expect($template)->not->toBeNull()
            ->and($template->body_template)->toContain('{{ resetUrl }}')
            ->and($template->declaredVariables())->toContain('resetUrl');
    });

    it('sert d’objet et de corps au courriel', function (): void {
        ($this->template)();
        Mail::fake();

        ($this->send)()->assertOk();

        Mail::assertSent(
            PasswordResetMail::class,
            fn (PasswordResetMail $mail): bool => str_contains(
                (string) $mail->envelope()->subject,
                $this->organization->name,
            ),
        );
    });

    /** Le lien doit s'y trouver, sinon le courriel ne sert à rien. */
    it('remplace le lien dans le corps', function (): void {
        ($this->template)();
        Mail::fake();

        ($this->send)()->assertOk();

        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail): bool {
            $body = $mail->render();

            return str_contains($body, '/reset-password?token=')
                && ! str_contains($body, '{{ resetUrl }}');
        });
    });
});

describe('le repli', function (): void {
    /**
     * Quelqu'un est bloqué dehors : lui refuser son lien parce qu'un texte est
     * fautif serait la mauvaise priorité.
     */
    it('envoie quand même quand le modèle est illisible', function (): void {
        // Un placeholder que le modele ne declare pas : le rendu echoue.
        ($this->template)(['body_template' => '<p>{{ inconnu.champ }}</p>']);
        Notification::fake();

        ($this->send)()->assertOk();

        Notification::assertSentTo($this->member->user, ResetPassword::class);
    });

    it('envoie le courriel par défaut quand aucun modèle n’est actif', function (): void {
        ($this->template)(['is_active' => false]);
        Notification::fake();

        ($this->send)()->assertOk();

        Notification::assertSentTo($this->member->user, ResetPassword::class);
    });
});

/**
 * L'adresse mène à l'interface, pas à l'API : la route de réinitialisation est
 * un `POST`, elle n'afficherait qu'une page blanche.
 */
it('construit un lien vers l’interface', function (): void {
    config(['app.frontend_url' => 'https://tricolis.test']);

    $url = app(PasswordResetUrl::class)
        ->for($this->member->user, 'jeton-123');

    expect($url)->toStartWith('https://tricolis.test/reset-password?token=jeton-123&email=');
});
