# Bon de livraison (BL)

Deux écrans, deux responsabilités, une seule table de modèles.

| Où | Ce qu'on y fait |
| --- | --- |
| **Modèles** | créer, modifier, dupliquer, activer/désactiver et prévisualiser la mise en page d'un BL |
| **Détail commande → Services** | choisir un modèle applicable et produire le document renseigné |

Séparer les deux n'est pas cosmétique : la mise en page engage toute
l'organisation, la génération n'engage qu'une livraison. Les réunir aurait permis
à un exploitant pressé de retoucher le BL de tous les clients depuis une
commande.

---

## 1. Le modèle

`TemplateType::DELIVERY_NOTE` entre dans l'énumération existante, à côté de
`INVOICE`. Aucune table, aucun moteur de rendu, aucun écran de plus : le §0.1 de
la Phase 9 l'interdit, et la première divergence entre deux CRUD serait passée
inaperçue.

`isDocument()` le range avec la facture, ce qui a trois conséquences immédiates,
toutes déjà en place :

- `channel` reste **nul** — un BL ne part par aucun canal. Lui inventer `email`
  le ferait apparaître dans le sélecteur des messages, où une règle de
  communication pourrait l'envoyer comme un texte ;
- `subject_template` reste nul — un document n'a pas de ligne de sujet ;
- le rendu passe par `renderDocument()`, non par `render()` : un modèle qui ne
  nomme que la moitié du contexte est normal, pas fautif.

### Portée

`customer_id` nul désigne le modèle du transporteur ; renseigné, il ne vaut que
pour ce client. `service_id` affine encore : contrairement à la facture, un BL
**garde** son service — un enlèvement se met en page autrement qu'une livraison.

`ResolveTemplateAction` tranche, du plus précis au plus général, et ne sert
jamais le modèle d'un tiers. Sa nouvelle méthode `candidates()` rend la liste
ordonnée dont `execute()` prend le premier : c'est **la même requête**, ce qui
garantit que l'écran présélectionne exactement le modèle que la génération
retiendrait.

### Catégories

`TemplateCategory` — `communication`, `invoice`, `delivery_note` — filtre la
liste côté serveur. Le filtre existe là et non dans l'écran parce que
« communication » se définit par la négative : tout ce qui n'est pas un document.
L'exprimer côté client aurait demandé d'énumérer onze natures dans l'URL, dont
une manquerait au premier ajout.

Deux portes du menu, un seul écran : `?category=communication` et
`?templateType=invoice` (l'ancienne adresse comptable, toujours honorée). Le
rayon des BL n'a **pas** sa propre entrée de menu — « Modèles » est déjà là, et
une seconde entrée vers le même écran se lit comme un second écran. Il s'atteint
par `?category=delivery_note` : c'est le filtre que la liste propose, et le lien
que l'écran de génération offre quand aucun modèle ne s'applique.

---

## 2. Le contexte de rendu

`DeliveryNoteRenderContext` construit une **liste close, écrite à la main**. Rien
n'est lu par réflexion : un modèle pouvant nommer `orderer.paymentMode` ferait
apparaître sur le papier remis au destinataire ce qui ne regarde que la
comptabilité.

| Bloc | Contenu |
| --- | --- |
| `deliveryNote` | numéro, date et heure d'édition |
| `organization` | en-tête, `logo` encodé compris |
| `orderer` | le donneur d'ordre — le client de la commande |
| `order`, `service` | références, créneau, consignes |
| `loading` | agence, point de chargement, dépôt, adresse par défaut de l'agence |
| `delivery` | adresse du service et son contact principal |
| `packages`, `articles` | les deux **listes** répétables |
| `totals` | ce que les deux tableaux additionnent |

`internal_remark` de la commande **n'y figure pas** : c'est une note interne, et
un BL se remet au client. `worker_remark` s'y trouve sous `order.remark` — elle
est écrite pour celui qui exécute, et se lit sur le terrain.

### Le périmètre est le service, jamais la commande

Les colis viennent d'`order_service_packages`, la table qui dit précisément quels
colis ce service prend en charge. Les articles viennent de leur répartition dans
ces colis (`package_order_lines`).

Les deux quantités viennent donc de deux endroits différents, et c'est voulu :
celle du colis vient du lien au service — un service peut n'en prendre qu'une
partie — celle de l'article vient de sa répartition. Lire la quantité commandée
ferait signer le destinataire pour ce qu'il n'a pas reçu.

Un article éclaté entre deux colis apparaît **deux fois**, une par colis. C'est
ce qu'on veut sur un bon de livraison : il sert à contrôler des colis à leur
ouverture, pas à totaliser une commande.

### Parité de la liste des chemins

`DeliveryNotePaths` est ce que l'éditeur propose ; le contexte est ce que le
rendu fournit. Un test tient les deux identiques — proposer un chemin absent du
contexte ferait échouer le BL au moment de le remettre, devant le client.

---

## 3. Les routes

```http
GET  /orders/{order}/services/{orderService}/delivery-note/templates
GET  /orders/{order}/services/{orderService}/delivery-note?templateId=…
POST /orders/{order}/services/{orderService}/delivery-note
```

Permissions : `delivery_notes.view` pour les deux premières,
`delivery_notes.generate` pour la troisième. Un droit à part, et non
`order_services.view` : consulter la fiche d'un service et éditer le document
qui accompagne la marchandise ne sont pas le même pouvoir.

Un `templateId` hors des candidats est **refusé**, jamais remplacé en silence par
le modèle par défaut : l'utilisateur croirait avoir généré le BL qu'il a choisi.

### Aucun repli sur une mise en page livrée

Une facture en a un, parce que refuser aurait cassé la facturation de toutes les
organisations le jour de la migration. Un BL n'a pas cet historique : il n'a
jamais été produit sans modèle, et lui inventer une mise en page ferait remettre
au destinataire un document que personne n'a relu.

Sans modèle applicable, la réponse est **409 et non 404** : la commande et le
service existent, c'est la configuration qui manque. Le message nomme l'écran où
corriger, et l'interface y mène pour qui a `templates.view`.

---

## 4. Le PDF

`GenerateDeliveryNoteAction` rend, met sur papier A4 par dompdf, écrit le fichier
sur le disque `local`, puis crée un `Document` de type `delivery_note` lié **deux
fois** : à la commande et au service. L'onglet Documents d'une commande les
montre tous ; la fiche d'un service ne montre que le sien.

Le fichier est écrit **avant** la transaction et effacé si elle échoue : un
`Storage::put` ne se défait pas par un `rollBack`, et laisser un orphelin par
échec remplit le volume sans que rien ne le signale.

### Ce qui est figé, et ce qui ne l'est pas

Le rendu lit toujours le modèle **tel qu'il est enregistré**. Ce qui est figé,
c'est le PDF déjà produit — un fichier, que retoucher le modèle ne réécrit pas.
Chaque génération crée un nouveau document ; les précédents restent.

Le numéro est déterministe — `BL-<commande>-<service>` — parce que deux
générations du même service sont le même bon, réédité ; une séquence aurait fait
croire à deux livraisons. Le **nom de fichier**, lui, porte un horodatage et un
suffixe aléatoire : l'horodatage seul ne suffit pas, deux générations dans la
même seconde écrasaient le premier fichier — un test l'a montré.

---

## 5. Le parcours, vérifié

- `tests/Feature/Api/V1/Orders/OrderServiceDeliveryNoteTest.php` — résolution,
  périmètre, refus, PDF, immuabilité, parité des chemins ;
- `frontend/src/modules/orders/components/detail/DeliveryNoteDialog.test.tsx` —
  l'écran de génération, y compris l'absence de modèle ;
- `frontend/e2e/delivery-note.spec.ts` — le parcours complet dans un navigateur :
  créer le modèle dans « Modèles », ouvrir la commande, aller à ses services,
  générer le bon, et le retrouver dans les documents de la commande.
