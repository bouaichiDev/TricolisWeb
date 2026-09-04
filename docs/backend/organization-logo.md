# Logo d'une organisation

Un logo n'est pas un ornement : les modèles de facture l'appellent, le PDF
l'embarque, et la barre latérale le porte. Il se règle sur la **fiche d'une
organisation** — celle de la plateforme comme « Mon organisation », qui la
réutilise.

---

## 1. Deux usages, deux formes

| Qui | Ce qu'il veut | Ce qu'il reçoit |
| --- | --- | --- |
| L'écran | Une image à afficher | `GET /organizations/{organization}/logo`, le fichier |
| Le PDF de facture | Des octets à embarquer | `organization.logo`, un `data:` URI |
| La barre latérale | Savoir s'il y en a un, puis l'image | `hasLogo` sur l'appartenance, puis la même route |

**Le PDF ne peut pas aller chercher une URL.** dompdf résout chaque ressource
externe au moment du rendu : il n'a pas de session, et une facture qui pointerait
vers une route authentifiée serait une facture sans logo. Le fichier part donc
*dans* le HTML, encodé en base64 — ce que fait `OrganizationLogo::dataUri()`.

L'écran ne peut pas non plus poser l'URL dans un `src` : la route est
authentifiée, et un `<img>` partirait sans en-tête pour revenir en 401. Il
récupère le fichier par le client HTTP, puis le republie en URL d'objet.

---

## 2. Ce que la validation refuse, et pourquoi

| Règle | Raison |
| --- | --- |
| `mimes:png,jpg,jpeg,gif` | Ce que dompdf sait poser sur du papier. **Le SVG en est absent** bien qu'il soit le format naturel d'un logo : le moteur ne le rend pas, et l'accepter donnerait des factures au logo manquant sans qu'aucune erreur ne soit levée. Le WebP, de même. |
| `image` | `mimes` regarde l'extension ; `image` demande à la bibliothèque d'images de reconnaître le contenu. Un exécutable renommé `.png` passe le premier et échoue au second. |
| `max:1024` | Le fichier part **entier** dans chaque facture. Une image de dix mégaoctets ferait des factures de treize. |

---

## 3. Où vit le fichier

Sur le disque **`local`**, sous `organization-logos/{organizationId}/`, et non
sur `public` : une organisation n'a pas à voir le logo d'une autre, et un chemin
devinable sous `/storage` le donnerait à qui l'essaie. Il se sert par une route
qui vérifie l'appartenance.

Deux colonnes le désignent, pas une : `logo_path` **et** `logo_mime_type`. Le
PDF a besoin du type pour composer son `data:` URI, et l'aller chercher dans le
fichier à chaque rendu ferait une lecture disque de plus par facture.

`settings` aurait pu l'accueillir — c'est un JSON libre — mais un fichier n'est
pas un réglage : il se remplace, se supprime et se sert. Deux colonnes nommées
disent ce qu'elles portent, là où une clé enfouie dans un JSON se découvre en
lisant le code qui l'écrit.

**Le remplacement écrit le nouveau fichier avant d'effacer l'ancien.** L'inverse
laisserait l'organisation sans logo si l'écriture échouait.

Le chemin ne sort jamais par l'API : la fiche expose `hasLogo`, un booléen. Le
publier révélerait la disposition du disque, et l'écran n'en a pas besoin — il
lui suffit de savoir s'il doit demander l'image.

**`hasLogo` figure aussi sur chaque appartenance**, dans `/auth/me`. La barre
latérale est rendue avant toute autre requête : sans ce booléen, elle devrait
charger la fiche entière pour un seul champ, ou tenter le téléchargement à
l'aveugle et essuyer un 404 par organisation sans logo.

---

## 3 bis. Où il s'affiche

Dans l'en-tête de la barre latérale, à la place de l'identité de Tricolis, quand
l'organisation active en a un.

Il n'y arrive pourtant qu'en **premier de trois** : sans lui, la barre latérale
se replie sur le logo par défaut de l'installation, puis sur l'icône livrée. Ce
second niveau se règle sur la page **Configuration** — voir
`platform-configuration.md`.

Trois précautions l'encadrent, et chacune évite un défaut qu'on ne verrait pas
en le réglant :

| Précaution | Ce qu'elle évite |
| --- | --- |
| Le logo est posé sur une **tuile blanche** | la barre est bleu nuit, et un logo est dessiné pour du papier : à même le fond, tout logo à encre foncée disparaîtrait — sans qu'on puisse le deviner depuis l'écran de réglage, où l'aperçu est blanc |
| Le **nom accompagne toujours** le logo | beaucoup de logos ne sont qu'un symbole, et l'afficher seul laisserait une barre latérale que rien ne nomme |
| Un **compte plateforme garde l'identité de l'application** | il administre l'outil, pas un organisme : lui poser le logo d'une organisation laisserait croire qu'il en administre une en particulier |

Le `alt` de l'image est **vide**, délibérément : le nom de l'organisation est
juste à côté, et le répéter le ferait annoncer deux fois par un lecteur d'écran.

Il paraît aussi dans la **liste des organisations**, côté plateforme, en petit.
L'image n'y est demandée que pour les lignes dont `hasLogo` est vrai : c'est ce
qui rend la colonne tenable sur une page de vingt-cinq. Aucun repli sur le logo
de l'installation ici — il rendrait toutes les lignes identiques, ce qui est le
contraire de ce que la colonne sert à faire.

---

## 4. L'employer dans un modèle

```html
<img src="{{ organization.logo }}" alt="">
```

La variable figure dans `InvoiceRenderContext::availablePaths()`, donc dans la
liste que l'éditeur de modèle propose. Elle vaut `null` quand il n'y a pas de
logo — ou quand la ligne désigne un fichier absent : un `data:` URI vide
casserait la mise en page là où une image manquante ne fait qu'un trou.

C'est la seule variable du contexte à ne pas être une chaîne courte. Elle
n'échappe pas à la règle du §0.12 pour autant : rien n'est lu par réflexion, la
clé est écrite à la main comme les autres.

---

## 5. Ce qui le tient

| Test | Ce qu'il empêche |
| --- | --- |
| `keeps the file off the public disk` | Un logo lisible par qui devine l'URL |
| `replaces the previous file rather than piling them up` | Un disque qui grossit à chaque remplacement |
| `refuses a format the PDF engine cannot render` | Une facture au logo manquant, sans erreur |
| `refuses a file heavier than a megabyte` | Des factures alourdies par l'image |
| `never reaches another organization` | Un logo déposé chez la voisine |
| `answers 404 when there is none` | Une balise `<img>` sans réponse claire |
| `gives nothing when the file is gone` | Un `data:` URI vide dans le PDF |
| `offers the path to the template editor` | Une variable qui marche sans être proposée |
| `says whether the organization has a logo` | Une barre latérale qui charge une fiche entière pour un booléen |
| `never carries the file path` | La disposition du disque, publiée dans `/auth/me` |
| `porte le logo et le nom de l'organisation quand elle en a un` | Un en-tête qui reste celui de l'application |
| `garde l'identité de l'application pour un compte plateforme` | Le logo d'un organisme sur un compte qui n'en administre aucun |

Fichiers : `app/Modules/Organizations/Services/OrganizationLogo.php`,
`app/Http/Controllers/Api/V1/Organizations/OrganizationLogoController.php`,
`app/Http/Requests/Api/V1/Organizations/StoreOrganizationLogoRequest.php`,
`app/Http/Resources/Api/V1/Auth/UserResource.php` (le `hasLogo` de
l'appartenance), `frontend/src/modules/organizations/components/OrganizationLogoPanel.tsx`,
`frontend/src/app/layouts/SidebarBrand.tsx`.

Migration : `2026_09_04_110000_add_logo_to_organizations_table.php`.
