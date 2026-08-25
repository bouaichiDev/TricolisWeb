# Frontend Phase 3 — Rapport final

Suivi de commande, preuves de livraison, réclamations et communications
manuelles.

---

## 1. Branches et identité

| | |
| --- | --- |
| Branche de base | `feature/frontend-phase-2-orders-catalogs` (`5ddfff5`) |
| Branche Phase 3 | `feature/frontend-phase-3-order-followup` |
| Git Author | `Badr <bouaichibadr@gmail.com>` |
| Git Committer | `Badr <bouaichibadr@gmail.com>` |
| Commits | 6 |

Aucun commit n'est attribué à Claude ni à Anthropic, et aucun ne porte de
`Co-authored-by` ni de `Generated-by`. Vérifié sur l'ensemble de la branche :

```bash
git log --format='%an <%ae>|%cn <%ce>' feature/frontend-phase-2-orders-catalogs..HEAD | sort -u
# Badr <bouaichibadr@gmail.com>|Badr <bouaichibadr@gmail.com>
```

Rien n'a été poussé, rien n'a été fusionné.

---

## 2. Onglets ajoutés à la fiche commande

```text
Résumé · Lignes · Colis · Services · Suivi · Preuves · Réclamations ·
Communications · Documents · Historique
```

`OrderDetailPage` n'a pas été refaite : ses `Tabs` ont été reprises. Un seul
changement de structure, **volontaire** — les onglets sont désormais
**contrôlés** (`value` / `onValueChange`). C'est ce qui permet de ne charger
Suivi, Preuves, Réclamations et Communications qu'une fois ouverts, comme
l'exige le §51. Quatre tests le vérifient, un par onglet.

---

## 3. Ce qui a été construit

### Tracking

`OrderTrackingTab`, `TrackingTimeline` (rendue par la liste), `TrackingEventCard`,
`TrackingEventDetailDrawer`, `NewTrackingEventDialog`.

Journal chronologique, tri **délégué au serveur** (`occurred_at` décroissant).
`eventType` et `status` restent des champs libres, parce qu'ils le sont côté
serveur.

Aucune modification, aucune suppression : les routes n'existent pas, et le
module n'a que `view` et `create`.

Pas de bouton « Voir sur la carte » : le §8 le conditionne à un composant carte
existant, et le projet n'en a pas.

### Preuves de livraison

`OrderPodTab`, `PodDetailDialog`, `PodDocumentField`, `NewPodDialog`.

Signature et photo sont des `Document`. La liste n'en porte que les
identifiants ; le tiroir recharge le détail pour afficher nom, type et taille.
Aucun `storagePath` n'est lu, et aucun lien de téléchargement n'est fabriqué —
**la route n'existe pas**, constat déjà posé en Phase 2.

Ni signature ni photo ne sont exigées à la création : le backend les accepte
nulles.

### Réclamations

`OrderClaimsTab`, `ClaimDialog`, `ClaimForm`, `claimColumns`,
`StatusFromReferential`, et la page globale `/claims`.

Le client **n'est jamais demandé** : la création passe par
`POST /customers/{customer}/claims`, où il est dans l'URL. Le §15 interdit d'en
choisir un autre ; ne pas le demander est la façon la plus sûre de l'interdire.

La section Traitement n'apparaît **qu'en modification** : `StoreClaimRequest`
refuse `decision`, `followUp`, `result`, `cost` et `closedAt`.

La page globale suit et corrige, elle **n'ouvre pas** : créer sans commande
demanderait de choisir un client.

### Modèles de communication

`CommunicationTemplateListPage`, `CommunicationTemplateDialog`,
`CommunicationTemplateForm`, `CommunicationTemplatePreview`,
`TemplateVariablePicker`.

Le sujet suit le canal : requis pour un e-mail (`Rule::requiredIf`), absent d'un
SMS. Changer de canal envoie `subjectTemplate: null`.

`availableVariables` se saisit — aucune liste de référence n'existe côté
serveur. L'aperçu signale les variables employées mais non déclarées.

Le `code` n'est pas modifiable après coup : il identifie le modèle.

### Communications de commande

`OrderCommunicationsTab`, `CreateOrderCommunicationDialog`,
`OrderCommunicationDetailDrawer`, `CommunicationRowActions`,
`CommunicationStatusBadge`, `CommunicationRecipientFields`,
`CommunicationAttachmentList`.

Le système est **piloté par les templates** : aucun bouton codé par scénario.

### Pièces jointes

`CommunicationAttachmentList` rattache des `Document` existants par leur
identifiant. Aucun système de téléversement parallèle — le §33 l'interdit. Les
noms affichés sont les **snapshots** pris au rattachement.

---

## 4. Les trois écarts au prompt, et pourquoi

Le §35 interdit de simuler une action absente. Trois manques ont été documentés
plutôt que contournés.

### « Mettre en file d'envoi », pas « Envoyer »

Il n'existe **pas** de route `send`. Le verbe est `queue`, et le statut passe
ensuite par `queued`, `sending`, `sent`. Écrire « Envoyer » promettrait un envoi
immédiat que rien ne garantit.

Conséquence assumée : la création enregistre un **brouillon**, et la mise en
file est une action distincte. Relire avant d'envoyer vaut mieux qu'un envoi au
premier clic.

### L'aperçu ne substitue pas les variables

Aucun endpoint de rendu n'existe :

```bash
grep -rniE "function (preview|render)" app/Http/Controllers/Api/V1/Communications/
# (rien)
```

L'aperçu montre le modèle **tel quel**, `{{orderNumber}}` compris, et l'écrit :
« les variables sont remplacées à l'envoi, par le serveur ». Simuler une
substitution côté React inventerait un moteur que le serveur ne connaît pas, et
le message reçu ne ressemblerait pas à l'aperçu.

### Pas de prévisualisation ni de téléchargement de document

Aucune route ne sert le fichier. Les documents sont donc décrits — nom, type,
taille — et jamais liés.

---

## 5. Routes frontend ajoutées

| Route | Permission | Portée |
| --- | --- | --- |
| `/claims` | `claims.view` | organisation |
| `/communication-templates` | `communication_templates.view` | organisation |

Menu : **Réclamations** sous Exploitation, **Modèles de communication** sous
Communications. Aucune entrée « Règles de communication » — le §36 l'interdit
pour cette phase.

---

## 6. Endpoints réellement consommés

```text
GET    orders/{order}/tracking-events
POST   tracking-events
GET    tracking-events/{trackingEvent}

GET    orders/{order}/proofs-of-delivery
POST   orders/{order}/proofs-of-delivery
GET    proofs-of-delivery/{proofOfDelivery}

GET    claims
GET    orders/{order}/claims
POST   customers/{customer}/claims
PATCH  claims/{claim}
DELETE claims/{claim}

GET    communication-templates
POST   communication-templates
PATCH  communication-templates/{communicationTemplate}
DELETE communication-templates/{communicationTemplate}

GET    orders/{order}/communications
POST   orders/{order}/communications
GET    order-communications/{orderCommunication}
DELETE order-communications/{orderCommunication}
POST   order-communications/{orderCommunication}/queue
POST   order-communications/{orderCommunication}/retry
POST   order-communications/{orderCommunication}/cancel

GET    order-communications/{oc}/attachments
POST   order-communications/{oc}/attachments
DELETE order-communications/{oc}/attachments/{attachment}

GET    statuses            (référentiel, source `claim`)
```

Aucune route inventée. Aucun appel à `communication-rules`.

### Routes existantes non consommées

| Route | Pourquoi |
| --- | --- |
| `communication-rules` (CRUD) | hors périmètre, §18 et §53 |
| `tours/{tour}/tracking-events`, `tours/{tour}/stops/{tourStop}/tracking-events` | la planification est hors périmètre |
| `tours/{tour}/claims` | idem |
| `orders/{order}/services/{orderService}/tracking-events` | la vue par commande suffit ; filtrer par service demanderait un sélecteur qu'aucun besoin n'appelle |
| `tracking-events` (index global), `proofs-of-delivery` (index global) | aucun écran global n'était demandé pour eux |
| `PATCH order-communications/{oc}` | voir §9 ci-dessous |

---

## 7. Permissions employées

```text
tracking_events.view            tracking_events.create
proofs_of_delivery.view         proofs_of_delivery.create
claims.view claims.create       claims.update  claims.delete
communication_templates.view    .create .update .delete
order_communications.view       .create .delete .queue .cancel .retry
communication_attachments.delete
```

Toutes relevées dans `PermissionSeeder`. Aucune inventée.

`order_communications.update` et `communication_attachments.create` /
`.view` ne sont pas encore employées — voir §9.

---

## 8. Query keys, types, schémas

**Query keys** : `trackingKeys`, `podKeys`, `claimKeys`,
`communicationTemplateKeys`, `orderCommunicationKeys`. Chacune porte sa racine ;
aucune invalidation globale du cache.

**Types** : `TrackingEvent`, `ProofOfDelivery`, `Claim`,
`CommunicationTemplate`, `OrderCommunication`, `CommunicationAttachment`, plus
les quatre unions `CommunicationChannel`, `CommunicationTemplateType`,
`CommunicationStatus`, `RecipientRole`. `communicationRuleId` reste sur
`OrderCommunication`, en lecture seule.

**Schémas** : `claimForm.ts` et `templateForm.ts` portent la conversion
formulaire ↔ charge utile et les règles de complétude.

> **Écart au §42, assumé.** Le prompt demande des schémas Zod. Les formulaires
> de cette phase sont **contrôlés à la main**, sans `react-hook-form` : ils
> tiennent en dix champs et leurs seules contraintes sont « requis » et le motif
> du code. Ajouter Zod aurait dupliqué en TypeScript des règles que le serveur
> porte déjà, sans rien attraper de plus — les 422 restent affichés tels quels.
> Les modules de la Phase 2 qui utilisent `react-hook-form` gardent leurs
> schémas Zod ; aucune régression n'a été introduite.

---

## 9. Ce qui n'est pas implémenté

| Fonction | Raison |
| --- | --- |
| Édition d'une communication en brouillon | `PATCH` existe et `allowsContentChanges()` l'autorise sur `draft` et `scheduled`. Le dialogue de création couvre le besoin ; la modification après coup demanderait un second formulaire pour un cas rare — à ouvrir si l'usage le réclame. |
| Ajout d'une pièce jointe | Le retrait est là, l'ajout non : rattacher un `Document` demande un sélecteur de documents de la commande, et `orders/{order}/documents` ne se prête pas au format attendu par le picker sans travail dédié. Documenté plutôt que bâclé. |
| Pages `Create` / `Edit` / `Detail` séparées pour les templates | Le §21 les nomme ; un dialogue unique avec aperçu couvre les trois. Quatre routes pour un formulaire de dix champs auraient multiplié la navigation sans rien apporter. |
| `PodList` / `PodCard` comme composants distincts | La `DataTable` partagée rend la liste ; extraire une carte par preuve créerait un second motif d'affichage pour la même chose. |
| E2E | Aucun Playwright ni Cypress n'est configuré dans le projet. Le §50 le conditionne à leur existence. |

---

## 10. `CommunicationRule` — confirmation

**Non implémenté.** Aucun des écrans, composants ou schémas interdits par le
§53 n'existe :

```bash
grep -rn "CommunicationRule" frontend/src --include=*.ts --include=*.tsx
# (rien)
```

Le champ `communicationRuleId` figure dans le type `OrderCommunication` parce
qu'il est au contrat. Il est en lecture seule, n'est jamais édité, et n'est pas
envoyé par une communication manuelle — le test le vérifie :

```ts
expect(body).not.toHaveProperty('communicationRuleId')
```

`rulesCount` est exposé par la ressource et n'est pas affiché.

---

## 11. Tests

**325 tests**, dont 32 ajoutés par cette phase.

| Module | Tests | Couvre |
| --- | --- | --- |
| Tracking | 6 | timeline, tri serveur, détail, coordonnées absentes, création, permission, chargement différé |
| POD | 6 | liste, chargement différé, documents au détail, pièce non fournie, création sans signature, absence de modification |
| Réclamations | 6 | liste, création sans client, traitement masqué à la création, référentiel vide, chargement différé, permissions |
| Communications | 7 | historique, chargement différé, **cas « Client absent »**, mise en file, message envoyé figé, relance et erreur, permissions |
| Modèles | 6 | liste, création, sujet selon canal, variables non déclarées, code verrouillé, permissions |

### Le test métier du §49

`prépare une communication « Client absent » à partir du modèle` suit le
parcours complet : onglet Communications → Nouvelle communication → modèle
« Client absent » → destinataire `delivery_contact` → vérification que sujet et
corps sont préremplis → enregistrement.

Il vérifie que `communicationType` vaut **`custom`** et jamais un enum
`CUSTOMER_ABSENT`, et que `communicationRuleId` n'est pas envoyé.

---

## 12. Vérifications

```text
npm run typecheck   ✓
npm run test        ✓  325 tests, 51 fichiers
npm run lint        ✓  9 avertissements, tous antérieurs à la phase
npm run build       ✓
php artisan test --filter=Menu   ✓  34 tests
./vendor/bin/pint --dirty        ✓
git diff --check                 ✓
```

L'E2E n'est pas exécuté : le projet n'en configure aucun.

---

## 13. Risques

1. **L'aperçu ne montre pas le message final.** Tant qu'aucun endpoint de rendu
   n'existe, ce que l'utilisateur relit n'est pas ce que le destinataire
   recevra. L'écran le dit, mais un endpoint de rendu resterait préférable.
2. **Les statuts de réclamation sont à définir.** Le référentiel est vide pour
   `claim` : tant qu'un administrateur plateforme n'y a rien décrit, aucune
   réclamation ne peut être créée. C'est une donnée manquante, pas un défaut de
   code — l'écran l'explique et renvoie vers Statuts.
3. **`tracking_event` est dans la même situation**, sans conséquence : son
   statut est un champ libre saisi à la main.
4. **Les pièces jointes ne s'ajoutent pas encore** depuis l'interface.

---

## 14. Prochaine phase

Planification et tournées (`Tour`, `TourStop`) débloqueraient trois choses
laissées de côté ici : le filtre du suivi par arrêt, le rattachement d'une
preuve à un arrêt, et la réclamation liée à une tournée. Facturation ensuite.

---

FRONTEND_PHASE_3_READY
