<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Communications\Actions\ApplyCommunicationTransition;
use App\Modules\Communications\Console\ProcessScheduledCommunications;
use App\Modules\Communications\Enums\CommunicationChannel;
use App\Modules\Communications\Jobs\SendOrderCommunicationJob;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Communications\Services\Senders\CommunicationSender;
use App\Modules\Communications\Services\Senders\CommunicationSenderRegistry;
use App\Modules\Communications\Services\Senders\EmailCommunicationSender;
use App\Modules\Communications\Services\Senders\InternalCommunicationSender;
use App\Modules\Communications\Services\Senders\SenderResult;
use App\Modules\Communications\Services\Senders\SmsCommunicationSender;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/**
 * Transporteur de test : n'atteint aucun tiers, comme le §26 le demande.
 */
final class FakeSender implements CommunicationSender
{
    public int $calls = 0;

    public function __construct(private readonly bool $succeeds = true) {}

    public function send(OrderCommunication $communication): SenderResult
    {
        $this->calls++;

        return $this->succeeds
            ? SenderResult::success('fake-message-id', ['channel' => $communication->channel->value, 'secret_token' => 'ne-doit-pas-fuiter'])
            : SenderResult::failure('Le transporteur a refusé le message.');
    }
}

beforeEach(function (): void {
    $this->seed();
    $this->user = authUser();
    $this->organization = authOrganization();
    $this->headers = ['X-Organization-Id' => $this->organization->id];
    $this->order = Order::factory()->forOrganization($this->organization)->create();

    $this->call = fn (string $action, OrderCommunication $c) => $this->actingAs($this->user, 'sanctum')
        ->withHeaders($this->headers)
        ->postJson("/api/v1/order-communications/{$c->id}/{$action}");
});

describe('queueing', function (): void {
    it('queues a draft and dispatches the job', function (): void {
        Queue::fake();
        $communication = OrderCommunication::factory()->forOrder($this->order)->create();

        ($this->call)('queue', $communication)
            ->assertOk()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.queuedAt', fn (?string $v): bool => $v !== null);

        Queue::assertPushed(SendOrderCommunicationJob::class, 1);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order_communication.queued', 'entity_id' => $communication->id,
        ]);
    });

    it('refuses to queue a communication already sent', function (): void {
        Queue::fake();
        $communication = OrderCommunication::factory()->forOrder($this->order)->sent()->create();

        ($this->call)('queue', $communication)->assertStatus(409);

        Queue::assertNothingPushed();
    });
});

describe('sending', function (): void {
    it('marks the communication sent and records the provider identifiers', function (): void {
        $sender = new FakeSender;
        $this->app->instance(EmailCommunicationSender::class, $sender);

        $communication = OrderCommunication::factory()->forOrder($this->order)->queued()->create();

        (new SendOrderCommunicationJob($communication->id, $this->organization->id))
            ->handle(app(ApplyCommunicationTransition::class), app(CommunicationSenderRegistry::class));

        $communication->refresh();

        expect($sender->calls)->toBe(1)
            ->and($communication->status->value)->toBe('sent')
            ->and($communication->provider_message_id)->toBe('fake-message-id')
            ->and($communication->sent_at)->not->toBeNull();
    });

    it('marks the communication failed and records the reason', function (): void {
        $this->app->instance(EmailCommunicationSender::class, new FakeSender(succeeds: false));

        $communication = OrderCommunication::factory()->forOrder($this->order)->queued()->create();

        (new SendOrderCommunicationJob($communication->id, $this->organization->id))
            ->handle(app(ApplyCommunicationTransition::class), app(CommunicationSenderRegistry::class));

        $communication->refresh();

        expect($communication->status->value)->toBe('failed')
            ->and($communication->error_message)->toBe('Le transporteur a refusé le message.')
            ->and($communication->failed_at)->not->toBeNull();
    });

    it('is idempotent : a second dispatch sends nothing more', function (): void {
        $sender = new FakeSender;
        $this->app->instance(EmailCommunicationSender::class, $sender);

        $communication = OrderCommunication::factory()->forOrder($this->order)->queued()->create();
        $job = new SendOrderCommunicationJob($communication->id, $this->organization->id);

        $job->handle(app(ApplyCommunicationTransition::class), app(CommunicationSenderRegistry::class));
        $job->handle(app(ApplyCommunicationTransition::class), app(CommunicationSenderRegistry::class));

        expect($sender->calls)->toBe(1);
    });

    it('never exposes an unlisted provider key', function (): void {
        $this->app->instance(EmailCommunicationSender::class, new FakeSender);

        $communication = OrderCommunication::factory()->forOrder($this->order)->queued()->create();
        (new SendOrderCommunicationJob($communication->id, $this->organization->id))
            ->handle(app(ApplyCommunicationTransition::class), app(CommunicationSenderRegistry::class));

        $response = $this->actingAs($this->user, 'sanctum')->withHeaders($this->headers)
            ->getJson("/api/v1/order-communications/{$communication->id}")->assertOk();

        expect($response->getContent())->not->toContain('ne-doit-pas-fuiter')
            ->and($response->json('data.providerResponse'))->toBe(['channel' => 'email']);
    });

    it('never journals the body nor the provider response', function (): void {
        $this->app->instance(EmailCommunicationSender::class, new FakeSender);

        $communication = OrderCommunication::factory()->forOrder($this->order)->queued()
            ->create(['body' => 'Contenu confidentiel du message']);

        (new SendOrderCommunicationJob($communication->id, $this->organization->id))
            ->handle(app(ApplyCommunicationTransition::class), app(CommunicationSenderRegistry::class));

        $logs = AuditLog::where('entity_id', $communication->id)->get();

        expect($logs)->not->toBeEmpty();

        foreach ($logs as $log) {
            expect(json_encode([$log->old_values, $log->new_values]))
                ->not->toContain('Contenu confidentiel')
                ->not->toContain('ne-doit-pas-fuiter');
        }
    });
});

describe('real senders', function (): void {
    it('sends an email through the configured mailer', function (): void {
        Mail::fake();
        $communication = OrderCommunication::factory()->forOrder($this->order)->queued()->create();

        $result = app(EmailCommunicationSender::class)->send($communication);

        expect($result->successful)->toBeTrue()
            ->and($result->providerMessageId)->not->toBeNull()
            ->and($result->providerResponse)->toHaveKey('mailer');
    });

    it('fails when the recipient has no email address', function (): void {
        Mail::fake();
        $communication = OrderCommunication::factory()->forOrder($this->order)->queued()
            ->create(['recipient_email' => null]);

        $result = app(EmailCommunicationSender::class)->send($communication);

        expect($result->successful)->toBeFalse()
            ->and($result->errorMessage)->toContain('adresse e-mail');
    });

    it('fails explicitly on a channel with no provider', function (): void {
        $communication = OrderCommunication::factory()->forOrder($this->order)->sms()->queued()->create();

        $result = app(SmsCommunicationSender::class)->send($communication);

        expect($result->successful)->toBeFalse()
            ->and($result->errorMessage)->toContain('agrégateur SMS');
    });

    it('always succeeds on the internal channel', function (): void {
        $communication = OrderCommunication::factory()->forOrder($this->order)->queued()
            ->create(['channel' => CommunicationChannel::INTERNAL_NOTIFICATION, 'recipient_email' => null]);

        $result = app(InternalCommunicationSender::class)->send($communication);

        expect($result->successful)->toBeTrue()
            ->and($result->providerMessageId)->toBe($communication->id);
    });
});

describe('cancel and retry', function (): void {
    it('cancels a draft, a scheduled and a queued communication', function (): void {
        Queue::fake();

        $communications = [
            OrderCommunication::factory()->forOrder($this->order)->create(),
            OrderCommunication::factory()->forOrder($this->order)->scheduled()->create(),
            OrderCommunication::factory()->forOrder($this->order)->queued()->create(),
        ];

        foreach ($communications as $communication) {
            ($this->call)('cancel', $communication)->assertOk()->assertJsonPath('data.status', 'cancelled');
        }
    });

    it('refuses to cancel a communication already sent', function (): void {
        $communication = OrderCommunication::factory()->forOrder($this->order)->sent()->create();

        ($this->call)('cancel', $communication)->assertStatus(409);
    });

    it('retries a failed communication and clears the previous error', function (): void {
        Queue::fake();
        $communication = OrderCommunication::factory()->forOrder($this->order)->failed('Erreur precedente')->create();

        ($this->call)('retry', $communication)
            ->assertOk()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.errorMessage', null)
            ->assertJsonPath('data.failedAt', null);

        Queue::assertPushed(SendOrderCommunicationJob::class, 1);
    });

    it('refuses to retry a communication that was sent or is still a draft', function (): void {
        Queue::fake();

        ($this->call)('retry', OrderCommunication::factory()->forOrder($this->order)->sent()->create())
            ->assertStatus(409);

        ($this->call)('retry', OrderCommunication::factory()->forOrder($this->order)->create())
            ->assertStatus(409);

        Queue::assertNothingPushed();
    });
});

describe('scheduled processing', function (): void {
    it('queues only due scheduled communications', function (): void {
        Queue::fake();

        $due = OrderCommunication::factory()->forOrder($this->order)->scheduled(now()->subMinutes(5))->create();
        $future = OrderCommunication::factory()->forOrder($this->order)->scheduled(now()->addHour())->create();
        $draft = OrderCommunication::factory()->forOrder($this->order)->create();

        $this->artisan(ProcessScheduledCommunications::class)->assertSuccessful();

        expect($due->fresh()->status->value)->toBe('queued')
            ->and($future->fresh()->status->value)->toBe('scheduled')
            ->and($draft->fresh()->status->value)->toBe('draft');

        Queue::assertPushed(SendOrderCommunicationJob::class, 1);
    });

    it('does not queue the same communication twice', function (): void {
        Queue::fake();
        OrderCommunication::factory()->forOrder($this->order)->scheduled(now()->subMinute())->create();

        $this->artisan(ProcessScheduledCommunications::class)->assertSuccessful();
        $this->artisan(ProcessScheduledCommunications::class)->assertSuccessful();

        Queue::assertPushed(SendOrderCommunicationJob::class, 1);
    });
});
