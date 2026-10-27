<?php

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Drivers\Models\Driver;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Identity\Models\User;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\Types\Models\TypeItem;
use App\Shared\Enums\UserStatus;
use Database\Seeders\DemoProviderPortalSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\ProviderPortal;

/**
 * Durcissement après relecture de la phase 2 : champs imposés même vides,
 * comptes partagés intouchables, statuts du transporteur respectés, pas de
 * sonde d'e-mail gratuite, erreurs génériques sur tout le portail.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    $this->alpha = ProviderPortal::provider('transports-alpha');
    $type = TypeItem::where('organization_id', $this->alpha->organization_id)->whereHas('type', fn ($q) => $q->where('code', 'vehicle'))->firstOrFail();
    $this->vehicle = Vehicle::factory()->forProvider($this->alpha)->create(['vehicle_type_id' => $type->id]);
    $this->admin = ProviderPortal::headers('admin@transports-alpha.test');
});

it('refuse un champ imposé même vide ou nul, sans erreur 500', function (string $field, mixed $value): void {
    $this->withHeaders($this->admin)->patchJson("/api/v1/provider/vehicles/{$this->vehicle->id}", [$field => $value])
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($this->vehicle->fresh()->provider_id)->toBe($this->alpha->id);
})->with([['providerId', null], ['status', ''], ['organizationId', null]]);

it('ne modifie pas le compte d’un chauffeur partagé ailleurs, et refuse d’en changer le téléphone', function (): void {
    $shared = ProviderPortal::member('admin@transports-beta.test')->user;
    $driver = Driver::factory()->forProvider($this->alpha)->create(['user_id' => $shared->id, 'name' => 'Bruno Admin']);

    $this->withHeaders($this->admin)->patchJson("/api/v1/provider/drivers/{$driver->id}", ['phone' => '+41000'])
        ->assertUnprocessable()->assertJsonValidationErrors('phone');
    $this->withHeaders($this->admin)->patchJson("/api/v1/provider/drivers/{$driver->id}", ['firstName' => 'Pirate'])->assertOk();

    expect($shared->fresh()->first_name)->not->toBe('Pirate')->and($driver->fresh()->name)->toContain('Pirate');
});

it('modifie et audite le compte propre au chauffeur', function (): void {
    $this->withHeaders($this->admin)->postJson('/api/v1/provider/drivers', ['code' => 'OWN-1', 'firstName' => 'Ida', 'lastName' => 'Propre', 'email' => 'ida@alpha.test'])->assertCreated();
    $driver = Driver::where('code', 'OWN-1')->firstOrFail();

    $this->withHeaders($this->admin)->patchJson("/api/v1/provider/drivers/{$driver->id}", ['phone' => '+41791112233'])->assertOk()->assertJsonPath('data.phone', '+41791112233');

    expect(AuditLog::where('action', 'driver.account_updated')->where('entity_id', $driver->id)->exists())->toBeTrue();
});

it('ne touche pas un statut posé par le transporteur', function (string $route): void {
    $this->vehicle->update(['status' => 'maintenance']);

    $this->withHeaders($this->admin)->postJson("/api/v1/provider/vehicles/{$this->vehicle->id}/{$route}")->assertUnprocessable();
    $this->withHeaders($this->admin)->getJson("/api/v1/provider/vehicles/{$this->vehicle->id}")
        ->assertJsonPath('data.actions.canActivate', false)->assertJsonPath('data.actions.canDeactivate', false);

    expect($this->vehicle->fresh()->status)->toBe('maintenance');
})->with(['activate', 'deactivate']);

it('ne juge l’e-mail qu’une fois le reste de la saisie valable', function (): void {
    User::create(['first_name' => 'X', 'last_name' => 'Y', 'email' => 'pris@ailleurs.test', 'password' => 'x-password-1', 'status' => UserStatus::ACTIVE->value]);

    $errors = $this->withHeaders($this->admin)->postJson('/api/v1/provider/drivers', ['code' => '', 'firstName' => 'A', 'lastName' => 'B', 'email' => 'pris@ailleurs.test'])
        ->assertUnprocessable()->json('errors');

    expect($errors)->toHaveKey('code')->not->toHaveKey('email');
});

it('répond 404 avant toute validation pour un chauffeur étranger', function (): void {
    $theirs = Driver::factory()->forProvider(ProviderPortal::provider('transports-beta'))->create(['code' => 'B-ONE']);
    Driver::factory()->forProvider($this->alpha)->create(['code' => 'A-ONE']);

    $this->withHeaders($this->admin)->patchJson("/api/v1/provider/drivers/{$theirs->id}", ['code' => 'A-ONE'])->assertNotFound();
});

it('ne rend jamais le détail d’une erreur imprévue sur le portail fournisseur', function (): void {
    config(['app.debug' => true]);
    Route::middleware(['api'])->get('/api/v1/provider/__boom', static fn () => throw new RuntimeException('SQLSTATE secret detail'));

    $this->getJson('/api/v1/provider/__boom')->assertStatus(500)->assertDontSee('SQLSTATE')->assertDontSee('secret');
});

it('ne crée pas de rattachement au portail pour un compte chauffeur', function (): void {
    $this->withHeaders($this->admin)->postJson('/api/v1/provider/drivers', ['code' => 'OWN-2', 'firstName' => 'Max', 'lastName' => 'Moteur', 'email' => 'max@alpha.test'])->assertCreated();

    expect(ProviderUser::whereHas('user', fn ($q) => $q->where('email', 'max@alpha.test'))->exists())->toBeFalse();
});

it('rend un refus neutre, pas une erreur 500, quand une création concurrente prend le même code', function (): void {
    $type = $this->vehicle->vehicle_type_id;
    Vehicle::creating(function () use ($type): void {
        DB::table('vehicles')->insertOrIgnore([
            'id' => (string) str()->ulid(), 'organization_id' => $this->alpha->organization_id, 'provider_id' => $this->alpha->id,
            'vehicle_type_id' => $type, 'code' => 'RACE-V', 'registration_number' => 'RACE-PLATE', 'payload_capacity' => 1,
            'volume_capacity' => 1, 'pallet_capacity' => 1, 'status' => 'active',
        ]);
    });

    $this->withHeaders($this->admin)->postJson('/api/v1/provider/vehicles', [
        'code' => 'RACE-V', 'registrationNumber' => 'RACE-PLATE', 'vehicleTypeId' => $type,
        'payloadCapacity' => 1, 'volumeCapacity' => 1, 'palletCapacity' => 1,
    ])->assertUnprocessable()->assertJsonPath('errors.code.0', 'Ce code ou cette immatriculation ne peut pas être utilisé.');
});
