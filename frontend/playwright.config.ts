import { defineConfig, devices } from '@playwright/test'

/**
 * Parcours de bout en bout du Backoffice.
 *
 * **La pile entière tourne contre la base de test**, jamais celle de
 * développement : un scénario qui clôture une facture ou annule une commande
 * écrit réellement, et le §28 réserve ce genre d'exécution à une base locale ou
 * de test. Le serveur Laravel est donc lancé avec `--env=testing`, sur le port
 * 8001 pour ne pas entrer en conflit avec un serveur de développement déjà
 * ouvert sur 8000.
 *
 * Le préalable est explicite et hors de Playwright — préparer la base :
 *
 * ```bash
 * php artisan migrate:fresh --seed --env=testing
 * ```
 *
 * Le faire ici, à chaque exécution, aurait rallongé la boucle de plusieurs
 * dizaines de secondes et rendu impossible de rejouer un seul scénario.
 *
 * Un seul navigateur : ces parcours vérifient des enchaînements métier, pas des
 * différences de moteur de rendu. Les multiplier par trois triplerait le temps
 * sans rien apprendre de plus sur le domaine.
 */
const API_PORT = 8001
const WEB_PORT = 5174

export default defineConfig({
  testDir: './e2e',
  // Un parcours qui traverse commande, planification et facture est long ; le
  // défaut de trente secondes le couperait avant sa fin.
  timeout: 90_000,
  expect: { timeout: 10_000 },

  // En série : les scénarios écrivent dans la même base, et les paralléliser
  // ferait échouer l'un par ce que l'autre a écrit.
  fullyParallel: false,
  workers: 1,

  // Un échec en intégration continue vient rarement d'un aléa ; le masquer par
  // une reprise ferait passer un défaut réel pour un test instable.
  retries: 0,
  reporter: process.env.CI ? [['github'], ['list']] : [['list']],

  use: {
    baseURL: `http://localhost:${WEB_PORT}`,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    locale: 'fr-FR',
  },

  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],

  webServer: [
    {
      command: `php artisan serve --port=${API_PORT}`,
      cwd: '..',
      // `--env=testing` ne suffit **pas** : il vaut pour la commande artisan,
      // pas pour le serveur qu'elle lance, lequel relit `.env`. Les premiers
      // essais ont ainsi écrit dans la base de développement.
      //
      // `APP_ENV` figure en revanche dans les variables que `ServeCommand`
      // transmet au processus fils, qui charge alors `.env.testing`.
      env: { APP_ENV: 'testing' },
      // `/up` est la sonde de santé de Laravel, et la seule route qui réponde
      // 200 sans jeton. Une route d'API renverrait 401 — la bonne réponse, mais
      // que Playwright lit comme un serveur absent.
      url: `http://localhost:${API_PORT}/up`,
      // **Jamais** de réutilisation ici. Un serveur déjà ouvert sur ce port a
      // pu être lancé sans `APP_ENV`, donc contre la base de développement :
      // les scénarios y écriraient des clients et des factures, ce que le §28
      // interdit. Cela s'est produit pendant l'écriture de ces tests.
      reuseExistingServer: false,
      timeout: 60_000,
    },
    {
      command: `npm run dev -- --port ${WEB_PORT}`,
      url: `http://localhost:${WEB_PORT}`,
      env: { VITE_API_URL: `http://localhost:${API_PORT}` },
      reuseExistingServer: !process.env.CI,
      timeout: 60_000,
    },
  ],
})
