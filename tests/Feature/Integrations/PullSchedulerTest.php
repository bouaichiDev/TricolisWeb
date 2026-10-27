<?php

use App\Modules\Integrations\Console\PullCustomerIntegrations;
use App\Modules\Integrations\Jobs\RunCustomerPullIntegrationJob;
use App\Modules\Integrations\Services\Sources\RestPullConnections;
use Database\Seeders\CustomerPortalDemoSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CustomerIntegrations;

/**
 * Le runner générique (§15, §40) : horaire, sans chevauchement, un Job unique
 * par configuration, aucun client nommé.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(CustomerPortalDemoSeeder::class);
    Queue::fake();
});

it('planifie le runner chaque heure, sans chevauchement', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(static fn ($event): bool => str_contains((string) $event->command, 'customer-integrations:pull'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('met en file une passe par configuration automatique prête, et rien d’autre', function (): void {
    $rest = CustomerIntegrations::import('ikea', 'json');
    app(RestPullConnections::class)->save($rest->load('customer'), ['baseUrl' => 'https://api.partner.example.com', 'authType' => 'none'], false, null);
    $sftp = CustomerIntegrations::import('ikea', 'xml');
    CustomerIntegrations::fileConnection($sftp, ['host' => 'sftp.partner.example.com']);

    CustomerIntegrations::import('qoqa', 'xlsx');                                  // dépôt manuel
    CustomerIntegrations::import('qoqa', 'json');                                  // pull sans connexion
    CustomerIntegrations::import('ikea', 'xml', ['is_active' => false]);           // désactivée

    $this->artisan(PullCustomerIntegrations::class)->assertSuccessful();

    Queue::assertPushed(RunCustomerPullIntegrationJob::class, 2);
    Queue::assertPushed(RunCustomerPullIntegrationJob::class, static fn ($job): bool => $job->configurationId === $rest->id);
    Queue::assertPushed(RunCustomerPullIntegrationJob::class, static fn ($job): bool => $job->configurationId === $sftp->id);
});

it('empêche deux passes simultanées d’une même configuration', function (): void {
    $job = new RunCustomerPullIntegrationJob('01JTESTCONFIGURATION000000');
    $middleware = $job->middleware();

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('01JTESTCONFIGURATION000000')
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware[0]->key)->toBe('customer-import-01JTESTCONFIGURATION000000');
});
