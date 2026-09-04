# Menu par rôle

Le menu se réglait à deux niveaux — l'organisation pour l'ordre et les noms, le
rôle pour la seule visibilité. Deux écrans, donc, et il fallait savoir lequel
ouvrir pour obtenir quoi. **Il tient désormais en un seul endroit : la fiche du
rôle.**

Les routes, la carte des fichiers, ce qui empêche le catalogue de dériver et la
procédure à chaque phase sont dans `role-menu-files.md`.

---

## 1. Le besoin

Chaque métier veut son menu. Un chauffeur n'a que faire de la facturation, un
comptable des tournées — et ce choix ne peut pas vivre dans un fichier livré à
tout le monde.

---

## 2. La ligne de partage

**Le catalogue reste en code. Ce qu'un rôle en fait vit en base.**

La ligne ne passe pas entre « peu » et « beaucoup » de configuration, mais entre
ce qui **ne peut pas être faux** et ce qui, au pire, est mal nommé :

| Donnée | Où | Pourquoi |
| --- | --- | --- |
| `route` | code | Doit exister dans le routeur React. Une route en base qui n'y correspond à rien donne « Page introuvable ». |
| `permission` | code | Doit exister dans le référentiel — c'est le bug `audit_logs.view`. |
| `labelKey` | code | La clé i18n reste le **défaut**, vers lequel un retour est toujours possible. |
| `isVisible` | **base** | Le choix du rôle. |
| `position` | **base** | Le choix du rôle. |
| `label` | **base** | Le nom affiché, s'il est renseigné. `null` suit `labelKey`. |
| `icon` | **base** | Le **nom** d'une icône, validé contre `MenuIcons`. `null` suit le catalogue. |
| `parent` | **base** | Le groupe d'accueil, ou le premier niveau. Deux colonnes : voir §4. |

Renommer « Agences » en « Sites », ou la sortir des « Ressources », ne casse
rien : au pire on se relit et on se corrige, dans le même écran. En réécrire
l'adresse, si.

**Les colonnes personnalisables sont nullables, et le `null` a un sens :**
« garder ce que dit le catalogue ». Un rôle qui n'a rien renommé suit donc les
traductions livrées, y compris celles à venir ; vider le champ est le geste qui
revient au défaut, sans bouton dédié.

L'icône mérite une précaution supplémentaire. C'est un composant React, et la
base n'en stocke que le nom : un nom que `menuIcons.ts` ignore **échoue en
silence** — l'entrée retombe sur l'icône neutre, et l'administrateur qui vient
de la choisir croit avoir réussi. `App\Shared\Menu\MenuIcons` est donc le miroir
exact de la table frontend, la validation s'y confronte à l'écriture, et le
résolveur **s'y confronte encore à la lecture**.

---

## 3. Ce que coûte le réglage par rôle

Un utilisateur peut cumuler plusieurs rôles. Deux réponses en découlent, et
elles ne suivent pas la même règle :

| | Règle | Pourquoi |
| --- | --- | --- |
| **Visibilité** | union — l'entrée s'affiche dès qu'**un seul** rôle la montre | C'est déjà celle des permissions : `hasPermission()` s'arrête au premier rôle qui accorde. Un menu plus restrictif que les droits proposerait moins que ce que l'utilisateur peut ouvrir, sans qu'il ait moyen de s'en apercevoir. Et l'intersection avait le défaut inverse, plus grave : **ajouter** un rôle aurait retiré des écrans. |
| **Présentation** (ordre, nom, icône, groupes) | le **rôle principal** — celui dont le `code` vient en premier | Deux rôles qui nomment la même entrée autrement ne peuvent pas se fondre : il faut choisir. Le tri par code rend le départage stable et lisible, et l'administrateur peut en changer en renommant. |

Ce départage est arbitraire, et c'est le prix de la liberté demandée. Le modèle
précédent l'évitait en **refusant** de régler la présentation sur un rôle ; il
ne se paie que sur les comptes multi-rôles. `UserRoleMenus` porte la règle.

---

## 4. Le rattachement, et pourquoi il demande deux colonnes

Sortir une entrée d'un sous-menu pour en faire un menu, ou l'inverse, c'est le
même geste : **changer son parent**. Il n'y a donc qu'un réglage, et un seul
champ dans l'écran.

Mais `null` y est une **valeur choisie** — « au premier niveau » — alors qu'il
signifie « suivre le catalogue » partout ailleurs. Les confondre rendrait une
promotion indistinguable d'une absence de choix, et l'entrée retournerait dans
son groupe au premier rechargement. D'où `parent_overridden`, qui porte la
décision, et `parent_code`, qui porte sa cible :

| `parent_overridden` | `parent_code` | Résultat |
| --- | --- | --- |
| `false` | — | Le rattachement du catalogue |
| `true` | `null` | Entrée de premier niveau |
| `true` | `resources` | Entrée du groupe « Ressources » |

Choisir de nouveau le groupe du catalogue **efface le choix** plutôt que de
l'enregistrer : l'entrée suivra le catalogue s'il la déplace un jour.

**Seules les entrées qui portent une route se déplacent.** La barre latérale
rend exactement deux niveaux : un groupe rangé dans un groupe placerait ses
entrées au troisième, où rien ne les affiche — elles disparaîtraient sans
qu'aucune erreur ne soit levée.

Un groupe cible disparu rend l'entrée à son groupe d'origine, et non à la
racine : une promotion que personne n'a demandée surprendrait davantage qu'un
retour au défaut.

---

## 5. Les groupes créés

Un rôle peut créer ses propres groupes. **C'est la conséquence de la règle du
§2, pas une entorse** : un groupe n'ouvre rien. Ni route, ni permission — c'est
un titre repliable au-dessus d'entrées qui gardent, elles, leur destination du
code. Il n'y a donc pas de route à saisir de travers, pas de permission à
inventer. Créer un groupe est du rangement, pas du routage.

Trois précautions les encadrent :

| Précaution | Ce qu'elle évite |
| --- | --- |
| `code` opaque et immuable, tiré par le serveur — un ULID préfixé `grp-` | Un slug tiré du nom ferait perdre au groupe tous ses enfants au premier renommage ; un code saisi pourrait heurter un code du catalogue |
| Le préfixe sépare les deux espaces de noms | Ils se croisent dans `parent_code` et `role_menu_items.code` ; une collision ferait régler une entrée pour une autre |
| Un groupe créé n'est jamais `canReparent` | Pas de troisième niveau où mettre ses entrées |

Le nom, l'icône, le rang et la visibilité se règlent par `PATCH
/roles/{role}/menu`, avec tout le reste — on ne compose pas un menu en deux
enregistrements. Ils sont écrits dans **sa propre ligne** : un groupe créé n'a
pas de valeur de catalogue à surcharger, il *est* sa propre valeur.

**Il naît vide, donc invisible** : la règle qui retire un groupe sans enfant
s'applique à lui. Il reste présent dans l'écran de réglage — c'est là qu'on le
remplit — et l'écran le signale, sans quoi on croirait la création ratée.

**Le supprimer ne supprime pas ses entrées** : elles retrouvent le rattachement
du catalogue.

---

## 6. Les trois filtres du menu servi

`MenuResolver::resolve()` applique, **dans cet ordre** :

1. **la portée du compte** — un compte plateforme reçoit le menu plateforme,
   pas le menu d'organisme expurgé. N'ayant pas de rôle d'organisation, il
   reçoit le catalogue tel qu'il est livré ;
2. **les rôles de l'utilisateur** — §3 ;
3. **les permissions de l'utilisateur** — une entrée qu'il n'a pas le droit
   d'ouvrir ne lui est pas proposée.

Le dernier ne se négocie pas : c'est lui qui fait du menu la projection des
droits, et non l'inverse. Le menu range, les permissions protègent — voir §7.

Un groupe dont plus aucun enfant ne subsiste est retiré : il afficherait un
titre vide.

`RoleMenuCatalogue` montre autre chose — tout ce qui est **réglable** — et
diverge sur trois points, chacun pour une raison : les permissions n'y filtrent
pas, les entrées masquées y restent proposées (décochées), et les groupes vides
y figurent.

---

## 7. Le garde-fou : une entrée, et une seule

`alwaysVisible` marquait l'administration **et** « Mon organisation ». Il les
verrouillait pour tout le monde, ce qui avait un sens quand le réglage valait
pour l'organisation entière : les masquer les aurait retirées au propriétaire
lui-même, sans écran pour se corriger.

Depuis que le menu appartient au rôle, un rôle « Bureau » qui n'a que faire de
l'administration doit pouvoir la ranger. **Une seule entrée reste donc
verrouillée : « Mon organisation ».** Elle garde à chacun un pied dans
l'administration, quels que soient les rôles qu'il porte, et le groupe qui la
contient se masque librement — un enfant dont le groupe a disparu remonte au
premier niveau plutôt que de se perdre.

La demande de masquage est **ignorée**, pas refusée : la requête reste valide,
c'est la contrainte qui l'emporte. Et **masquer n'est pas interdire** — l'écran
reste atteignable par son adresse tant que la permission est là. Le menu range,
les permissions protègent.

Qui a le droit de régler ce menu — et pourquoi le rôle système y échappe à sa
propre règle — est dans `role-menu-files.md`, §1.

---

## 8. Réordonner

Deux gestes qu'il ne faut pas confondre. Les **flèches** déplacent une entrée
parmi ses frères, et un groupe déplacé emmène ses entrées. Le **rattachement**
(§4) la change de groupe, sans la faire bouger dans la liste. Les mêler ferait
d'un simple « monter » une promotion involontaire dès qu'on atteint le haut d'un
groupe.

Les rangs sont **renumérotés en bloc sur l'ordre affiché**, sans trou : ils
n'ont de sens que les uns par rapport aux autres.

Rien n'est envoyé au fil des clics — on enregistre **une fois**. Deux
exceptions, et elles se justifient : créer et supprimer un groupe prennent effet
tout de suite, car on ne range pas une entrée dans un groupe qui n'existe pas
encore. Et **l'icône n'est transmise que si elle a changé** : le serveur renvoie
l'icône effective sans dire d'où elle vient, et la réécrire figerait en base des
icônes que personne n'a choisies.
