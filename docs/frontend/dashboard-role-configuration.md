# Tableau de bord par rôle — côté interface

Le tableau de bord n'affichait que quatre cartes, écrites en dur : clients,
agences, utilisateurs, rôles. Elles convenaient à l'administration et à personne
d'autre — un planificateur y trouvait quatre chiffres qui ne le concernaient
pas, et rien de ses tournées.

Il se compose désormais **par rôle**, sur la fiche du rôle.

Le raisonnement de sécurité est dans `docs/backend/dashboard-security.md`, le
catalogue dans `docs/backend/dashboard-widget-registry.md`. Celui-ci dit ce que
l'interface fait, et pourquoi.

---

## 1. Le parcours

```
Administration → Rôles → un rôle → onglet Tableau de bord
→ cocher, ordonner, enregistrer

Un utilisateur de ce rôle → Tableau de bord
→ ce qui a été coché, moins ce que ses permissions ne couvrent pas
```

---

## 2. Les fichiers

| Fichier | Rôle |
| --- | --- |
| `modules/dashboard/types/dashboard.ts` | les formes servies, et les cinq types |
| `modules/dashboard/api/dashboard.api.ts` | `GET /dashboard` |
| `modules/dashboard/api/role-dashboard.api.ts` | lecture, enregistrement, réinitialisation |
| `modules/dashboard/schemas/roleDashboardSchema.ts` | ce qu'on s'autorise à envoyer |
| `modules/dashboard/hooks/useDashboard.ts` | le tableau de bord servi |
| `modules/dashboard/hooks/useRoleDashboard.ts` | le réglage d'un rôle |
| `modules/dashboard/hooks/useRoleDashboardDraft.ts` | brouillon : un seul enregistrement |
| `modules/dashboard/pages/DashboardPage.tsx` | la page, qui ne code plus aucune carte |
| `modules/dashboard/components/DashboardGrid.tsx` | 12 / 6 / 1 colonnes |
| `modules/dashboard/components/DashboardWidgetRenderer.tsx` | type → composant, jeu fermé |
| `modules/dashboard/components/DashboardSkeleton.tsx` | l'attente |
| `modules/dashboard/components/WidgetCard.tsx` | l'enveloppe commune |
| `modules/dashboard/components/widgets/*.tsx` | les cinq rendus |
| `modules/dashboard/components/RoleDashboardPanel.tsx` | l'écran de réglage |
| `modules/dashboard/components/RoleDashboardOrder.tsx` | ordre : glisser, ou flèches |
| `modules/dashboard/components/RoleDashboardCatalogue.tsx` | le catalogue, groupé par métier |
| `modules/dashboard/components/RoleDashboardRow.tsx` | une ligne, et sa permission |
| `modules/dashboard/components/RoleDashboardPreview.tsx` | l'aperçu, sans données |
| `modules/roles/pages/RoleDetailPage.tsx` | porte les trois onglets |

Les clés de cache :

```
dashboardKeys.current(organizationId)
roleDashboardKeys.detail(roleId)
```

L'organisation fait partie de la première, et il le faut : le même compte peut
travailler dans deux organisations avec des rôles différents dans chacune. Une
clé commune aurait servi les chiffres de la première dans la seconde le temps
d'un rafraîchissement — des totaux justes attribués au mauvais organisme, ce que
personne ne repère.

---

## 3. Ce que le frontend ne décide pas

**Rien de ce qui s'affiche.** La page ne filtre pas, ne trie pas, n'ajoute pas.
Ce qui arrive de `GET /dashboard` a déjà passé les trois filtres du serveur : un
widget interdit n'est pas dans la réponse, et son chiffre n'a jamais été
calculé.

Un `PermissionGuard` posé sur chaque carte serait donc au mieux redondant, au
pire trompeur — il laisserait croire que la protection est ici.

Corollaire : **aucun type ne nomme un rôle**. Écrire
`type DashboardRole = 'ADMIN' | 'PLANNER'` remettrait la décision côté
navigateur, où elle se contourne, et figerait dans le code des rôles que chaque
organisation crée elle-même.

---

## 4. Le rendu : neuf types, neuf composants

`DashboardWidgetRenderer` fait correspondre `widget.type` à l'un de neuf
composants écrits dans le dossier. Un `components[widget.component]` aurait
suffi à ouvrir la porte à un nom venu de la base ; le `switch` la ferme.

Un type inconnu ne rend **rien**. Le cas ne devrait pas se produire — le serveur
ne sert que ce que son énumération contient — mais un frontend en retard d'une
version le verrait, et une carte manquante vaut mieux qu'un écran blanc.

| Type | Ce qu'il montre | Particularité |
| --- | --- | --- |
| `kpi` | un chiffre | `null` affiche un tiret, jamais zéro : « aucun client » et « le chiffre n'a pas pu être lu » ne sont pas la même information |
| `alert` | un compte qui appelle une action | zéro est une bonne nouvelle, et se rend sobrement |
| `chart` | une barre de composition | **sans bibliothèque** : voir §5 |
| `donut` | un anneau, six parts au plus | ce n'est pas une variante de la barre : voir §5 |
| `gauge` | un rapport, et son tout | une seule teinte, jamais de vert-orange-rouge : voir §5 |
| `columns` | un volume quotidien, empilé | la seule forme qui dise le temps **et** la composition : voir §5 |
| `lines` | une tendance sur un mois | un seul axe, toujours : voir §5 |
| `list` | six lignes, et « Voir tout » | la seule carte non cliquable : voir §6 |
| `quick_action` | un libellé, une destination | le seul dont `data` vaut `null` |

**Aucune tendance.** Un « +12 % » sous un compteur se lit comme une mesure, et
il n'y en a pas : le serveur ne compare pas deux périodes. L'inventer aurait
produit un chiffre décoratif que quelqu'un finirait par citer en réunion.

---

## 5. Les graphes

Le projet n'embarque aucune bibliothèque de graphes — seul Leaflet est installé,
pour les cartes. `ChartWidget` s'en passe : une barre segmentée est trois `div`
et une croissance en flex. En installer une pour cela aurait pesé quelques
centaines de kilo-octets. Un histogramme temporel reposerait la question, et ce
serait alors une décision de dépendance, prise comme telle.

### Une barre de composition, puis sa légende chiffrée

Une **seule barre segmentée**, et non une barre par statut. La question à
laquelle « Commandes par statut » répond est *comment mes commandes se
répartissent*, pas *laquelle est la plus longue* : les parts s'additionnent, et
les voir s'additionner est l'information. Le total est rappelé au-dessus — la
barre dit comment il se répartit, pas de combien il s'agit.

Trois détails de rendu, et aucun n'est cosmétique :

| Détail | Ce qu'il évite |
| --- | --- |
| **2 px de fond entre les segments**, jamais un trait | une bordure ajoute de l'encre qui n'est pas de la donnée ; le vide sépare aussi bien |
| **extrémités arrondies, intérieur carré** | l'arrondi marque la fin de la donnée ; l'appliquer à chaque segment ferait mentir sur les frontières internes |
| **10 px d'épaisseur** | un bloc épais et saturé se lit comme un bandeau décoratif |

### La palette n'est pas choisie, elle est mesurée

Les huit teintes de `--chart-1` … `--chart-8` passent six contrôles sur la
surface réelle des cartes — bande de clarté, plancher de saturation, séparation
sous daltonisme (ΔE 9,1 au pire couple voisin), plancher en vision normale
(ΔE 19,6), contraste — en clair comme en sombre. Le mode sombre a ses **propres
pas**, redressés pour son fond : une inversion automatique les ferait sortir de
la bande.

Deux règles en découlent, et elles ne se négocient pas :

- **la couleur suit la série, jamais son rang.** Les teintes sont attribuées
  dans l'ordre du cycle de vie, pas par valeur décroissante. Une commande de
  plus repeindrait sinon la moitié du graphe, et un lecteur qui avait retenu
  « les brouillons sont bleus » se tromperait le lendemain ;
- **il n'y a pas de neuvième teinte.** Au-delà de huit séries, la queue fusionne
  en « Autres », en gris. Une couleur générée serait indistinguable d'une autre
  sous vision altérée, et la palette cesserait de garantir ce qu'elle garantit.

### La légende est aussi le tableau

Une même liste tient deux exigences. La **légende** est obligatoire dès deux
séries : sans elle, l'identité reposerait sur la seule couleur. Le **tableau**
rend chaque valeur lisible sans survoler quoi que ce soit — un chiffre qu'on ne
peut atteindre qu'à la souris n'est pas accessible.

La pastille de couleur est **à côté** du texte, jamais dedans : trois des huit
teintes n'ont pas le contraste d'un texte sur fond blanc, et colorer le libellé
les rendrait illisibles.

L'ordre des lignes vient du **référentiel des statuts**, pas du tri par code que
rend le serveur : `completed, confirmed, draft` prend le pipeline à l'envers.
Les libellés en viennent aussi — un statut ajouté par un administrateur
s'affiche avec le nom qu'il lui a donné.

### Trois formes, et ce qui les sépare

Elles portent la même donnée pour deux d'entre elles, et ce n'est pas la donnée
qui choisit : c'est le **catalogue**, une fois, sur le nombre de parts que la
série peut atteindre.

| Forme | Quand | Pourquoi pas une autre |
| --- | --- | --- |
| barre de composition | dix statuts de commande, aux noms longs | dix secteurs voisins deviennent indistinguables |
| camembert | six statuts de tournée, cinq canaux d'envoi | une barre se lit de gauche à droite, pas comme un tout |
| jauge | **un** rapport : ce qui est planifié, ce qui est couvert | un camembert à deux secteurs occupe deux fois la place et fait passer le reste pour une catégorie |

Le camembert est **évidé**, et non plein : un disque plein demande de comparer
des angles au sommet, ce que l'œil fait mal ; un anneau les rend en longueurs
d'arc, qu'il lit bien mieux. Le centre libéré porte le total, qui n'a nulle part
ailleurs où aller sans occuper une ligne de plus.

Il garde **la même légende** que la barre, et c'est voulu : un camembert seul ne
permet pas de comparer deux parts proches — c'est son défaut connu, et la liste
chiffrée à côté est la réponse, pas un ornement.

La jauge affiche le taux **et** le compte, jamais l'un sans l'autre : « 72 % »
ne dit pas si l'on parle de neuf cas sur douze ou de neuf cents sur mille deux
cents. Elle porte **une seule teinte**, jamais de vert-orange-rouge : ces taux
n'ont pas de bon sens universel — une part de stock réservée élevée est
excellente pour un commercial et inquiétante pour un logisticien, et en peindre
un en rouge trancherait à leur place. Et zéro sur zéro n'affiche pas 0 %, qui se
lirait comme un échec, mais « rien à mesurer ».

### Les deux graphes qui portent le temps

Toutes les formes précédentes photographient l'instant. Celles-ci montrent les
jours.

Les **colonnes empilées** disent un volume quotidien *et* sa composition : la
hauteur totale dit combien, l'empilement dit de quoi. C'est la seule forme du
catalogue à dire les deux. Les **courbes** disent une tendance, que l'œil suit
bien mieux qu'il ne compare trente hauteurs voisines — d'où une fenêtre plus
large, trente jours contre quatorze.

Quatre décisions y sont prises, et aucune n'est cosmétique :

| Décision | Ce qu'elle évite |
| --- | --- |
| **Les graduations sont choisies avant le plafond** — le pas d'abord, pris parmi 1, 2 et 5 fois une puissance de dix | diviser le plafond en trois donnait « 100 / 67 / 33 », des nombres exacts que personne ne lit |
| **La hauteur de la carte inclut la bande des dates** | fixer la hauteur du tracé fait déborder les libellés, et le navigateur pose un minuscule ascenseur à l'intérieur de la carte |
| **Le survol est capté par une bande large de tout l'intervalle** | viser une colonne de six pixels ou un point de courbe demanderait une précision que personne n'a |
| **Des segments droits, jamais de lissage** | une interpolation invente un creux qui n'a pas eu lieu, ou un pic qui dépasse le maximum réel |

Les points de survol sont dessinés **hors du SVG**, en HTML positionné en
pourcentages : le tracé travaille en coordonnées relatives (`preserveAspectRatio="none"`),
et un cercle dessiné dedans serait devenu une ellipse d'autant plus aplatie que
la carte est large.

Le jour survolé et ses valeurs s'écrivent **en tête de carte et dans la
légende**, non dans une infobulle flottante : celle-ci aurait recouvert la
donnée qu'on vient de viser, et demandé d'être placée sans jamais sortir de la
carte — deux problèmes pour un gain nul.

Les colonnes occupent le **milieu de leur intervalle**, les points de courbe se
posent **sur le bord** — le premier à 0 %, le dernier à 100 %. Une convention
unique aurait décalé l'une des deux visées d'une demi-case, exactement là où on
regarde.

### Le graphe qui ne dessine aucune barre

`closed_invoices_period_total` rend une liste de montants, une devise par ligne.
Une longueur proportionnelle affirme une comparaison — deux fois plus long, deux
fois plus — et 5 000 CHF ne se rangent pas sur la même règle que 5 000 MAD. On
refuse déjà de sommer les devises dans une valeur unique ; les mettre sur une
échelle commune reviendrait à le faire du regard.

C'est le serveur qui le déclare, par `mode: 'amounts'` : c'est lui qui sait que
`currency_code` sépare des monnaies incomparables.

---

## 6. Ce qui est cliquable, et ce qui ne l'est pas

Une carte porte un lien quand le catalogue lui en donne un. **Toutes n'en ont
pas** : un compteur de services n'a pas d'écran où mener — les services se
lisent dans leur commande, et `/services` est le catalogue des prestations
vendues. Sans `to`, la carte reste un `div` : pas de curseur en main, pas de
survol, rien qui suggère un clic.

`ListWidget` fait exception dans l'autre sens : **la carte n'est pas cliquable,
ses lignes le sont**. Un lien qui envelopperait des lignes elles-mêmes liées
imbriquerait deux ancres, ce que le HTML n'admet pas et que chaque navigateur
démêle à sa façon. Le lien vers la liste entière est donc en pied de carte — ce
qui est aussi ce qu'on y cherche du regard.

---

## 7. La grille

```
écran large  → 12 colonnes
tablette     →  6 colonnes
téléphone    →  1 colonne
```

La taille vient du **catalogue**, pas de la configuration. L'administrateur
choisit ce qu'un rôle voit et dans quel ordre ; lui laisser régler la largeur de
chaque carte aurait demandé un éditeur de page, pour un besoin qui est de
composer une vue métier — et aurait permis de rendre illisible un tableau de
bord qu'on ne peut plus corriger qu'en le rouvrant.

L'ordre est celui que le serveur a rendu. Retrier ici aurait donné un second
ordre, à défendre contre le premier.

---

## 8. Attente, erreur, écran vide

| État | Ce qu'on montre |
| --- | --- |
| chargement | un squelette de huit tuiles neutres |
| erreur | « Impossible de charger », et **Réessayer** |
| aucun widget | « Votre tableau de bord ne contient actuellement aucun widget. » |

Le squelette remplace ce qui se faisait avant : afficher les quatre anciennes
cartes le temps de la requête. Elles montraient à chacun un tableau de bord qui
n'était pas le sien, puis disparaissaient — ce qui ressemble à une panne bien
plus qu'à un chargement.

En erreur, **aucun repli** sur des chiffres qu'on aurait le droit de lire : une
erreur se dit, elle ne se comble pas.

L'écran vide propose **Configurer les rôles** — mais seulement à qui détient
`dashboard.configure`. Sans la permission, le bouton aurait mené à un écran qui
refuse, et laissé penser que le tableau de bord vide vient d'une erreur.

---

## 9. L'écran de réglage

Trois parties, dans l'ordre où l'on s'en sert : l'**ordre** des widgets actifs,
l'**aperçu**, puis le **catalogue** où l'on coche. Le catalogue vient en dernier
bien qu'il soit le plus long : on ouvre cet écran plus souvent pour remanier une
composition que pour la créer.

### Le catalogue montre tout

Y compris les widgets décochés, et y compris ceux que le rôle n'a pas le droit
de voir. Les retirer aurait rendu le geste irréversible pour les premiers — plus
d'endroit où les remontrer — et muet pour les seconds : on chercherait
« Factures brouillon » sans le trouver, sans savoir qu'il ne manque qu'une
permission.

Quand `availableForRole` est faux, l'interrupteur est **désactivé** et la
permission écrite en toutes lettres :

```
Permission requise : invoices.view
```

Le code brut, pas un libellé traduit : c'est ce que l'administrateur va chercher
dans l'onglet Permissions, juste à côté.

**Cet écran n'accorde jamais rien.** Un interrupteur qui aurait ajouté la
permission manquante aurait fait de la composition d'un tableau de bord une voie
d'élévation.

### Deux façons de réordonner

Le **glisser-déposer** est le geste naturel quand on remanie une liste entière ;
les **flèches** sont le seul geste possible au clavier, et un écran qu'on ne
peut régler qu'à la souris exclut ceux qui n'en utilisent pas. Elles servent
aussi quand on ne déplace qu'une carte d'un rang.

Le glisser-déposer est celui du navigateur — `draggable`, `dragover`, `drop` —
et non celui d'une bibliothèque.

Les rangs ne sont pas affichés : ils n'ont de sens que les uns par rapport aux
autres, et sont renumérotés en bloc à l'enregistrement, sans trou. Montrer « 3 »
aurait invité à le corriger à la main.

### Un seul enregistrement

Rien ne part au fil des clics. Composer un tableau de bord demande une dizaine
de gestes, et les transmettre un par un aurait produit dix écritures, dix lignes
de journal pour une seule décision, et un état à moitié enregistré si le réseau
lâche au milieu.

Une exception, et elle se justifie : **la réinitialisation** prend effet tout de
suite. Elle supprime la configuration, et l'accumuler dans le brouillon aurait
demandé d'y représenter une absence de ligne. Elle demande confirmation, et
abandonne le brouillon — la liste change de forme sous lui.

### L'aperçu ne charge aucune donnée

Il dessine des tuiles vides aux bonnes places, d'après les métadonnées déjà
reçues. C'est une précaution, pas une économie : afficher ici les chiffres du
rôle qu'on configure aurait montré à l'administrateur des données qu'il n'a
peut-être pas le droit de lire, alors qu'il a seulement le droit de **ranger**
ce que d'autres liront.

Et pas de chiffre d'exemple non plus : « 42 » sur une carte de facturation
finirait par être lu comme une valeur.

---

## 10. Trois onglets sur la fiche du rôle

```
Permissions | Menu | Tableau de bord
```

Ils se pensent ensemble — un rôle qui gagne une permission veut souvent l'entrée
de menu et la carte qui vont avec — mais empilés sur une seule page ils
faisaient trois longues listes qu'il fallait dérouler l'une après l'autre.

L'ordre dit la dépendance : les permissions d'abord, parce que ni le menu ni le
tableau de bord n'accordent quoi que ce soit. Masquer une entrée ne retire aucun
droit — l'écran reste atteignable par son adresse ; activer un widget n'en donne
aucun — il ne s'affichera pas sans la permission qu'il exige.

Un rôle de portée **plateforme** se consulte sans se régler : le catalogue reste
lisible — c'est ce que ses porteurs verront — mais aucune action n'est proposée.
Le rôle **système**, lui, se règle : il porte toutes les permissions, et lui
interdire aurait privé l'administrateur du seul tableau de bord qu'il voit.

---

## 11. Ce que les tests tiennent

| Fichier | Ce qu'il empêche |
| --- | --- |
| `dashboard/pages/DashboardPage.test.tsx` | qu'un type ne se rende pas ; que les anciennes cartes reparaissent pendant le chargement ; qu'une erreur soit comblée ; que le raccourci de réglage soit proposé sans la permission |
| `dashboard/components/RoleDashboardPanel.test.tsx` | qu'un widget sans permission soit activable ; qu'un clic enregistre ; que les rangs partent non renumérotés ; qu'un rôle non réglable propose des actions |
| `dashboard/components/widgets/ChartWidget.test.tsx` | qu'une série ne soit nommée que par sa couleur ; qu'une neuvième teinte soit inventée ; que des devises partagent une échelle |
| `dashboard/components/widgets/DonutWidget.test.tsx` | qu'une jauge rende 0 % faute de données ; qu'elle dépasse le tour complet ; qu'un code d'énumération s'affiche brut |
| `dashboard/components/widgets/TimeseriesWidgets.test.tsx` | qu'une graduation soit un nombre qu'on ne lit pas ; qu'un jour vide dessine une colonne ; qu'une courbe soit lissée |
