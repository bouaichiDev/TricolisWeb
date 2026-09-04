# Notifications

La cloche du bandeau supérieur. **Aucune table nouvelle** : le domaine porte les
deux notions depuis la Phase 9.

---

## 1. Ce qui existait déjà, et qu'il suffisait de servir

L'en-tête portait cette place vide, et le disait : « aucun endpoint ne les
alimente ». C'était vrai de l'API, pas du modèle.

| Ce que le domaine porte | Depuis |
| --- | --- |
| `CommunicationChannel::INTERNAL_NOTIFICATION` | Phase 9 |
| `RecipientRole::INTERNAL_USER` | Phase 9 |
| `InternalCommunicationSender` | Phase 9 |
| `order_communications.read_at` | Phase 9, jamais écrit |

`InternalCommunicationSender` le dit en toutes lettres : « une notification
interne **est** la ligne `order_communications` elle-même ». Une règle de
communication qui vise un `internal_user` sur un canal `internal_notification`
en produit une, et elle attendait qu'on la lise.

Créer une table `notifications` par-dessus aurait fait un second journal des
mêmes événements, à tenir synchronisé avec le premier.

---

## 2. Deux moitiés qui ne se ressemblent pas

C'est le cœur du modèle, et ce qui justifie les deux onglets.

| | **Internes** | **Externes** |
| --- | --- | --- |
| À qui | **à moi**, par mon adresse | à un client ou un contact |
| Ce qu'on montre | tout | seulement ce qui a **échoué** |
| État de lecture | `read_at`, propre à moi | aucun |
| Compte dans la pastille | oui | non |
| Permission | aucune | `order_communications.view` |

**Les internes m'appartiennent.** Elles portent mon adresse et me sont
adressées ; exiger `order_communications.view` pour lire ce qu'on m'écrit aurait
été absurde.

**Les externes appartiennent à l'organisation.** Ce sont ses envois à ses
clients : les lire demande la permission qui ouvre leur historique, et leur
donner un état de lecture par utilisateur laisserait croire qu'un collègue s'en
occupe. Elles ne comptent donc pas dans la pastille — un compteur qu'aucun geste
ne fait baisser cesse d'être lu au bout d'une journée.

**Un envoi réussi n'y figure pas.** Il n'appelle aucune action, et noierait les
échecs qui, eux, en appellent une. L'historique complet est à un clic.

---

## 3. Le destinataire, et pourquoi c'est une adresse

`order_communications` ne porte **pas** de `recipient_user_id` : le destinataire
y est décrit par un nom, une adresse et un téléphone — ce qu'il faut pour
expédier. `ResolveOrderCommunicationRecipient::fromUser()` y écrit donc l'adresse
du compte, et c'est par elle qu'on retrouve les siennes.

Cela suffit — l'adresse d'un compte est unique dans `users` — mais deux
conséquences en découlent, et les deux sont tenues :

- un compte **sans adresse** ne reçoit rien, ce que la comparaison stricte
  garantit plutôt que de tout lui rendre ;
- le filtre est **toujours** accompagné de l'organisation active : la même
  adresse dans deux organisations ne mélange pas leurs notifications.

---

## 4. Les routes

```
GET  /api/v1/notifications                     tout compte authentifié
POST /api/v1/notifications/read-all            les miennes, dans l'organisation active
POST /api/v1/notifications/{communication}/read `markAsRead`
```

**Hors du middleware `organization`**, comme `GET /menu`. La cloche est rendue
sur chaque page, y compris pour un compte plateforme qui n'agit dans aucune
organisation : exiger l'en-tête lui rendrait une erreur là où la réponse juste
est « rien à signaler ».

`read-all` est déclarée **avant** `{communication}/read` : sans cela, le
paramètre avalerait le segment.

### `markAsRead`, et pourquoi ce n'est pas `update`

`update` modifie le contenu d'une communication et demande
`order_communications.update` ; `markAsRead` constate qu'on a lu ce qui nous
était adressé. Un porteur d'`update` n'a aucune raison de marquer lues les
notifications d'un collègue, et le destinataire n'a aucune raison d'avoir
`update` pour lire les siennes.

Trois conditions, et les trois comptent :

| Condition | Ce qu'elle évite |
| --- | --- |
| Le canal est **interne** | sur un envoi externe, `read_at` porte l'accusé de lecture du destinataire réel — l'écraser falsifierait la trace de l'envoi |
| L'adresse est **la mienne** | marquer lue la notification d'un collègue |
| L'organisation est celle du message | atteindre celle d'à côté par son identifiant |

`read_at` n'est écrit **qu'une fois** : le relire ne doit pas déplacer la date à
laquelle on l'a vue pour la première fois.

---

## 5. Ce qui ne sort pas

`body` est un `longText` qui porte le message rendu. Le panneau montre l'objet et
la date ; transporter le corps mettrait dix messages complets dans une réponse
ouverte à chaque page. Un test le vérifie sur le corps brut de la réponse.

La moitié externe est une **liste vide** quand la permission manque, jamais une
clé absente : l'écran n'a pas à distinguer « pas le droit » de « rien à
montrer », et lui dire lequel des deux renseignerait sur ce qui existe ailleurs.

---

## 6. Les fichiers

| Fichier | Rôle |
| --- | --- |
| `app/Modules/Communications/Services/UserNotifications.php` | les deux requêtes, et la projection |
| `app/Http/Controllers/Api/V1/Communications/NotificationController.php` | les trois routes |
| `app/Policies/OrderCommunicationPolicy.php` | `markAsRead` |
| `frontend/src/modules/notifications/components/NotificationBell.tsx` | la cloche et ses deux onglets |
| `frontend/src/modules/notifications/components/NotificationList.tsx` | une moitié |
| `frontend/src/modules/notifications/hooks/useNotifications.ts` | le flux, rafraîchi à la minute |

Le flux se rafraîchit **à la minute**, et au retour sur l'onglet. Les
notifications arrivent pendant qu'on travaille — une communication échoue, une
règle en déclenche une — et une cloche qui ne bouge qu'au rechargement de la
page ne sert à rien. La minute est un compromis assumé : plus court ferait une
requête pour rien la plupart du temps, plus long ferait rater ce qui vient de se
produire.

---

## 7. Ce que les tests tiennent

| Test | Ce qu'il empêche |
| --- | --- |
| `carries what is addressed to me` | une cloche muette là où quelque chose attend |
| `leaves a colleague notification alone` | lire ce qu'on écrit à un autre |
| `shows external sends only when they failed` | des envois réussis noyant les échecs |
| `answers without an organization header` | un compte plateforme enfermé dehors |
| `never carries the message body` | dix messages complets à chaque page |
| `keeps one organization out of another` | des notifications servies sous le mauvais organisme |
| `refuses an external send, whatever its recipient` | un accusé de lecture client falsifié |
| `keeps the first date when read again` | une date de lecture qui recule |
| `marks every one of mine at once, and none of theirs` | un « tout marquer » qui déborde |
| `compte les internes non lues, et elles seules` | une pastille qu'aucun geste ne fait baisser |
| `ne propose l'historique qu'avec la permission` | un lien menant à un écran qui refuse |

---

## 8. Ce qui reste ouvert

- **Rien n'alimente les notifications internes d'office.** Elles naissent d'une
  règle de communication qu'un organisme configure — canal « notification
  interne », destinataire « utilisateur interne ». Sans règle, la moitié interne
  reste vide, et c'est exact : personne n'a demandé à être prévenu.
- **Pas de notification de plateforme.** Un compte sans organisation active voit
  une cloche vide. Les événements qui le concerneraient — une organisation
  créée, un abonnement expiré — ne sont modélisés nulle part, et les inventer
  ferait une cloche qui parle sans savoir.
