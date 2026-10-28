<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Console;

use App\Modules\ProviderPortal\Support\ProviderRouteSecurity;
use Illuminate\Console\Command;

/**
 * Inventaire de sécurité des routes du portail fournisseur (Phase 7).
 *
 * Généré depuis le routeur réel, jamais écrit à la main. `--output` l'écrit
 * dans un fichier (docs/provider-portal/security-route-matrix.md) ; `--check`
 * échoue si une route perd son authentification, son contexte, sa permission
 * ou sa limite de débit.
 */
class ProviderRouteMatrix extends Command
{
    protected $signature = 'provider-portal:route-matrix
        {--output= : Fichier Markdown à écrire}
        {--check : Échoue si une route n\'a pas toutes ses protections}';

    protected $description = 'Inventaire de sécurité des routes du portail fournisseur.';

    public function handle(ProviderRouteSecurity $security): int
    {
        $rows = $security->rows();

        if ($this->option('check')) {
            $gaps = array_filter($rows, static fn (array $row): bool => $row['gap'] !== null);

            foreach ($gaps as $gap) {
                $this->error("{$gap['method']} {$gap['uri']} : {$gap['gap']}");
            }

            if ($gaps !== []) {
                return self::FAILURE;
            }

            $this->info(count($rows).' routes du portail fournisseur, toutes protégées explicitement.');

            return self::SUCCESS;
        }

        $markdown = $this->markdown($rows);
        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            file_put_contents(base_path($output), $markdown);
            $this->info(count($rows)." routes écrites dans {$output}.");
        } else {
            $this->line($markdown);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function markdown(array $rows): string
    {
        $lines = [
            '# Matrice de sécurité des routes — portail fournisseur',
            '',
            '> Généré par `php artisan provider-portal:route-matrix --output=docs/provider-portal/security-route-matrix.md`.',
            '> Ne pas modifier à la main : régénérer après tout changement de route.',
            '> `php artisan provider-portal:route-matrix --check` (et `ProviderRouteGuardsTest`) échouent si une route perd une protection.',
            '',
            '| Méthode | URI | Authentification | Contexte | Permission | Isolation | Débit |',
            '|---|---|---|---|---|---|---|',
        ];

        foreach ($rows as $row) {
            $lines[] = sprintf(
                '| %s | `%s` | %s | %s | %s | %s | %s |',
                $row['method'],
                $row['uri'],
                $row['auth'],
                $row['context'],
                $row['permission'] === [] ? '—' : implode('<br>', array_map(static fn (string $code): string => "`{$code}`", $row['permission'])),
                $row['isolation'],
                $row['throttle'] === [] ? '—' : implode('<br>', $row['throttle']),
            );
        }

        return implode("\n", $lines)."\n";
    }
}
