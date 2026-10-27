<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Uploads;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Le fichier d'un import prévisualisé, gardé le temps de la confirmation.
 *
 * Pas de table : un fichier sur le disque **privé** (`local`, sans URL) et un
 * jeton en cache à durée courte (§28). Le jeton est aléatoire et **lié** au
 * client, à la configuration et à l'utilisateur : présenté par un autre
 * client ou pour une autre configuration, il n'existe pas (404).
 *
 * Le nom de stockage est le jeton, jamais le nom fourni : aucun chemin ne
 * vient de l'utilisateur. Le nom d'origine n'est gardé, nettoyé, que pour
 * l'affichage.
 */
final readonly class ImportUploadStore
{
    private const string PREFIX = 'customer-import-upload:';

    private const string RESULT_PREFIX = 'customer-import-result:';

    /**
     * @return array{token: string, expiresAt: string, fileName: string}
     */
    public function put(UploadedFile $file, string $customerId, string $configurationId, string $userId): array
    {
        $token = Str::random(48);
        $extension = strtolower($file->getClientOriginalExtension()) ?: 'bin';
        $path = $this->directory().'/'.$customerId.'/'.$token.'.'.preg_replace('/[^a-z0-9]/', '', $extension);
        $expiresAt = now()->addMinutes((int) config('customer_integrations.uploads.ttl_minutes', 30));
        $fileName = $this->cleanName($file->getClientOriginalName());

        $this->disk()->put($path, (string) $file->get());

        $this->cache()->put(self::PREFIX.$token, [
            'customerId' => $customerId,
            'configurationId' => $configurationId,
            'userId' => $userId,
            'path' => $path,
            'fileName' => $fileName,
        ], $expiresAt);

        return ['token' => $token, 'expiresAt' => $expiresAt->toIso8601String(), 'fileName' => $fileName];
    }

    /**
     * Le dépôt, s'il appartient bien à ce client et à cette configuration.
     *
     * @return array{customerId: string, configurationId: string, userId: string, path: string, fileName: string}|null
     */
    public function find(string $token, string $customerId, string $configurationId): ?array
    {
        $entry = $this->cache()->get(self::PREFIX.$token);

        if (! is_array($entry) || $entry['customerId'] !== $customerId || $entry['configurationId'] !== $configurationId) {
            return null;
        }

        return $this->disk()->exists($entry['path']) ? $entry : null;
    }

    public function contents(array $entry): string
    {
        return (string) $this->disk()->get($entry['path']);
    }

    /** Le jeton ne sert qu'une fois : consommé, le fichier disparaît. */
    public function forget(string $token, array $entry): void
    {
        $this->cache()->forget(self::PREFIX.$token);
        $this->disk()->delete($entry['path']);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function putResult(string $token, string $customerId, array $result): void
    {
        $this->cache()->put(self::RESULT_PREFIX.$token, ['customerId' => $customerId, ...$result], now()->addMinutes(
            (int) config('customer_integrations.uploads.result_ttl_minutes', 1440),
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function result(string $token, string $customerId): ?array
    {
        $result = $this->cache()->get(self::RESULT_PREFIX.$token);

        if (! is_array($result) || ($result['customerId'] ?? null) !== $customerId) {
            return null;
        }

        unset($result['customerId']);

        return $result;
    }

    /** Supprime les fichiers dont le jeton a expiré. Rend leur nombre. */
    public function prune(): int
    {
        $limit = now()->subMinutes((int) config('customer_integrations.uploads.ttl_minutes', 30) + 5)->getTimestamp();
        $pruned = 0;

        foreach ($this->disk()->allFiles($this->directory()) as $path) {
            if ($this->disk()->lastModified($path) < $limit) {
                $this->disk()->delete($path);
                $pruned++;
            }
        }

        return $pruned;
    }

    private function cleanName(string $name): string
    {
        $clean = preg_replace('/[^\pL\pN._ -]+/u', '_', basename(str_replace('\\', '/', $name))) ?? 'fichier';

        return mb_substr(trim($clean) !== '' ? trim($clean) : 'fichier', 0, 120);
    }

    /** Un store durable, partagé entre requêtes : voir `customer_integrations.cache_store`. */
    private function cache(): Repository
    {
        return Cache::store((string) config('customer_integrations.cache_store', 'database'));
    }

    private function directory(): string
    {
        return (string) config('customer_integrations.uploads.directory', 'customer-imports');
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('customer_integrations.uploads.disk', 'local'));
    }
}
