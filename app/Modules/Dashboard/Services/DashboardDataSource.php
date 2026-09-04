<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Services;

/**
 * Ce qui sait calculer les widgets d'une catégorie.
 *
 * Une source reçoit **les clés retenues**, jamais le catalogue entier : quand
 * un rôle n'a activé qu'un compteur sur neuf, huit requêtes ne sont pas jouées.
 * C'est ce qui rend l'appel unique du §27 tenable — un seul aller-retour HTTP,
 * et seulement le travail demandé.
 *
 * Ce qu'une source ne fait **jamais** : vérifier une permission. Le filtrage a
 * eu lieu avant elle, dans `DashboardComposer`, et une clé qui lui parvient est
 * une clé autorisée. Refaire le contrôle ici donnerait deux endroits où la
 * règle vit, donc deux endroits où elle peut diverger.
 */
interface DashboardDataSource
{
    /**
     * @param  array<int, string>  $keys  Clés de cette catégorie, déjà autorisées.
     * @return array<string, mixed> Clé du widget → sa donnée.
     */
    public function resolve(array $keys, DashboardContext $context): array;
}
