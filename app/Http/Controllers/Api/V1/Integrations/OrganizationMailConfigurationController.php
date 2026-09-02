<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Integrations\UpsertOrganizationMailConfigurationRequest;
use App\Http\Resources\Api\V1\Integrations\OrganizationMailConfigurationResource;
use App\Modules\Integrations\Models\OrganizationMailConfiguration;
use App\Modules\Integrations\Services\OrganizationMailer;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\InputMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Throwable;

/**
 * La boîte d'envoi de l'organisation active.
 *
 * **Une ressource unique, pas une liste.** Une organisation n'a qu'une identité
 * d'expédition ; en autoriser deux poserait la question de savoir laquelle
 * répond, sans qu'aucune réponse ne soit meilleure. L'écriture est donc un
 * `PUT` qui crée ou remplace, jamais un `POST` qui empile.
 *
 * Le mot de passe n'est jamais relu : il ne peut qu'être posé ou remplacé.
 */
class OrganizationMailConfigurationController extends Controller
{
    /**
     * Colonne en base → clé de l'API, sens attendu par `InputMapper`.
     *
     * @var array<string, string>
     */
    private const array MAPPING = [
        'host' => 'host',
        'port' => 'port',
        'encryption' => 'encryption',
        'username' => 'username',
        'from_address' => 'fromAddress',
        'from_name' => 'fromName',
        'reply_to' => 'replyTo',
        'is_active' => 'isActive',
    ];

    /**
     * Consulter la boîte d'envoi. Permission requise : `mail_configuration.view`.
     *
     * Rend `null` plutôt qu'un 404 quand rien n'est réglé : l'absence de
     * configuration est un état normal — l'organisation part alors avec la
     * messagerie du projet — et un 404 la ferait passer pour une erreur.
     */
    public function show(): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('viewAny', [OrganizationMailConfiguration::class, $organizationId]);

        $configuration = OrganizationMailConfiguration::inOrganization($organizationId)->first();

        return ApiResponse::ok(
            $configuration === null ? null : new OrganizationMailConfigurationResource($configuration),
        );
    }

    /**
     * Régler la boîte d'envoi. Permission requise : `mail_configuration.update`.
     *
     * Omettre `password` conserve celui en place ; l'envoyer vide l'efface.
     */
    public function update(UpsertOrganizationMailConfigurationRequest $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('update', [OrganizationMailConfiguration::class, $organizationId]);

        $data = $request->validated();

        $configuration = OrganizationMailConfiguration::inOrganization($organizationId)->first()
            ?? new OrganizationMailConfiguration(['organization_id' => $organizationId]);

        $existed = $configuration->exists;
        $before = $existed ? $this->safe($configuration) : null;

        $configuration->fill(InputMapper::map($data, self::MAPPING));

        if (array_key_exists('password', $data)) {
            $configuration->setPassword($data['password']);
        }

        $configuration->save();

        $this->audit(
            $request,
            $organizationId,
            $existed ? 'updated' : 'created',
            $configuration,
            $before,
            $this->safe($configuration),
        );

        return ApiResponse::ok(new OrganizationMailConfigurationResource($configuration));
    }

    /**
     * Supprimer la boîte d'envoi. Permission requise : `mail_configuration.delete`.
     *
     * L'organisation repart alors avec la messagerie du projet. Pour la couper
     * temporairement sans perdre ses réglages, `isActive` suffit.
     */
    public function destroy(Request $request): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();

        $configuration = OrganizationMailConfiguration::inOrganization($organizationId)->firstOrFail();
        $this->authorize('delete', $configuration);

        $before = $this->safe($configuration);
        $configuration->delete();

        $this->audit($request, $organizationId, 'deleted', $configuration, $before, null);

        return ApiResponse::noContent();
    }

    /**
     * Envoyer un courrier d'essai. Permission requise : `mail_configuration.update`.
     *
     * **Sans cet essai, la première preuve qu'un réglage est faux est une
     * facture qui n'arrive pas.** Un hôte mal orthographié, un port fermé ou un
     * mot de passe périmé échouent silencieusement au fond d'une file
     * d'attente ; ici l'erreur du serveur distant revient telle quelle.
     */
    public function test(Request $request, OrganizationMailer $mailer): JsonResponse
    {
        $organizationId = $this->requireOrganizationId();
        $this->authorize('update', [OrganizationMailConfiguration::class, $organizationId]);

        $configuration = OrganizationMailConfiguration::inOrganization($organizationId)->firstOrFail();
        $recipient = (string) ($request->input('recipient') ?? $configuration->from_address);

        try {
            $mailer->for($organizationId)->raw(
                $this->testBody(),
                static function (Message $message) use ($recipient): void {
                    $message->to($recipient)->subject('Tricolis — essai d’envoi');
                },
            );
        } catch (Throwable $exception) {
            // Le message du serveur distant, pas une reformulation : « 535
            // authentification refusée » se cherche, « envoi impossible » non.
            return ApiResponse::error(
                'L’essai d’envoi a échoué.',
                422,
                ['recipient' => [$exception->getMessage()]],
            );
        }

        return ApiResponse::ok(['recipient' => $recipient]);
    }

    /** Le corps du courrier d'essai : ce qu'il prouve, en une phrase. */
    private function testBody(): string
    {
        return 'Cet essai confirme que votre messagerie d’envoi est correctement réglée.'
            .PHP_EOL.PHP_EOL
            .'Si vous recevez ce message, Tricolis peut envoyer vos courriers depuis votre boîte.';
    }

    /**
     * Ce qu'un journal d'audit a le droit de retenir.
     *
     * Ni le mot de passe ni sa forme chiffrée : un journal se relit longtemps
     * après, par des gens qui n'ont pas à connaître le secret.
     *
     * @return array<string, mixed>
     */
    private function safe(OrganizationMailConfiguration $configuration): array
    {
        return $configuration->only([
            'host', 'port', 'encryption', 'username', 'from_address', 'from_name', 'reply_to', 'is_active',
        ]);
    }
}
