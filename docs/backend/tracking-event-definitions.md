# Le parcours client, configuré depuis les statuts

Conception de la table qui décide **quels changements de statut deviennent des
événements visibles par le client**, et dans quel ordre.

---

## 1. Le besoin

> « Lorsqu'un statut est posé dans la table — par exemple le service passe à
> *chargé* — la timeline affiche cet événement. Le chauffeur pose le statut,
> l'événement s'ajoute automatiquement. Dans la configuration on a déjà dit que
> lorsque le service a ce statut, on peut le tracker. Les événements ont un
> ordre : créé, chargé, en route, livré. »

Trois exigences en découlent :

1. **Automatique** — personne ne saisit l'événement. Le chauffeur change un
   statut, l'événement apparaît.
2. **Configurable sur n'importe quelle table** — le statut déclencheur peut
   vivre sur `order`, `order_service`, `package`, `tour`, `tour_stop`…
3. **Ordonné** — le parcours se lit de bout en bout, même si les événements
   arrivent dans le désordre.

Plus une quatrième, ajoutée ensuite : un événement doit pouvoir **déclarer
qu'il est suivi par une API externe** — la position du chauffeur sur une carte,
pendant « en route ».

---

## 2. Pourquoi une table, et pas le référentiel de statuts

`statuses` décrit **ce qu'un statut signifie** pour une entité. La question ici
est différente : *quel statut mérite d'être montré au client, sous quel nom, et
à quelle place du parcours*.

Trois raisons de ne pas surcharger `statuses` :

- un même statut peut mériter un événement **et** ne pas en mériter selon
  l'organisation ; la visibilité client n'est pas une propriété du statut ;
- le **titre montré au client** n'est pas le libellé interne. « chargé » côté
  exploitation devient « Votre commande est en route vers vous » ;
- l'**ordre du parcours** est propre au parcours, pas au statut. Deux statuts de
  deux tables différentes peuvent occuper deux étapes consécutives.

C'était le choix de l'utilisateur : « je crois le plus propre est une table
séparée pour configurer les événements ».

---

## 3. La table

```text
tracking_event_definitions
- id                 ULID
- organization_id    ULID
- source_type        string(64)   alias de morph map : order_service, tour…
- status_code        string(64)   le statut qui declenche
- code               string(64)   code de l'evenement : loaded, en_route…
- title              string(255)  ce que le client lit
- description        text|null    le detail, facultatif
- icon               string(64)|null
- position           unsignedSmallInteger   ordre dans le parcours
- is_live            boolean      suivi par une API externe (carte)
- active             boolean
- timestamps

unique(organization_id, source_type, status_code)
index(organization_id, active, position)
```

### `unique(organization_id, source_type, status_code)`

**Un statut, un événement** — l'utilisateur l'a tranché. Deux définitions pour
le même couple produiraient deux événements pour un seul changement, et rien ne
dirait lequel affiher.

La contrainte porte l'organisation : deux organismes décrivent leur parcours
indépendamment.

### `source_type` n'est pas une énumération

C'est un alias de morph map, validé contre `MorphMap::registered()` à
l'écriture. Recopier ici la liste des 39 sources la ferait diverger à la
première entité ajoutée.

### `is_live`

Un booléen, pas une URL. **Le frontend saura qu'une étape se suit sur une
carte ; il ne saura pas d'où vient la position.** L'API externe qui rend les
coordonnées du chauffeur n'est pas décrite ici : son adresse, son
authentification et son format ne sont pas encore arrêtés, et les inventer
créerait un contrat que personne n'a signé.

Ce que `is_live` permet dès maintenant : afficher la carte à la bonne étape, et
seulement à celle-là.

---

## 4. Comment l'événement naît

Un **observateur** sur les modèles portant un statut, plutôt qu'un appel dans
chaque Action.

`order_services.status` s'écrit aujourd'hui depuis six endroits — `ChangeOrderStatus`,
`CreateFullOrder`, `CreateOrderServices`, `DuplicateOrder`,
`DuplicateOrderServices`, `OrderServiceController`. En ajouter un septième
demain sans penser au parcours laisserait un trou silencieux : la commande
avancerait sans que le client le voie.

L'observateur écoute `saved`, compare l'ancien et le nouveau statut, et publie
si une définition correspond. Il ne publie **rien** quand le statut n'a pas
changé.

### Idempotence

`tracking_events` porte déjà `order_id`, `event_type` et `occurred_at`. Un
aller-retour de statut — chargé → en préparation → chargé — produirait deux
événements identiques.

La règle retenue : **un événement par couple (commande, code)**. Repasser par un
statut déjà franchi ne réécrit pas l'histoire ; le parcours dit *où on en est*,
pas combien de fois on y est passé.

---

## 5. Ce que le client voit

Le parcours d'une commande se lit en croisant :

- les **définitions actives** de l'organisation, triées par `position` ;
- les **`tracking_events`** de la commande.

Chaque étape est alors *franchie* (un événement existe, avec sa date), ou *à
venir*. C'est ce qui permet d'afficher le parcours complet dès la création —
créé · chargé · en route · livré — plutôt qu'une liste qui s'allonge sans dire
ce qui reste.

---

## 6. Ce qui n'est pas fait ici

| | Pourquoi |
| --- | --- |
| Conditions multiples (« tous les colis chargés ») | « normalement un seul statut » — un statut, un événement |
| L'API de position du chauffeur | contrat non arrêté ; `is_live` marque l'étape, sans préjuger de la source |
| La carte elle-même | dépend de l'API ci-dessus |
| Le portail client | cette phase montre le parcours côté exploitation |
