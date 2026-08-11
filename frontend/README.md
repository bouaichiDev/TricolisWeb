# Tricolis — Back-office

Interface interne de la plateforme Tricolis V2, adossée à l'API Laravel du même
dépôt.

## Démarrer

```bash
npm install
cp .env.example .env.local   # renseigner VITE_API_URL
npm run dev
```

Le backend doit tourner en parallèle :

```bash
cd ..            # racine Laravel
php artisan serve
php artisan queue:work       # communications
php artisan schedule:work    # communications programmées
```

## Scripts

| Commande | Rôle |
|---|---|
| `npm run dev` | serveur de développement |
| `npm run build` | build de production |
| `npm run typecheck` | vérification TypeScript sans émission |
| `npm run test` | suite Vitest |
| `npm run test:coverage` | couverture |
| `npm run lint` | oxlint |

## Repères

- **Contrat d'API** : `../docs/frontend/backend-api-contract.md`
- **Analyse de phase** : `../docs/frontend/phase-1-analysis.md`

Toute requête métier exige deux en-têtes, posés par `src/shared/api/client.ts` :
`Authorization: Bearer …` et `X-Organization-Id`. Aucun composant n'appelle
`fetch` directement — c'est ce qui garantit qu'on ne peut pas interroger l'API
sans contexte d'organisation.
