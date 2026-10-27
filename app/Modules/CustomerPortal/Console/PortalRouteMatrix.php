<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Console;

use App\Modules\CustomerPortal\Support\PortalRouteSecurity;
use Illuminate\Console\Command;

/**
 * Inventaire de sécurité des routes du périmètre externe (Phase 7, §49).
 *
 * Généré depuis le routeur réel, jamais écrit à la main : il ne peut pas
 * dériver du code. `--output` l'écrit dans un fichier (par défaut
 * docs/customer-portal/security-route-matrix.md) ; `--check` échoue si une
 * route sensible n'a pas d'autorisation explicite.
 */
class PortalRouteMatrix extends Command
{
    protected $signature = 'customer-portal:route-matrix
        {--output= : Fichier Markdown à écrire}
        {--check : Échoue si une route sensible n\'a ni permission ni contrôle documenté}';

    protected $description = 'Inventaire de sécurité des routes du portail client, du lien public et de l’API par clé.';

    public function handle(PortalRouteSecurity $security): int
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

            $this->info(count($rows).' routes externes, toutes autorisées explicitement.');

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
            '# Matrice de sécurité des routes — portail client',
            '',
            '> Généré par `php artisan customer-portal:route-matrix --output=docs/customer-portal/security-route-matrix.md`.',
            '> Ne pas modifier à la main : régénérer après tout changement de route.',
            '> `php artisan customer-portal:route-matrix --check` (et `PortalRouteSecurityTest`) échouent si une route sensible perd son autorisation.',
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
