<?php

use App\Modules\Drivers\Models\Driver;
use App\Modules\Identity\Models\User;
use App\Modules\ProviderPortal\Models\ProviderUser;
use App\Modules\Tours\Enums\TourStatus;
use App\Modules\Tours\Models\Tour;
use App\Shared\Enums\UserStatus;
use Database\Seeders\DemoProviderPortalSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\ProviderPortal;

/**
 * Chauffeurs du portail fournisseur : chacun ne voit et ne touche que les
 * siens. Un chauffeur d'un autre fournisseur ou du transporteur répond 404,
 * comme un identifiant inexistant.
 */
beforeEach(function (): void {
    $this->seed();
    $this->seed(DemoProviderPortalSeeder::class);
    $this->alpha = ProviderPortal::provider('transports-alpha');
    $this->beta = ProviderPortal::provider('transports-beta');
    $this->mine = Driver::factory()->forProvider($this->alpha)->create(['code' => 'ALPHA-01', 'name' => 'Ali Alpha']);
    $this->theirs = Driver::factory()->forProvider($this->beta)->create(['code' => 'BETA-01', 'name' => 'Bea Beta']);
    $this->carrier = Driver::factory()->create(['organization_id' => $this->alpha->organization_id, 'provider_id' => null, 'code' => 'OWN-01']);
    $this->admin = ProviderPortal::headers('admin@transports-alpha.test');
});

const NEW_DRIVER = ['code' => 'ALPHA-02', 'firstName' => 'Nadia', 'lastName' => 'Neuve', 'email' => 'nadia@alpha.test', 'phone' => '+41790000000'];

it('ne liste que les chauffeurs du fournisseur courant', function (): void {
    $codes = collect($this->withHeaders($this->admin)->getJson('/api/v1/provider/drivers?perPage=100')->assertOk()->json('data'))->pluck('code');

    expect($codes->all())->toBe(['ALPHA-01']);
    $this->withHeaders($this->admin)->getJson('/api/v1/provider/drivers?search=BETA')->assertOk()->assertJsonCount(0, 'data');
});

it('répond 404 pour un chauffeur étranger, du transporteur ou inexistant, sans rien modifier', function (string $who): void {
    $id = match ($who) {
        'autre fournisseur' => $this->theirs->id, 'transporteur' => $this->carrier->id, 'inexistant' => '01JZZZZZZZZZZZZZZZZZZZZZZZ'
    };

    $this->withHeaders($this->admin)->getJson("/api/v1/provider/drivers/{$id}")->assertNotFound();
    $this->withHeaders($this->admin)->patchJson("/api/v1/provider/drivers/{$id}", ['code' => 'VOLE'])->assertNotFound();
    $this->withHeaders($this->admin)->postJson("/api/v1/provider/drivers/{$id}/deactivate")->assertNotFound();

    expect(Driver::where('code', 'VOLE')->exists())->toBeFalse();
})->with(['autre fournisseur', 'transporteur', 'inexistant']);

it('crée un chauffeur rattaché au fournisseur courant, avec un compte sans accès au portail', function (): void {
    $this->withHeaders($this->admin)->postJson('/api/v1/provider/drivers', NEW_DRIVER)
        ->assertCreated()
        ->assertJsonPath('data.code', 'ALPHA-02')
        ->assertJsonPath('data.status', 'active');

    $driver = Driver::where('code', 'ALPHA-02')->firstOrFail();
    expect($driver->provider_id)->toBe($this->alpha->id)
        ->and($driver->organization_id)->toBe($this->alpha->organization_id)
        ->and($driver->user->status)->toBe(UserStatus::INVITED)
        ->and(ProviderUser::where('user_id', $driver->user_id)->exists())->toBeFalse();
});

it('refuse les champs que le serveur impose', function (string $field): void {
    $this->withHeaders($this->admin)->postJson('/api/v1/provider/drivers', [...NEW_DRIVER, $field => $this->beta->id])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with(['providerId', 'organizationId', 'status', 'addressId', 'contactId', 'userId']);

it('refuse un e-mail ou un code déjà pris sans dire par qui', function (): void {
    User::create(['first_name' => 'X', 'last_name' => 'Y', 'email' => 'pris@ailleurs.test', 'password' => 'x-password-1', 'status' => 'active']);

    $email = $this->withHeaders($this->admin)->postJson('/api/v1/provider/drivers', [...NEW_DRIVER, 'email' => 'pris@ailleurs.test'])->assertUnprocessable();
    $code = $this->withHeaders($this->admin)->postJson('/api/v1/provider/drivers', [...NEW_DRIVER, 'code' => 'BETA-01'])->assertUnprocessable();

    expect($email->json('errors.email.0'))->toBe('Cette adresse e-mail ne peut pas être utilisée.')
        ->and($code->json('errors.code.0'))->toBe('Ce code ne peut pas être utilisé.');
});

it('modifie le nom et le téléphone de son chauffeur', function (): void {
    $this->withHeaders($this->admin)->patchJson("/api/v1/provider/drivers/{$this->mine->id}", ['firstName' => 'Alain', 'lastName' => 'Alpha'])
        ->assertOk()->assertJsonPath('data.name', 'Alain Alpha');
});

it('refuse de désactiver un chauffeur engagé dans une tournée à venir ou en cours, pas après', function (string $status, int $expected): void {
    Tour::factory()->forOrganization($this->alpha->organization)->create(['provider_id' => $this->alpha->id, 'driver_id' => $this->mine->id, 'status' => TourStatus::from($status)]);

    $this->withHeaders($this->admin)->postJson("/api/v1/provider/drivers/{$this->mine->id}/deactivate")->assertStatus($expected);
})->with([['planned', 422], ['confirmed', 422], ['in_progress', 422], ['completed', 200], ['cancelled', 200]]);

it('désactive puis réactive un chauffeur libre', function (): void {
    $this->withHeaders($this->admin)->postJson("/api/v1/provider/drivers/{$this->mine->id}/deactivate")->assertOk()->assertJsonPath('data.status', 'inactive');
    $this->withHeaders($this->admin)->postJson("/api/v1/provider/drivers/{$this->mine->id}/activate")->assertOk()->assertJsonPath('data.status', 'active');
});

it('réserve la lecture à `provider.drivers.view` et l’écriture à `create` / `update`', function (): void {
    $viewer = ProviderPortal::headers('viewer@transports-alpha.test');

    $this->withHeaders($viewer)->getJson('/api/v1/provider/drivers')->assertOk();
    $this->withHeaders($viewer)->postJson('/api/v1/provider/drivers', NEW_DRIVER)->assertForbidden();
    $this->withHeaders($viewer)->patchJson("/api/v1/provider/drivers/{$this->mine->id}", ['firstName' => 'X'])->assertForbidden();
});

it('liste les chauffeurs en un nombre de requêtes qui ne dépend pas de leur nombre', function (): void {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->withHeaders($this->admin)->getJson('/api/v1/provider/drivers?perPage=100')->assertOk();

        return count(DB::getQueryLog());
    };
    $create = fn (int $i) => $this->withHeaders($this->admin)
        ->postJson('/api/v1/provider/drivers', [...NEW_DRIVER, 'code' => "ALPHA-1{$i}", 'email' => "n{$i}@alpha.test"])->assertCreated();
    // Un chauffeur invité d'abord : le contrôle groupé du compte s'exécute.
    $create(1);
    $before = $count();
    foreach (range(2, 5) as $i) {
        $create($i);
    }

    expect($count())->toBe($before);
});
