<?php

use App\Shared\Security\SensitiveData;
use App\Shared\Security\SpreadsheetSafe;

/**
 * Les deux règles centrales de la Phase 7 : ce qu'est un secret (audit et
 * journaux) et ce qu'est une cellule dangereuse (export CSV).
 */
it('reconnaît un secret quel que soit l’habillage de son nom', function (string $key): void {
    expect(SensitiveData::isSensitiveKey($key))->toBeTrue();
})->with([
    'password', 'password_confirmation', 'passwordHash', 'apiKey', 'api_key', 'API-KEY', 'apiKeyHash', 'X-Api-Key',
    'encryptedPassword', 'encrypted_credentials', 'credentials', 'Authorization', 'cookie', 'set-cookie',
    'clientSecret', 'secret', 'token', 'accessToken', 'remember_token', 'plainTextToken', 'privateKey',
]);

it('ne masque pas ce qui n’est pas secret', function (int|string $key): void {
    expect(SensitiveData::isSensitiveKey($key))->toBeFalse();
})->with(['name', 'tokenUrl', 'tokenLifetimeSeconds', 'status', 'orderNumber', 'email', 'configurationId', 0]);

it('masque à toute profondeur, sans toucher au reste', function (): void {
    expect(SensitiveData::redact(['a' => 1, 'nested' => ['deeper' => ['secret' => 'x', 'keep' => 'y']]]))
        ->toBe(['a' => 1, 'nested' => ['deeper' => ['secret' => '[REDACTED]', 'keep' => 'y']]]);
});

it('neutralise une formule de tableur, jamais un nombre', function (mixed $value, string $expected): void {
    expect(SpreadsheetSafe::cell($value))->toBe($expected);
})->with([
    'formule' => ['=HYPERLINK("http://x")', '\'=HYPERLINK("http://x")'],
    'commande DDE' => ['=cmd|\' /C calc\'!A0', '\'=cmd|\' /C calc\'!A0'],
    'arobase' => ['@SUM(A1)', '\'@SUM(A1)'],
    'plus' => ['+41 22 555 01 01', '\'+41 22 555 01 01'],
    'tabulation' => ["\t=1", "'\t=1"],
    'négatif' => ['-12.50', '-12.50'],
    'entier' => [42, '42'],
    'texte' => ['Table', 'Table'],
    'vide' => ['', ''],
]);
