# Sécurité du tableau de bord

Une seule règle, et tout le reste en découle :

```
widget visible = activé par un rôle  ET  permission effective présente
```

La configuration **propose**. La permission **dispose**. Les deux réglages sont
indépendants, et il n'y a jamais de chemin du premier vers le second.

---

## 1. Ce qu'un tableau de bord n'est pas

C'est un écran d'agrégation : il montre en une page des chiffres qui vivent
ailleurs, derrière des écrans qui ont chacun leur permission. Un tableau de bord
mal conçu est donc une **fuite par le haut** — il rend accessible, sans clic et
sans contrôle, ce que dix écrans protègent chacun de leur côté.

D'où la contrainte fondatrice : **activer un widget n'accorde rien**. Un
administrateur qui compose le tableau de bord d'un rôle range des cartes ; il ne
distribue pas de droits. S'il pouvait, la configuration du tableau de bord
serait une voie d'élévation ouverte à qui détient `dashboard.configure`.

---

## 2. Les trois filtres, dans l'ordre

`DashboardComposer::compose()` les applique l'un après l'autre :

| | Filtre | Ce qu'il écarte |
| --- | --- | --- |
| 1 | **les rôles** | ce qu'aucun rôle de l'organisation active ne montre |
| 2 | **les permissions effectives** | ce dont l'appelant n'a pas le droit de lire la source |
| 3 | **l'ordre** | rien — il départage |

Le deuxième ne se négocie pas, et il ne se contente pas de masquer : **le widget
refusé n'est pas calculé**. Sa clé n'atteint jamais la source de données, et sa
valeur ne figure pas dans la réponse. Un widget masqué côté interface dont le
chiffre voyagerait quand même dans le JSON serait une fuite complète — il
suffirait d'ouvrir l'onglet réseau.

`tests/Feature/Api/V1/Dashboard/DashboardTest.php` le vérifie sur le **corps
brut** de la réponse, et non sur la liste décodée : c'est la seule façon
d'attraper une valeur qui aurait survécu dans un champ auxiliaire.

---

## 3. Union pour les rôles, intersection pour les permissions

Deux règles opposées, appliquées à deux choses différentes, et chacune a sa
raison.

| | Règle | Pourquoi |
| --- | --- | --- |
| **Widgets des rôles** | **union** — le widget s'affiche dès qu'**un seul** rôle le montre | C'est déjà celle des permissions : `hasPermission()` s'arrête au premier rôle qui accorde. L'intersection aurait eu le défaut inverse, plus grave : **ajouter** un rôle aurait retiré des cartes, ce que personne n'attend d'un ajout. |
| **Permissions** | **intersection** — il faut la permission requise | C'est le filtre de sécurité. Le relâcher rendrait le premier dangereux. |

Un widget que deux rôles montrent s'affiche **une fois**, au plus petit rang
configuré. Le tri final est `(position, clé)` : sans le second critère, deux
widgets de même rang sortiraient dans l'ordre où SQL a rendu les rôles —
c'est-à-dire dans un ordre différent d'un appel à l'autre.

`UserDashboardWidgets` porte cette règle.

---

## 4. Les permissions, et ce qui les remplace

| Permission | Ce qu'elle ouvre |
| --- | --- |
| `dashboard.view` | son propre tableau de bord. Elle ouvre l'écran, elle ne décide de rien de ce qu'on y trouve |
| `dashboard.configure` | le catalogue des widgets, et le réglage d'un rôle |

`dashboard.configure` est **nouvelle**. Le menu, qui est le réglage jumeau,
passe par `roles.update` — et la différence est délibérée : composer un tableau
de bord est un travail métier qu'on veut pouvoir confier sans donner du même
geste le droit de modifier les permissions d'un rôle. `roles.update` est un
pouvoir bien plus large que ce que cet écran demande.

Elle n'est attribuée automatiquement à aucun rôle sinon `admin`, qui reçoit
l'ensemble des permissions d'organisation par `RoleSeeder`.

`EffectivePermissions` résout les permissions d'un compte en **une** requête,
pour la cinquantaine de widgets du catalogue. Il reprend les deux règles
existantes sans en ajouter une troisième : le propriétaire de l'organisation
détient tout, les autres tiennent leurs droits de l'union de leurs rôles.

**Rien ne dépend du nom d'un rôle.** Un rôle appelé « Admin » qui ne porterait
aucune permission n'ouvre aucun widget. C'est ce qui empêche qu'un libellé
devienne un droit.

---

## 5. Qui peut régler quel rôle

`RolePolicy::configureDashboard` — en miroir de `updateMenu`, avec une
permission différente :

```
platform admin                      → autorisé
rôle d'une autre organisation       → 404, jamais 403
rôle de portée plateforme           → refusé
dashboard.configure                 → requis
```

Le **404 sur un rôle étranger** n'est pas une négligence : un 403 confirmerait
que cet identifiant existe ailleurs, et c'est la différence entre les deux
réponses qui constitue la fuite, pas leur contenu. `BaseOrganizationPolicy`
porte cette convention pour toutes les ressources.

Le **rôle système fait exception**, comme pour le menu : `admin` porte toutes les
permissions, et `update` le protège pour cette raison — mais composer son
tableau de bord n'accorde rien, et l'interdire aurait privé l'administrateur du
seul tableau de bord qu'il voit lui-même.

---

## 6. Ce qu'on refuse d'écrire

`UpdateRoleDashboardRequest` n'accepte que **deux champs** : une clé du
catalogue, un rang entier. Ni SQL, ni nom de classe PHP, ni nom de composant
React, ni URL, ni titre libre.

Ce n'est pas de la prudence excessive. La colonne est un JSON, et tout ce qu'on
y accepterait ressortirait un jour dans une réponse d'API ou dans un rendu. Une
clé du catalogue ne peut désigner qu'un résolveur écrit et déployé ; une chaîne
libre pourrait désigner n'importe quoi.

Le contrôleur **renormalise** par-dessus la validation : un champ en trop est
retiré avant l'écriture, même s'il a franchi les règles. Deux refus s'y
ajoutent, tous deux au nom de la même règle :

- **une clé en double** — deux rangs pour un même widget, dont un seul
  survivrait, choisi par l'ordre du tableau ;
- **un widget dont le rôle n'a pas la permission** — l'accepter aurait laissé
  croire qu'il s'affichera. Il ne s'affichera pas ; le refuser le dit tout de
  suite, à l'endroit où l'on peut y remédier.

À la **lecture**, `RoleDashboardWidgets` ignore en plus toute clé que le
catalogue ne connaît plus. La validation ne peut rien contre un widget retiré du
code après coup : l'ignorer à la lecture le fait disparaître de lui-même, sans
migration.

---

## 7. Retirer une permission suffit

Un rôle a `customers_count` configuré et `customers.view` accordée. L'admin
retire la permission :

```
customers_count disparaît au prochain chargement
la configuration, elle, n'a pas bougé
```

Personne n'a eu à toucher au tableau de bord. Et si la permission revient, le
widget revient avec elle — la configuration l'attendait.

C'est la propriété la plus utile de ce modèle, et la raison pour laquelle
l'intersection a lieu **à chaque requête** plutôt qu'une fois à l'enregistrement.

---

## 8. Ce qui ne traverse jamais la réponse

Les widgets d'intégration comptent, ils ne montrent pas. Une configuration
d'export porte un hôte, un identifiant, un mot de passe chiffré et un répertoire
de dépôt ; une configuration d'API porte des identifiants chiffrés. La liste des
envois récents ne rend que le nom du fichier et son état — jamais
`storage_path`, jamais `error_message`, qui cite parfois l'hôte et le chemin
distants.

Même précaution pour les communications : `body` est le message rendu,
destinataire compris, et six courriels complets n'ont rien à faire dans une
réponse qui tient en quelques lignes.

---

## 9. Portée : l'organisation, et rien d'autre

`GET /dashboard` vit sous le middleware `organization` — contrairement à
`GET /menu`, qu'un compte plateforme lit aussi. Tous les chiffres agrégés
viennent d'une organisation ; il n'y a pas de tableau de bord hors d'elle.

**Aucun widget ne déclare de portée « agence ».** L'application n'a pas de
contexte d'agence actif : le client n'envoie que `X-Organization-Id`. Déclarer
un champ `scope` qui vaudrait `ORGANIZATION` sur les soixante widgets aurait
ajouté une colonne morte et laissé croire qu'un arbitrage existe. Le jour où un
contexte d'agence apparaîtra, le champ s'ajoutera avec lui.

---

## 10. Journal d'audit

```
role_dashboard_updated
role_dashboard_reset
```

Portés par le journal de l'**organisation du rôle**, avec le rôle pour entité :
un tableau de bord qui change pour tout un métier se constate le lendemain, et
il faut pouvoir dire qui l'a décidé.

Seules les **clés activées** sont journalisées, pas le catalogue entier : une
entrée de journal de soixante lignes dont deux ont changé se relit mal, et c'est
précisément quand on la relit qu'elle sert.

Pas de table `DashboardHistory` : le journal d'audit existe et fait ce travail.

---

## 11. Ce que les tests tiennent

| Fichier | Ce qu'il empêche |
| --- | --- |
| `Api/V1/Dashboard/DashboardTest.php` | qu'une configuration suffise à voir un widget ; qu'un chiffre refusé voyage ; qu'une organisation déborde sur l'autre ; que l'ordre dépende de SQL |
| `Api/V1/Identity/RoleDashboardTest.php` | qu'on écrive une clé inconnue, un doublon, un rang négatif, un widget sans permission ; qu'on règle le rôle d'un tiers ; qu'un champ en trop soit conservé |
| `Hardening/DashboardCatalogueConsistencyTest.php` | qu'un widget nomme une permission ou une route qui n'existe pas ; qu'il lui manque une traduction ; qu'il ne soit calculé par personne |
