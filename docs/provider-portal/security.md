# Portail fournisseur — sécurité, audit, isolation (Phase 7)

Audit transversal des phases 1 à 6. Complète `access-matrix.md` (règles métier par route) et
`security-route-matrix.md` (inventaire généré depuis le routeur réel, à ne pas éditer à la main).

## 1. Chaîne d'accès

```
auth:sanctum → provider.portal → provider.permission:<codes> → scope métier → Action métier
```

| Maillon | Où | Ce qu'il garantit |
|---|---|---|
| Jeton | `ProviderPortalToken` | capacités `provider-portal` + `provider:<id>`, gravées à la connexion ; TTL `PROVIDER_PORTAL_TOKEN_TTL_MINUTES` (720) |
| Contexte | `EnsureProviderPortalContext` | à **chaque requête** : compte autorisé, rattachement `provider_users` actif, fournisseur `active`, organisation ouverte, `provider.portal.access` ; sinon 403 `provider_access_revoked` et le front abandonne la session |
| Permission | `RequireProviderPermission` | codes `provider.*` du rôle **dans l'organisation du fournisseur**, rôle actif, portée `provider` |
| Périmètre | `ProviderTourScope`, `ProviderResourceScope`, `ProviderDocumentAccess`, `ProviderSettlementScope`, `ProviderCommunications` | fournisseur **et** organisation du jeton ; ressource étrangère, en brouillon ou inexistante : même 404, même corps |
| Écriture | Actions métier existantes (`UpdateTourAction`, `UpdateTourStopAction`, `CreateDriverAction`, `CreateProofOfDeliveryAction`, `AttachEntityDocument`…) | transitions, verrous, audit ; aucun contrôleur n'écrit en base directement |

`providerId`, `organizationId`, `status`, `id` et les champs fixés par le serveur (heures, commande, documents)
sont refusés en **422** (`missing`) : la tentative est visible, jamais ignorée en silence.

**Étanchéité des jetons** : jeton fournisseur refusé par l'interne (`internal.token`) et par le portail client ;
jetons interne et client refusés par le portail fournisseur — vérifié **route par route**
(`ProviderRouteGuardsTest`).

**Un compte, plusieurs fournisseurs** : supporté par `provider_users` ; le jeton porte un seul fournisseur,
choisi à la connexion. Changer de fournisseur exige un nouveau jeton.

## 2. Tests automatisés de sécurité

| Test | Couvre |
|---|---|
| `Security/ProviderRouteGuardsTest` | toutes les routes du routeur réel : protections déclarées sans lacune, 401 anonyme, 403 jetons interne et client, 403 pour un membre qui n'a que l'accès au portail, aucune route users/roles/permissions |
| `Security/ProviderIdorTest` | Beta essaie chaque route paramétrée avec les identifiants **réels** d'Alpha : réponse identique à un identifiant inexistant (ni accès, ni oracle) |
| `ProviderPortalAccessTest` | chaque maillon du contexte tombe à la requête suivante (compte, rattachement, fournisseur, organisation, permission), jeton échu (401) |
| `ProviderPortalAuthTest` | connexion, limitation par IP + e-mail, messages neutres |
| `Tours/*`, `Resources/*`, `Finance/*`, `ProviderCommunicationsTest` | isolation et minimisation par ressource (voir `access-matrix.md`) |
| `php artisan provider-portal:route-matrix --check` | échoue si une route perd authentification, contexte, permission ou limite de débit |

## 3. Chemins indirects vérifiés

- `Tour → TourStop → TourStopService → OrderService` : seuls les services **acquis** (affectation active et confirmée,
  `ProviderAcquiredServices`) sont montrés, ouvrent des documents ou acceptent une preuve. Un service retiré d'une
  tournée déjà partie en sort aussitôt. Jamais `Order` : ni numéro, ni référence, ni client, ni prix.
- `Tour → Driver/Vehicle` : affectation limitée à la flotte active du fournisseur, sous verrou ; relue au démarrage.
- `POD → Document` : preuve visible seulement si son couple arrêt + service est acquis ; signature et photo suivent.
- `ProviderSettlement → lines` : décompte du fournisseur, clôturé ; lignes par numéro de service.
- Contacts : rôles `load`, `delivery`, `operations`, `emergency` seulement.

## 4. Contrôles transverses

| Sujet | État |
|---|---|
| CSRF / cookies | jeton Bearer (sessionStorage), pas de cookie de session ; `supports_credentials=false` |
| CORS | origines explicites ; les origines locales ne sont ajoutées **qu'hors production** |
| Débit | `provider-portal` 300/min, connexion 30/min + 5 essais IP+e-mail, création 30/min, exécution et preuves 120/min ; publiques 6 à 20/min |
| Upload | `image` + `mimes` (contenu) : ni SVG ni faux PNG ; 5 Mo, 4000 px ; nom ≤ 180 ; disque privé, nom haché ; fichiers effacés si l'enregistrement échoue |
| Téléchargement | contrôle d'accès d'abord, chemin lu en base (`..` refusé), `attachment`, `nosniff`, nom assaini |
| XSS | aucun `dangerouslySetInnerHTML` ; corps des communications rendu sans balises, affiché en texte |
| Injection | requêtes Eloquent liées ; recherche `LIKE` échappée |
| SSRF | aucune URL sortante fournie par le portail |
| Erreurs | `ExternalApiErrors` : messages génériques, aucune trace |
| Transitions | vérifiées dans les Actions sous verrou ; le front n'affiche que les actions calculées par le serveur |

## 5. Audit (`audit_logs`)

- Préfixe `provider_portal.` (`ProviderPortalAudit`) : `login`, `login_failed`, `password_changed`, `user_invited`,
  `invitation_resent`, `user_activated`, `user_disabled`, `user_enabled`, `role_changed` — avec utilisateur,
  organisation, fournisseur et IP.
- Écritures métier, auditées par leurs Actions : `tour.status_changed`, `tour.updated` (affectation),
  `tour_stop.updated`, `tour_stop.skipped` (motif), création et modification de chauffeurs et véhicules,
  `driver.account_updated`, `proof_of_delivery.created`, documents `created`.
- Ancien et nouvel état quand l'Action le porte. **Point à valider** : l'audit de modification d'un chauffeur conserve
  son téléphone (donnée personnelle) dans `old_values`/`new_values`.

## 6. Confidentialité, rétention (`BLOCKED_COMPLIANCE_DECISION`)

Aucune durée n'est inventée. À fixer par le responsable juridique : conservation des preuves de livraison,
signatures et photos (métadonnées EXIF, dont la position, conservées telles quelles), des journaux d'audit du portail,
des comptes fournisseur désactivés. Aucune purge n'est programmée.

## 7. Constats ouverts

| Constat | Gravité | Décision |
|---|---|---|
| `hasOrganizationAccess` (back-office) ignore le statut de l'appartenance `OrganizationUser` ; les comptes chauffeurs en créent une | à évaluer | cœur de l'authentification interne : hors périmètre du portail, à trancher par le propriétaire |
| `TourScopeGuard` (back-office) accepte des ressources inactives | mineur | idem |
| Démarrage depuis `planned` (référentiel) ou `confirmed` (spécification) | fonctionnel | `BLOCKED_DESIGN_DECISION` |
| Index unique `(tour_stop_id, order_service_id)` sur les preuves | durcissement | ALTER d'une table existante : accord requis ; en attendant, contrôle verrouillé dans l'Action |
| Taille minimale de signature, retrait des EXIF des photos | durcissement | à décider avec la conformité |
| Événements de suivi automatiques sans `tour_id` : absents de la chronologie | fonctionnel | documenté |
| Un décompte `closed` reste modifiable au back-office (lignes, statut, suppression) : le montant lu par le fournisseur peut changer | important | module financier interne : figer un décompte clôturé est une règle métier à valider par le propriétaire |
| Communications rattachées par adresse e-mail (aucun destinataire « fournisseur ») ; une adresse changée par un administrateur interne ouvre les e-mails reçus depuis le rattachement | à évaluer | colonne `recipient_user_id` = ALTER, accord requis |
| Origine d'une preuve (portail ou back-office) déduite de son auteur | mineur | colonne de provenance = ALTER, accord requis |

## 8. Non-régression

À chaque phase : suite Pest complète (back-office, portail client, portail fournisseur), Vitest des trois
applications concernées, Playwright des portails client et fournisseur après re-semis, `pint`,
`provider-portal:route-matrix --check`, `customer-portal:route-matrix --check`.
